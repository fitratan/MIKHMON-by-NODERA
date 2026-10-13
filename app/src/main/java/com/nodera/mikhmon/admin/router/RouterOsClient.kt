package com.nodera.mikhmon.admin.router

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.BufferedInputStream
import java.io.BufferedOutputStream
import java.io.ByteArrayOutputStream
import java.io.InputStream
import java.io.OutputStream
import java.net.InetSocketAddress
import java.net.Socket
import java.nio.charset.StandardCharsets
import java.security.MessageDigest
import java.util.Locale

data class RouterSystemInfo(
    val identity: String = "MikroTik",
    val model: String = "RouterBOARD",
    val version: String = "-",
    val cpuLoad: Int = 0,
    val freeMemoryMb: Long = 0,
    val totalMemoryMb: Long = 0,
    val uptime: String = "-",
    val activeUsersCount: Int = 0
)

data class ActiveHotspotUser(
    val id: String,
    val user: String,
    val address: String,
    val macAddress: String,
    val uptime: String,
    val bytesIn: Long,
    val bytesOut: Long
)

data class HotspotProfile(
    val id: String,
    val name: String,
    val sharedUsers: String = "1",
    val rateLimit: String = ""
)

data class GeneratedVoucher(
    val username: String,
    val password: String,
    val profile: String,
    val timeLimit: String,
    val price: String = ""
)

