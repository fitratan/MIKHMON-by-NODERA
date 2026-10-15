package com.nodera.mikhmon.admin.server

import android.content.Context
import android.util.Log
import com.nodera.mikhmon.admin.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileInputStream
import java.io.FileOutputStream
import java.net.HttpURLConnection
import java.net.InetSocketAddress
import java.net.ServerSocket
import java.net.Socket
import java.net.URL
import java.util.zip.ZipInputStream

class PhpServerManager(private val context: Context) {

    companion object {
        private const val TAG = "PhpServerManager"
        const val DEFAULT_PORT = 8788
    }

    private var serverProcess: Process? = null
    val phpBinary: File
        get() {
            val nativeBin = File(context.applicationInfo.nativeLibraryDir, "libphp.so")
            if (nativeBin.exists()) return nativeBin

            val filesBin = File(File(context.filesDir, "bin"), "php")
            if (filesBin.exists()) return filesBin

            return nativeBin
        }

    val mikhmonDir: File = File(context.filesDir, "mikhmon")
    val logFile: File = File(context.filesDir, "php_server.log")
    private val versionFile: File = File(context.filesDir, ".installed_version")

    /**
     * Checks if local TCP port is accepting socket connections
     */
    fun isServerListening(port: Int = DEFAULT_PORT): Boolean {
        return try {
            Socket().use { socket ->
                socket.connect(InetSocketAddress("127.0.0.1", port), 250)
                true
            }
        } catch (_: Exception) {
            false
        }
    }

    /**
     * Probes if PHP web server is actually responding with HTTP headers (200..399)
     */
    fun isHttpReady(port: Int = DEFAULT_PORT): Boolean {
        return try {
            val url = URL("http://127.0.0.1:$port/admin.php")
            val conn = url.openConnection() as HttpURLConnection
            conn.connectTimeout = 300
            conn.readTimeout = 800
            conn.instanceFollowRedirects = false
            val code = conn.responseCode
            conn.disconnect()
            code in 200..399
        } catch (_: Exception) {
            false
        }
    }

    /**
     * Finds the next open local TCP port starting from startPort
     */
    fun findAvailablePort(startPort: Int = DEFAULT_PORT): Int {
        for (port in startPort until startPort + 50) {
            try {
                ServerSocket(port).use {
                    return port
                }
            } catch (_: Exception) {
                // Occupied, check next
            }
        }
        return startPort
    }

    /**
     * Extracts desktop mikhmon files from assets to app internal filesDir and ensures PHP binary is ready.
     * Preserves user router configurations in mikhmon/config across updates.
     */
    suspend fun prepareEnvironment(hwid: String): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            val installedVersion = if (versionFile.exists()) {
                versionFile.readText().trim()
            } else ""

            val currentVersion = BuildConfig.VERSION_CODE.toString()
            val nativeBin = File(context.applicationInfo.nativeLibraryDir, "libphp.so")
            val filesBin = File(File(context.filesDir, "bin"), "php")

            if (installedVersion != currentVersion && filesBin.exists()) {
                filesBin.delete()
            }

            // If libphp.so was not unpacked automatically by OS, extract it from APK packageCodePath
            if (!nativeBin.exists() && !filesBin.exists()) {
                Log.w(TAG, "libphp.so not found in nativeLibraryDir. Extracting from APK...")
                try {
                    filesBin.parentFile?.mkdirs()
                    ZipInputStream(FileInputStream(context.packageCodePath)).use { zipIn ->
                        var entry = zipIn.nextEntry
                        while (entry != null) {
                            if (entry.name == "lib/arm64-v8a/libphp.so") {
                                FileOutputStream(filesBin).use { out ->
                                    zipIn.copyTo(out)
                                }
                                ensureExecutable(filesBin)
                                Log.i(TAG, "Extracted fallback php binary to ${filesBin.absolutePath}")
                                break
                            }
                            zipIn.closeEntry()
                            entry = zipIn.nextEntry
                        }
                    }
                } catch (e: Exception) {
                    Log.w(TAG, "Fallback APK extraction failed: ${e.message}")
                }
            }

            val binaryToUse = phpBinary
            if (binaryToUse.exists()) {
                ensureExecutable(binaryToUse)
            } else {
                throw IllegalStateException("Binary PHP (libphp.so) tidak ditemukan di ${context.applicationInfo.nativeLibraryDir}")
            }

