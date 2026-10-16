package com.nodera.mikhmon.admin.bluetooth

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.content.Context
import android.content.SharedPreferences
import android.util.Log
import com.nodera.mikhmon.admin.router.GeneratedVoucher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.OutputStream
import java.nio.charset.Charset
import java.util.UUID

data class PairedPrinter(
    val name: String,
    val address: String
)

class ThermalPrinterManager(private val context: Context) {

    companion object {
        private const val TAG = "ThermalPrinterManager"

        // Standard Bluetooth Serial Port Profile (SPP) UUID
        private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
        private const val PREFS_NAME = "mikhmon_printer_prefs"
        private const val KEY_PRINTER_ADDR = "selected_printer_address"
        private const val KEY_PRINTER_NAME = "selected_printer_name"

        // ESC/POS Command bytes
        private val CMD_INIT = byteArrayOf(0x1B, 0x40) // ESC @
        private val CMD_ALIGN_LEFT = byteArrayOf(0x1B, 0x61, 0x00)
        private val CMD_ALIGN_CENTER = byteArrayOf(0x1B, 0x61, 0x01)
        private val CMD_ALIGN_RIGHT = byteArrayOf(0x1B, 0x61, 0x02)
        private val CMD_BOLD_ON = byteArrayOf(0x1B, 0x45, 0x01)
        private val CMD_BOLD_OFF = byteArrayOf(0x1B, 0x45, 0x00)
        private val CMD_FONT_LARGE = byteArrayOf(0x1D, 0x21, 0x11) // GS ! 0x11 (2x width + 2x height)
        private val CMD_FONT_NORMAL = byteArrayOf(0x1D, 0x21, 0x00)
        private val CMD_FEED_3 = byteArrayOf(0x0A, 0x0A, 0x0A)

        // Shared Persistent Bluetooth Socket & Stream (keeps printer LED ON)
        @Volatile
        private var sharedSocket: BluetoothSocket? = null
        @Volatile
        private var sharedOutputStream: OutputStream? = null
        @Volatile
        private var sharedConnectedAddress: String? = null
        private val socketLock = Any()
    }

    private val prefs: SharedPreferences by lazy {
        context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
    }

    private val bluetoothAdapter: BluetoothAdapter? by lazy {
        BluetoothAdapter.getDefaultAdapter()
    }

    fun isBluetoothSupported(): Boolean = bluetoothAdapter != null

    fun isBluetoothEnabled(): Boolean = bluetoothAdapter?.isEnabled == true

    fun getSelectedPrinterAddress(): String {
        val saved = prefs.getString(KEY_PRINTER_ADDR, "") ?: ""
        if (saved.isNotBlank()) return saved
        return try {
            getPairedPrinters().firstOrNull()?.address ?: ""
        } catch (_: Exception) {
            ""
        }
    }

    fun getSelectedPrinterName(): String {
        val saved = prefs.getString(KEY_PRINTER_NAME, "") ?: ""
        if (saved.isNotBlank()) return saved
        return try {
            getPairedPrinters().firstOrNull()?.name ?: ""
        } catch (_: Exception) {
            ""
        }
    }

    fun setSelectedPrinter(address: String, name: String) {
        val prevAddr = prefs.getString(KEY_PRINTER_ADDR, "") ?: ""
        prefs.edit().putString(KEY_PRINTER_ADDR, address).putString(KEY_PRINTER_NAME, name).apply()

        // If target printer changed, cycle connection to the new printer
        if (prevAddr != address && isPrinterConnected()) {
            disconnectPrinter()
        }
        // Connect in background to light up printer LED immediately
        Thread {
            try {
                connectPrinter(address)
            } catch (_: Exception) {}
        }.start()
    }

    @SuppressLint("MissingPermission")
    fun getPairedPrinters(): List<PairedPrinter> {
        val adapter = bluetoothAdapter ?: return emptyList()
        if (!adapter.isEnabled) return emptyList()

        return try {
            adapter.bondedDevices?.map { device ->
                PairedPrinter(
                    name = device.name ?: "Printer (${device.address})",
                    address = device.address
                )
            } ?: emptyList()
        } catch (e: Exception) {
            emptyList()
        }
    }