class RouterOsClient(
    private val host: String,
    private val port: Int = 8728,
    private val timeoutMs: Int = 6000
) {
    private var socket: Socket? = null
    private var inputStream: BufferedInputStream? = null
    private var outputStream: BufferedOutputStream? = null

    @Volatile
    private var isConnected: Boolean = false

    suspend fun connect(user: String, pass: String): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            close()
            val s = Socket()
            s.connect(InetSocketAddress(host, port), timeoutMs)
            s.soTimeout = timeoutMs

            val inp = BufferedInputStream(s.getInputStream())
            val out = BufferedOutputStream(s.getOutputStream())

            socket = s
            inputStream = inp
            outputStream = out

            // Login attempt (ROS 7 / ROS 6 compatible)
            val loginResult = performLogin(user, pass)
            if (loginResult) {
                isConnected = true
                Result.success(true)
            } else {
                close()
                Result.failure(Exception("Login gagal! Periksa username & password MikroTik."))
            }
        } catch (e: Exception) {
            close()
            Result.failure(Exception("Koneksi gagal ke $host:$port - ${e.message}"))
        }
    }

    private fun performLogin(user: String, pass: String): Boolean {
        // Try ROS 7 direct login first
        sendSentence(listOf("/login", "=name=$user", "=password=$pass"))
        val response = readSentenceGroup()

        var done = false
        var challenge: String? = null

        for (row in response) {
            val type = row["!type"]
            if (type == "!done") {
                if (row.containsKey("ret")) {
                    challenge = row["ret"]
                } else {
                    done = true
                }
            } else if (type == "!trap") {
                // Trap error
            }
        }

        if (done && challenge == null) {
            return true
        }

        // If challenge was returned (ROS 6 style) or direct login didn't finish
        if (challenge == null) {
            // Request challenge explicitly
            sendSentence(listOf("/login"))
            val chalResp = readSentenceGroup()
            for (row in chalResp) {
                if (row.containsKey("ret")) {
                    challenge = row["ret"]
                    break
                }
            }
        }

        if (challenge != null) {
            val md5Response = calculateChallengeResponse(pass, challenge)
            sendSentence(listOf("/login", "=name=$user", "=response=00$md5Response"))
            val authResp = readSentenceGroup()
            for (row in authResp) {
                if (row["!type"] == "!done") return true
            }
        }

        return false
    }

    private fun calculateChallengeResponse(password: String, challengeHex: String): String {
        val md = MessageDigest.getInstance("MD5")
        md.update(0.toByte())
        md.update(password.toByteArray(StandardCharsets.UTF_8))
        md.update(hexToBytes(challengeHex))
        val digest = md.digest()
        return bytesToHex(digest)
    }

    suspend fun getSystemInfo(): Result<RouterSystemInfo> = withContext(Dispatchers.IO) {
        try {
            ensureConnected()

            // 1. /system/identity/print
            sendSentence(listOf("/system/identity/print"))
            val identityRows = readSentenceGroup()
            val identity = identityRows.firstOrNull { it["!type"] == "!re" }?.get("name") ?: "MikroTik"

            // 2. /system/resource/print
            sendSentence(listOf("/system/resource/print"))
            val resourceRows = readSentenceGroup()
            val res = resourceRows.firstOrNull { it["!type"] == "!re" } ?: emptyMap()

            val cpuLoad = res["cpu-load"]?.toIntOrNull() ?: 0
            val freeMem = (res["free-memory"]?.toLongOrNull() ?: 0L) / (1024 * 1024)
            val totalMem = (res["total-memory"]?.toLongOrNull() ?: 0L) / (1024 * 1024)
            val uptime = res["uptime"] ?: "-"
            val model = res["board-name"] ?: res["platform"] ?: "MikroTik"
            val version = res["version"] ?: "-"

            // 3. /ip/hotspot/active/print (count only)
            sendSentence(listOf("/ip/hotspot/active/print", "=count-only="))
            val activeRows = readSentenceGroup()
            val activeCount = activeRows.firstOrNull { it.containsKey("ret") }?.get("ret")?.toIntOrNull() ?: 0

            Result.success(
                RouterSystemInfo(
                    identity = identity,
                    model = model,
                    version = version,
                    cpuLoad = cpuLoad,
                    freeMemoryMb = freeMem,
                    totalMemoryMb = totalMem,
                    uptime = uptime,
                    activeUsersCount = activeCount
                )
            )
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getActiveUsers(): Result<List<ActiveHotspotUser>> = withContext(Dispatchers.IO) {
        try {
            ensureConnected()
            sendSentence(listOf("/ip/hotspot/active/print"))
            val rows = readSentenceGroup()

            val list = mutableListOf<ActiveHotspotUser>()
            for (r in rows) {
                if (r["!type"] == "!re") {
                    val id = r[".id"] ?: continue
                    val user = r["user"] ?: "-"
                    val address = r["address"] ?: "-"
                    val mac = r["mac-address"] ?: "-"
                    val uptime = r["uptime"] ?: "-"
                    val bytesIn = r["bytes-in"]?.toLongOrNull() ?: 0L
                    val bytesOut = r["bytes-out"]?.toLongOrNull() ?: 0L

                    list.add(
                        ActiveHotspotUser(
                            id = id,
                            user = user,
                            address = address,
                            macAddress = mac,
                            uptime = uptime,
                            bytesIn = bytesIn,
                            bytesOut = bytesOut
                        )
                    )
                }
            }
            Result.success(list)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun kickUser(id: String): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            ensureConnected()
            sendSentence(listOf("/ip/hotspot/active/remove", "=.id=$id"))
            val resp = readSentenceGroup()
            val hasDone = resp.any { it["!type"] == "!done" }
            if (hasDone) Result.success(true) else Result.failure(Exception("Gagal menghapus sesi user"))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getHotspotProfiles(): Result<List<HotspotProfile>> = withContext(Dispatchers.IO) {
        try {
            ensureConnected()
            sendSentence(listOf("/ip/hotspot/user/profile/print"))
            val rows = readSentenceGroup()

            val list = mutableListOf<HotspotProfile>()
            for (r in rows) {
                if (r["!type"] == "!re") {
                    val id = r[".id"] ?: ""
                    val name = r["name"] ?: continue
                    val shared = r["shared-users"] ?: "1"
                    val rate = r["rate-limit"] ?: ""
                    list.add(HotspotProfile(id = id, name = name, sharedUsers = shared, rateLimit = rate))
                }
            }
            Result.success(list)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun generateVouchers(
        qty: Int,
        profile: String,
        prefix: String,
        userSameAsPass: Boolean,
        charLength: Int,
        timeLimit: String,
        price: String
    ): Result<List<GeneratedVoucher>> = withContext(Dispatchers.IO) {
        try {
            ensureConnected()
            val vouchers = mutableListOf<GeneratedVoucher>()
            val comment = "ndr-apk-" + (System.currentTimeMillis() / 1000L)

            val alphabet = "23456789abcdefghkmnpqrstuvwxyz"
            fun randomCode(len: Int): String {
                val sb = StringBuilder()
                for (i in 0 until len) {
                    sb.append(alphabet.random())
                }
                return sb.toString()
            }

            for (i in 1..qty) {
                val randomPart = randomCode(charLength)
                val username = if (prefix.isNotEmpty()) "$prefix$randomPart" else randomPart
                val password = if (userSameAsPass) username else randomCode(charLength)

                val cmd = mutableListOf(
                    "/ip/hotspot/user/add",
                    "=name=$username",
                    "=password=$password",
                    "=profile=$profile",
                    "=comment=$comment"
                )

                if (timeLimit.isNotEmpty()) {
                    cmd.add("=limit-uptime=$timeLimit")
                }

                sendSentence(cmd)
                val resp = readSentenceGroup()
                val success = resp.any { it["!type"] == "!done" }
                if (success) {
                    vouchers.add(
                        GeneratedVoucher(
                            username = username,
                            password = password,
                            profile = profile,
                            timeLimit = timeLimit,
                            price = price
                        )
                    )
                }
            }

            Result.success(vouchers)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    private fun ensureConnected() {
        if (!isConnected || socket == null || socket?.isClosed == true) {
            throw IllegalStateException("Tidak terhubung ke MikroTik! Silakan hubungkan router terlebih dahulu.")
        }
    }

    @Synchronized
    private fun sendSentence(words: List<String>) {
        val out = outputStream ?: throw IllegalStateException("Socket tertutup")
        for (w in words) {
            writeWord(w, out)
        }
        // End of sentence is an empty word (length 0)
        out.write(0x00)
        out.flush()
    }

    private fun writeWord(word: String, out: OutputStream) {
        val bytes = word.toByteArray(StandardCharsets.UTF_8)
        val len = bytes.size
        when {
            len < 0x80 -> {
                out.write(len)
            }
            len < 0x4000 -> {
                out.write((len or 0x8000) shr 8)
                out.write(len and 0xFF)
            }
            len < 0x200000 -> {
                out.write((len or 0xC00000) shr 16)
                out.write((len shr 8) and 0xFF)
                out.write(len and 0xFF)
            }
            len < 0x10000000 -> {
                out.write(((len.toLong() or 0xE0000000L) shr 24).toInt())
                out.write((len shr 16) and 0xFF)
                out.write((len shr 8) and 0xFF)
                out.write(len and 0xFF)
            }
            else -> {
                out.write(0xF0)
                out.write((len shr 24) and 0xFF)
                out.write((len shr 16) and 0xFF)
                out.write((len shr 8) and 0xFF)
                out.write(len and 0xFF)
            }
        }
        out.write(bytes)
    }

    @Synchronized
    private fun readSentenceGroup(): List<Map<String, String>> {
        val list = mutableListOf<Map<String, String>>()
        while (true) {
            val sentence = readSentence()
            if (sentence.isEmpty()) break
            list.add(sentence)
            val type = sentence["!type"]
            if (type == "!done" || type == "!trap" || type == "!fatal") {
                break
            }
        }
        return list
    }

    private fun readSentence(): Map<String, String> {
        val inp = inputStream ?: throw IllegalStateException("Socket tertutup")
        val map = mutableMapOf<String, String>()

        while (true) {
            val word = readWord(inp) ?: break // null on end of sentence
            if (word.startsWith("!")) {
                map["!type"] = word
            } else if (word.startsWith("=")) {
                val eqIdx = word.indexOf('=', 1)
                if (eqIdx != -1) {
                    val key = word.substring(1, eqIdx)
                    val value = word.substring(eqIdx + 1)
                    map[key] = value
                } else {
                    map[word.substring(1)] = ""
                }
            }
        }
        return map
    }

    private fun readWord(inp: InputStream): String? {
        val len = readLength(inp)
        if (len == 0) return null // End of sentence
        val buffer = ByteArray(len)
        var readSoFar = 0
        while (readSoFar < len) {
            val r = inp.read(buffer, readSoFar, len - readSoFar)
            if (r == -1) throw Exception("Stream closed unexpectedly")
            readSoFar += r
        }
        return String(buffer, StandardCharsets.UTF_8)
    }

    private fun readLength(inp: InputStream): Int {
        val b = inp.read()
        if (b == -1) throw Exception("Koneksi ke MikroTik terputus")
        if (b == 0) return 0

        return when {
            (b and 0x80) == 0 -> b
            (b and 0xC0) == 0x80 -> {
                val b2 = inp.read()
                ((b and 0x3F) shl 8) or b2
            }
            (b and 0xE0) == 0xC0 -> {
                val b2 = inp.read()
                val b3 = inp.read()
                ((b and 0x1F) shl 16) or (b2 shl 8) or b3
            }
            (b and 0xF0) == 0xE0 -> {
                val b2 = inp.read()
                val b3 = inp.read()
                val b4 = inp.read()
                ((b and 0x0F) shl 24) or (b2 shl 16) or (b3 shl 8) or b4
            }
            b == 0xF0 -> {
                val b1 = inp.read()
                val b2 = inp.read()
                val b3 = inp.read()
                val b4 = inp.read()
                (b1 shl 24) or (b2 shl 16) or (b3 shl 8) or b4
            }
            else -> 0
        }
    }

    fun close() {
        isConnected = false
        try { inputStream?.close() } catch (_: Exception) {}
        try { outputStream?.close() } catch (_: Exception) {}
        try { socket?.close() } catch (_: Exception) {}
        inputStream = null
        outputStream = null
        socket = null
    }

    private fun hexToBytes(hex: String): ByteArray {
        val len = hex.length
        val data = ByteArray(len / 2)
        var i = 0
        while (i < len) {
            data[i / 2] = ((Character.digit(hex[i], 16) shl 4) + Character.digit(hex[i + 1], 16)).toByte()
            i += 2
        }
        return data
    }

    private fun bytesToHex(bytes: ByteArray): String {
        val hexChars = "0123456789abcdef"
        val result = StringBuilder(bytes.size * 2)
        for (b in bytes) {
            val i = b.toInt() and 0xFF
            result.append(hexChars[i shr 4])
            result.append(hexChars[i and 0x0F])
        }
        return result.toString()
    }
}