            val needsExtraction = installedVersion != currentVersion ||
                    !File(mikhmonDir, "admin.php").exists()

            if (!needsExtraction) {
                Log.i(TAG, "Environment already initialized for version $currentVersion")
                cleanupStaleLicenseFiles()
                syncHwid(hwid)
                return@runCatching
            }

            Log.i(TAG, "Preparing environment: extracting Desktop Mikhmon files...")

            if (!mikhmonDir.exists()) {
                mikhmonDir.mkdirs()
            }

            context.assets.open("mikhmon.zip").use { assetIn ->
                ZipInputStream(assetIn).use { zipIn ->
                    var entry = zipIn.nextEntry
                    while (entry != null) {
                        val entryName = entry.name
                        val relativePath = if (entryName.startsWith("mikhmon/")) {
                            entryName.substring("mikhmon/".length)
                        } else {
                            entryName
                        }

                        if (relativePath.isNotEmpty()) {
                            val targetFile = File(mikhmonDir, relativePath)
                            if (!targetFile.canonicalPath.startsWith(mikhmonDir.canonicalPath)) {
                                zipIn.closeEntry()
                                entry = zipIn.nextEntry
                                continue
                            }
                            if (entry.isDirectory) {
                                targetFile.mkdirs()
                            } else {
                                targetFile.parentFile?.mkdirs()
                                val isUserConfig = relativePath.startsWith("config/") &&
                                        (relativePath.endsWith(".php") || relativePath.endsWith(".json")) &&
                                        targetFile.exists()

                                if (!isUserConfig) {
                                    FileOutputStream(targetFile).use { out ->
                                        zipIn.copyTo(out)
                                    }
                                }
                            }
                        }
                        zipIn.closeEntry()
                        entry = zipIn.nextEntry
                    }
                }
            }