    fun getPairedPrintersJson(): String {
        val printers = getPairedPrinters()
        val arr = JSONArray()
        for (p in printers) {
            val obj = JSONObject()
            obj.put("name", p.name)
            obj.put("address", p.address)
            arr.put(obj)
        }
        return arr.toString()
    }

    fun isPrinterConnected(): Boolean {
        synchronized(socketLock) {
            return sharedSocket?.isConnected == true && sharedOutputStream != null
        }
    }

    fun disconnectPrinter() {
        synchronized(socketLock) {
            closeActiveSocketInternal()
        }
    }

    @SuppressLint("MissingPermission")
    fun connectPrinter(targetAddress: String? = null): Boolean {
        val addr = if (!targetAddress.isNullOrBlank()) targetAddress else getSelectedPrinterAddress()
        if (addr.isBlank()) return false
        return try {
            getOrConnectSocket(addr)
            true
        } catch (e: Exception) {
            Log.w(TAG, "connectPrinter failed for $addr: ${e.message}")
            false
        }
    }

    private fun closeActiveSocketInternal() {
        try { sharedOutputStream?.close() } catch (_: Exception) {}
        try { sharedSocket?.close() } catch (_: Exception) {}
        sharedSocket = null
        sharedOutputStream = null
        sharedConnectedAddress = null
    }

    @SuppressLint("MissingPermission")
    private fun getOrConnectSocket(targetAddress: String): Pair<BluetoothSocket, OutputStream> {
        synchronized(socketLock) {
            val adapter = bluetoothAdapter ?: throw Exception("Bluetooth tidak tersedia di perangkat")
            if (!adapter.isEnabled) throw Exception("Bluetooth sedang nonaktif. Silakan aktifkan Bluetooth di HP Anda.")

            // Check if existing socket is active and matches requested address
            val currentSocket = sharedSocket
            val currentStream = sharedOutputStream
            if (sharedConnectedAddress == targetAddress && currentSocket != null && currentSocket.isConnected && currentStream != null) {
                return Pair(currentSocket, currentStream)
            }

            // Close any old or dead socket before opening a fresh one
            closeActiveSocketInternal()

            val device: BluetoothDevice = adapter.getRemoteDevice(targetAddress)
            adapter.cancelDiscovery()

            val socket = try {
                device.createRfcommSocketToServiceRecord(SPP_UUID)
            } catch (e: Exception) {
                // Fallback reflection for devices with strict RFCOMM channels
                val m = device.javaClass.getMethod("createRfcommSocket", Int::class.javaPrimitiveType)
                m.invoke(device, 1) as BluetoothSocket
            }

            socket.connect()
            val stream = socket.outputStream

            sharedSocket = socket
            sharedOutputStream = stream
            sharedConnectedAddress = targetAddress

            Log.i(TAG, "Persistent Bluetooth socket established to $targetAddress. Printer ready!")
            return Pair(socket, stream)
        }
    }

    @SuppressLint("MissingPermission")
    fun printRawEscPosSync(targetAddress: String?, bytes: ByteArray): String {
        val adapter = bluetoothAdapter ?: return "Bluetooth tidak tersedia di perangkat"
        if (!adapter.isEnabled) return "Bluetooth sedang nonaktif. Silakan aktifkan Bluetooth di HP Anda."

        val address = if (!targetAddress.isNullOrBlank()) {
            targetAddress
        } else {
            val saved = getSelectedPrinterAddress()
            if (saved.isNotBlank()) saved else {
                val list = getPairedPrinters()
                if (list.isNotEmpty()) list.first().address else null
            }
        } ?: return "Tidak ada printer Bluetooth yang dipasangkan di HP. Silakan pairing printer Bluetooth terlebih dahulu."

        return try {
            val stream = try {
                getOrConnectSocket(address).second
            } catch (e: Exception) {
                // Retry once if first connection failed
                synchronized(socketLock) { closeActiveSocketInternal() }
                getOrConnectSocket(address).second
            }

            try {
                stream.write(bytes)
                stream.flush()
            } catch (writeEx: Exception) {
                // Socket was severed (e.g. printer was turned off) -> Reconnect and retry write once
                Log.w(TAG, "Socket write exception, attempting auto-reconnect: ${writeEx.message}")
                synchronized(socketLock) { closeActiveSocketInternal() }
                val reconnected = getOrConnectSocket(address)
                reconnected.second.write(bytes)
                reconnected.second.flush()
            }

            // KEEP SOCKET OPEN! Do not close it, so printer LED stays ON (Ready).
            "OK"
        } catch (e: Exception) {
            synchronized(socketLock) { closeActiveSocketInternal() }
            "Gagal mencetak thermal: ${e.message}"
        }
    }

