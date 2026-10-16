package com.nodera.mikhmon.admin.bluetooth

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.content.Context
import android.content.SharedPreferences
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
        prefs.edit().putString(KEY_PRINTER_ADDR, address).putString(KEY_PRINTER_NAME, name).apply()
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

        var socket: BluetoothSocket? = null
        var outputStream: OutputStream? = null

        return try {
            val device: BluetoothDevice = adapter.getRemoteDevice(address)
            adapter.cancelDiscovery()

            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            socket.connect()
            outputStream = socket.outputStream

            outputStream.write(bytes)
            outputStream.flush()
            "OK"
        } catch (e: Exception) {
            "Gagal mencetak thermal: ${e.message}"
        } finally {
            try { outputStream?.close() } catch (_: Exception) {}
            try { socket?.close() } catch (_: Exception) {}
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

        var socket: BluetoothSocket? = null
        var outputStream: OutputStream? = null

        try {
            val device: BluetoothDevice = adapter.getRemoteDevice(printerAddress)
            adapter.cancelDiscovery()

            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            socket.connect()
            outputStream = socket.outputStream

            val charset = Charset.forName("GBK")

            for ((index, voucher) in vouchers.withIndex()) {
                outputStream.write(CMD_INIT)

                // Header
                outputStream.write(CMD_ALIGN_CENTER)
                outputStream.write(CMD_BOLD_ON)
                outputStream.write("${hotspotName.ifEmpty { "MIKHMON HOTSPOT" }}\n".toByteArray(charset))
                outputStream.write(CMD_BOLD_OFF)
                outputStream.write("================================\n".toByteArray(charset))

                // Voucher Box
                outputStream.write(CMD_ALIGN_CENTER)
                outputStream.write("KODE LOGIN VOUCHER\n".toByteArray(charset))
                outputStream.write(CMD_FONT_LARGE)
                outputStream.write(CMD_BOLD_ON)
                outputStream.write("${voucher.username}\n".toByteArray(charset))
                outputStream.write(CMD_FONT_NORMAL)
                outputStream.write(CMD_BOLD_OFF)

                if (voucher.password != voucher.username && voucher.password.isNotEmpty()) {
                    outputStream.write("PASSWORD: ${voucher.password}\n".toByteArray(charset))
                }

                outputStream.write("--------------------------------\n".toByteArray(charset))

                // Detail section
                outputStream.write(CMD_ALIGN_LEFT)
                if (voucher.profile.isNotEmpty()) {
                    outputStream.write("Paket  : ${voucher.profile}\n".toByteArray(charset))
                }
                if (voucher.timeLimit.isNotEmpty()) {
                    outputStream.write("Durasi : ${voucher.timeLimit}\n".toByteArray(charset))
                }
                if (voucher.price.isNotEmpty()) {
                    outputStream.write("Harga  : Rp ${voucher.price}\n".toByteArray(charset))
                }

                outputStream.write("--------------------------------\n".toByteArray(charset))
                outputStream.write(CMD_ALIGN_CENTER)
                if (dnsName.isNotEmpty()) {
                    outputStream.write("Login: http://$dnsName\n".toByteArray(charset))
                }
                outputStream.write("Terima kasih & Selamat Berselancar\n".toByteArray(charset))
                outputStream.write("Powered by NODERA\n".toByteArray(charset))

                // Feed paper
                outputStream.write(CMD_FEED_3)

                // Add small delay between vouchers if batch printing
                if (index < vouchers.size - 1) {
                    Thread.sleep(300)
                }
            }

            outputStream.flush()
            Result.success(true)
        } catch (e: Exception) {
            Result.failure(Exception("Gagal mencetak: ${e.message}"))
        } finally {
            try { outputStream?.close() } catch (_: Exception) {}
            try { socket?.close() } catch (_: Exception) {}
        }
    }
}