            cleanupStaleLicenseFiles()
            syncHwid(hwid)
            versionFile.writeText(currentVersion)
            Log.i(TAG, "Environment preparation completed successfully.")
        }
    }

    private fun cleanupStaleLicenseFiles() {
        try {
            val configDir = File(mikhmonDir, "config")
            if (configDir.exists()) {
                val staleLic = File(configDir, "license.php")
                if (staleLic.exists() && staleLic.readText().contains("75C2-3B7E-02B0")) {
                    staleLic.delete()
                    Log.i(TAG, "Deleted stale dummy license.php")
                }
                File(configDir, ".reset_alert").delete()
                File(configDir, ".trial_attempted").delete()
                File(configDir, ".clock_guard").delete()
            }
        } catch (e: Exception) {
            Log.w(TAG, "Error cleaning stale license files: ${e.message}")
        }
    }

    private fun ensureExecutable(file: File) {
        file.setReadable(true, false)
        file.setExecutable(true, false)
        try {
            Runtime.getRuntime().exec(arrayOf("chmod", "755", file.absolutePath)).waitFor()
        } catch (e: Exception) {
            Log.w(TAG, "chmod via Runtime failed, relying on File.setExecutable: ${e.message}")
        }
    }

    private fun syncHwid(hwid: String) {
        try {
            val configDir = File(mikhmonDir, "config")
            if (!configDir.exists()) configDir.mkdirs()
            val hwidFile = File(configDir, ".hwid")
            hwidFile.writeText(hwid)
        } catch (e: Exception) {
            Log.w(TAG, "Failed to write .hwid: ${e.message}")
        }
    }

    /**
     * Starts the local PHP server process serving Mikhmon web app on 0.0.0.0:port.
     * Includes retry logic: if PHP crashes on first attempt (SELinux, missing libs),
     * re-extracts the binary from APK and retries once.
     */
    suspend fun startServer(hwid: String, port: Int = DEFAULT_PORT): Result<Int> = withContext(Dispatchers.IO) {
        runCatching {
            if (isHttpReady(port)) {
                Log.i(TAG, "PHP server is already running and responding on port $port")
                return@runCatching port
            }

            val maxAttempts = 2
            var lastError: Exception? = null

            for (attempt in 1..maxAttempts) {
                try {
                    val result = attemptStartServer(hwid, port, attempt)
                    return@runCatching result
                } catch (e: Exception) {
                    lastError = e
                    Log.w(TAG, "Server start attempt $attempt failed: ${e.message}")

                    // On first failure, try re-extracting PHP binary from APK as fallback
                    if (attempt < maxAttempts) {
                        Log.i(TAG, "Retrying with fresh binary extraction...")
                        val filesBin = File(File(context.filesDir, "bin"), "php")
                        if (filesBin.exists()) filesBin.delete()
                        forceExtractPhpFromApk()
                        kotlinx.coroutines.delay(500)
                    }
                }
            }

            throw lastError ?: IllegalStateException("Failed to start PHP server after $maxAttempts attempts")
        }
    }

    private fun forceExtractPhpFromApk() {
        val filesBin = File(File(context.filesDir, "bin"), "php")
        try {
            filesBin.parentFile?.mkdirs()
            ZipInputStream(FileInputStream(context.packageCodePath)).use { zipIn ->
                var entry = zipIn.nextEntry
                while (entry != null) {
                    if (entry.name == "lib/arm64-v8a/libphp.so") {
                        FileOutputStream(filesBin).use { out ->
                            zipIn.copyTo(out)
                        }
                        ensureExecutable(filesBin)
                        Log.i(TAG, "Force-extracted PHP binary to ${filesBin.absolutePath} (${filesBin.length()} bytes)")
                        return
                    }
                    zipIn.closeEntry()
                    entry = zipIn.nextEntry
                }
            }
            Log.w(TAG, "libphp.so not found inside APK zip entries")
        } catch (e: Exception) {
            Log.e(TAG, "Force extraction failed: ${e.message}")
        }
    }

    private suspend fun attemptStartServer(hwid: String, port: Int, attempt: Int): Int {
        val binaryToUse = phpBinary
        if (!binaryToUse.exists()) {
            throw IllegalStateException("Binary PHP tidak ditemukan di ${binaryToUse.absolutePath}")
        }
        ensureExecutable(binaryToUse)

        // Verify binary is actually executable by running --version
        try {
            val versionCheck = ProcessBuilder(binaryToUse.absolutePath, "-v")
                .redirectErrorStream(true)
                .start()
            val versionOutput = versionCheck.inputStream.bufferedReader().readText()
            val versionExit = versionCheck.waitFor()
            Log.i(TAG, "PHP binary check (attempt $attempt): exit=$versionExit output=${versionOutput.take(200)}")
            if (versionExit != 0) {
                throw IllegalStateException("PHP binary failed version check (exit $versionExit): ${versionOutput.take(300)}")
            }
        } catch (e: Exception) {
            if (e is IllegalStateException) throw e
            throw IllegalStateException("PHP binary cannot execute: ${e.message}")
        }

        if (!File(mikhmonDir, "admin.php").exists()) {
            throw IllegalStateException("Desktop Mikhmon files not found in ${mikhmonDir.absolutePath}")
        }

        val cacheDir = context.cacheDir.absolutePath
        val sessionDir = File(context.filesDir, "sessions")
        if (!sessionDir.exists()) sessionDir.mkdirs()

        val phpIni = File(context.filesDir, "php.ini")
        val caFile = File(mikhmonDir, "cacert.pem")
        val caPathStr = if (caFile.exists()) caFile.absolutePath else ""

        phpIni.writeText(
            """
            [PHP]
            memory_limit = 512M
            max_execution_time = 0
            max_input_time = -1
            upload_max_filesize = 32M
            post_max_size = 32M
            sys_temp_dir = "$cacheDir"
            upload_tmp_dir = "$cacheDir"
            session.save_path = "${sessionDir.absolutePath}"
            curl.cainfo = "$caPathStr"
            openssl.cafile = "$caPathStr"
            date.timezone = "Asia/Jakarta"

            [opcache]
            opcache.enable = 0
            opcache.enable_cli = 0
            opcache.lockfile_path = "$cacheDir"
            """.trimIndent()
        )

        // Clear old log
        if (logFile.exists() && logFile.length() > 500_000) {
            logFile.writeText("")
        }

        val pb = ProcessBuilder(
            binaryToUse.absolutePath,
            "-c", phpIni.absolutePath,
            "-d", "opcache.enable=0",
            "-d", "opcache.enable_cli=0",
            "-d", "max_execution_time=0",
            "-d", "upload_max_filesize=32M",
            "-d", "post_max_size=32M",
            "-d", "sys_temp_dir=$cacheDir",
            "-d", "upload_tmp_dir=$cacheDir",
            "-d", "session.save_path=${sessionDir.absolutePath}",
            "-S", "0.0.0.0:$port",
            "-t", mikhmonDir.absolutePath
        )
        pb.directory(mikhmonDir)
        pb.redirectErrorStream(true)

        val env = pb.environment()
        env["NODERA_DESKTOP_HWID"] = hwid
        env["PHP_CLI_SERVER_WORKERS"] = "6"
        env["TMPDIR"] = cacheDir
        env["TEMP"] = cacheDir
        env["TMP"] = cacheDir
        env["HOME"] = context.filesDir.absolutePath
        // Ensure linker can find libs in app native dir
        val nativeLibDir = context.applicationInfo.nativeLibraryDir
        env["LD_LIBRARY_PATH"] = "$nativeLibDir:/system/lib64:/vendor/lib64"

        try {
            val licInfo = com.nodera.mikhmon.admin.licensing.LicenseManager(context).checkLicense()
            if (licInfo.state == com.nodera.mikhmon.admin.licensing.LicenseState.ACTIVE_LIFETIME ||
                licInfo.state == com.nodera.mikhmon.admin.licensing.LicenseState.ACTIVE_SUBSCRIPTION ||
                licInfo.state == com.nodera.mikhmon.admin.licensing.LicenseState.ACTIVE_TRIAL) {
                env["NODERA_LICENSE_KEY"] = licInfo.licenseKey
                env["NODERA_LICENSE_STATUS"] = "ACTIVE"
                env["NODERA_LICENSE_EXPIRY"] = if (licInfo.expiresAt > 0) {
                    java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.US).format(java.util.Date(licInfo.expiresAt * 1000L))
                } else "LIFETIME"
                env["NODERA_LICENSE_PRODUCT"] = when (licInfo.state) {
                    com.nodera.mikhmon.admin.licensing.LicenseState.ACTIVE_TRIAL -> "Mikhmon Mobile Admin (Trial)"
                    com.nodera.mikhmon.admin.licensing.LicenseState.ACTIVE_SUBSCRIPTION -> "Mikhmon Mobile Admin (Subscription)"
                    else -> "Mikhmon Mobile Admin (Lifetime)"
                }
            }
        } catch (_: Exception) {}

        pb.redirectOutput(ProcessBuilder.Redirect.appendTo(logFile))

        Log.i(TAG, "Launching PHP (attempt $attempt): ${binaryToUse.absolutePath} -S 0.0.0.0:$port -t ${mikhmonDir.absolutePath}")
        val process = pb.start()
        serverProcess = process

        val startTime = System.currentTimeMillis()
        val timeoutMs = 20000L
        var ready = false

        while (System.currentTimeMillis() - startTime < timeoutMs) {
            if (isHttpReady(port)) {
                ready = true
                break
            }
            if (isServerListening(port)) {
                // Socket open but HTTP not yet ready, give it a moment
                kotlinx.coroutines.delay(300)
                if (isHttpReady(port)) {
                    ready = true
                    break
                }
            }
            if (!process.isAlive) {
                val logSnippet = if (logFile.exists()) {
                    logFile.readLines().takeLast(40).joinToString("\n")
                } else "No logs recorded"
                throw IllegalStateException("PHP process crashed (exit ${process.exitValue()}, attempt $attempt):\n$logSnippet")
            }
            kotlinx.coroutines.delay(200)
        }

        if (!ready) {
            process.destroyForcibly()
            serverProcess = null
            val logSnippet = if (logFile.exists()) {
                logFile.readLines().takeLast(40).joinToString("\n")
            } else "No logs recorded"
            throw IllegalStateException("Timeout waiting for PHP server on port $port (attempt $attempt).\nLogs:\n$logSnippet")
        }

        Log.i(TAG, "PHP server verified running on port $port (attempt $attempt)")
        return port
    }

    fun stopServer() {
        try {
            serverProcess?.let { p ->
                if (p.isAlive) {
                    p.destroyForcibly()
                }
            }
            serverProcess = null
        } catch (e: Exception) {
            Log.w(TAG, "Error stopping PHP server: ${e.message}")
        }
    }
}