    @SuppressLint("MissingPermission")
    suspend fun testPrint(printerAddress: String, hotspotName: String): Result<Boolean> = withContext(Dispatchers.IO) {
        val testVoucher = GeneratedVoucher(
            username = "DEMO-88",
            password = "DEMO-88",
            profile = "1 Hari 5k",
            timeLimit = "24 Jam",
            price = "5.000"
        )
        printVouchers(
            printerAddress = printerAddress,
            hotspotName = hotspotName,
            dnsName = "wifi.nodera.net",
            vouchers = listOf(testVoucher)
        )
    }

    @SuppressLint("MissingPermission")
    suspend fun printVouchers(
        printerAddress: String,
        hotspotName: String,
        dnsName: String,
        vouchers: List<GeneratedVoucher>
    ): Result<Boolean> = withContext(Dispatchers.IO) {
        val adapter = bluetoothAdapter ?: return@withContext Result.failure(Exception("Bluetooth tidak tersedia di perangkat ini"))
        if (!adapter.isEnabled) {
            return@withContext Result.failure(Exception("Bluetooth sedang mati. Harap aktifkan Bluetooth"))
        }

        try {
            val stream = try {
                getOrConnectSocket(printerAddress).second
            } catch (e: Exception) {
                synchronized(socketLock) { closeActiveSocketInternal() }
                getOrConnectSocket(printerAddress).second
            }

            val charset = Charset.forName("GBK")

            for ((index, voucher) in vouchers.withIndex()) {
                stream.write(CMD_INIT)

                // Header
                stream.write(CMD_ALIGN_CENTER)
                stream.write(CMD_BOLD_ON)
                stream.write("${hotspotName.ifEmpty { "MIKHMON HOTSPOT" }}\n".toByteArray(charset))
                stream.write(CMD_BOLD_OFF)
                stream.write("================================\n".toByteArray(charset))

                // Voucher Box
                stream.write(CMD_ALIGN_CENTER)
                stream.write("KODE LOGIN VOUCHER\n".toByteArray(charset))
                stream.write(CMD_FONT_LARGE)
                stream.write(CMD_BOLD_ON)
                stream.write("${voucher.username}\n".toByteArray(charset))
                stream.write(CMD_FONT_NORMAL)
                stream.write(CMD_BOLD_OFF)

                if (voucher.password != voucher.username && voucher.password.isNotEmpty()) {
                    stream.write("PASSWORD: ${voucher.password}\n".toByteArray(charset))
                }

                stream.write("--------------------------------\n".toByteArray(charset))

                // Detail section
                stream.write(CMD_ALIGN_LEFT)
                if (voucher.profile.isNotEmpty()) {
                    stream.write("Paket  : ${voucher.profile}\n".toByteArray(charset))
                }
                if (voucher.timeLimit.isNotEmpty()) {
                    stream.write("Durasi : ${voucher.timeLimit}\n".toByteArray(charset))
                }
                if (voucher.price.isNotEmpty()) {
                    stream.write("Harga  : Rp ${voucher.price}\n".toByteArray(charset))
                }

                stream.write("--------------------------------\n".toByteArray(charset))
                stream.write(CMD_ALIGN_CENTER)
                if (dnsName.isNotEmpty()) {
                    stream.write("Login: http://$dnsName\n".toByteArray(charset))
                }
                stream.write("Terima kasih & Selamat Berselancar\n".toByteArray(charset))
                stream.write("Powered by NODERA\n".toByteArray(charset))

                // Feed paper
                stream.write(CMD_FEED_3)

                // Add small delay between vouchers if batch printing
                if (index < vouchers.size - 1) {
                    Thread.sleep(300)
                }
            }

            stream.flush()
            // KEEP SOCKET OPEN!
            Result.success(true)
        } catch (e: Exception) {
            synchronized(socketLock) { closeActiveSocketInternal() }
            Result.failure(Exception("Gagal mencetak: ${e.message}"))
        }
    }
}
