package com.nodera.mikhmon.admin.server

import android.content.Context
import android.util.Log
import com.nodera.mikhmon.admin.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileInputStream
import java.io.FileOutputStream
import java.net.InetSocketAddress
import java.net.Socket
import java.util.zip.GZIPInputStream
import java.util.zip.ZipInputStream

class PhpServerManager(private val context: Context) {

    companion object {
        private const val TAG = "PhpServerManager"
        const val DEFAULT_PORT = 8080
    }

    private var serverProcess: Process? = null
    val phpBinary: File = File(File(context.filesDir, "bin"), "php")
    val mikhmonDir: File = File(context.filesDir, "mikhmon")
    val logFile: File = File(context.filesDir, "php_server.log")
    private val versionFile: File = File(context.filesDir, ".installed_version")

    /**
     * Checks if local PHP server is responding on 127.0.0.1:port
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
     * Extracts php binary and desktop mikhmon files from assets to app internal filesDir.
     * Preserves user router configurations in mikhmon/config across updates.
     */
    suspend fun prepareEnvironment(hwid: String): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            val installedVersion = if (versionFile.exists()) {
                versionFile.readText().trim()
            } else ""

            val currentVersion = BuildConfig.VERSION_CODE.toString()
            val needsExtraction = installedVersion != currentVersion ||
                    !phpBinary.exists() ||
                    !File(mikhmonDir, "admin.php").exists()

            if (!needsExtraction) {
                Log.i(TAG, "Environment already initialized for version $currentVersion")
                ensureExecutable(phpBinary)
                syncHwid(hwid)
                return@runCatching
            }

            Log.i(TAG, "Preparing environment: extracting PHP engine and Desktop Mikhmon files...")

            // 1. Extract and un-gzip PHP binary
            val binDir = phpBinary.parentFile ?: File(context.filesDir, "bin")
            if (!binDir.exists()) binDir.mkdirs()

            if (phpBinary.exists()) {
                phpBinary.delete()
            }

            context.assets.open("bin/php-arm64.gz").use { assetIn ->
                GZIPInputStream(assetIn).use { gzipIn ->
                    FileOutputStream(phpBinary).use { out ->
                        gzipIn.copyTo(out)
                    }
                }
            }
            ensureExecutable(phpBinary)

            // 2. Extract mikhmon.zip to filesDir/mikhmon
            if (!mikhmonDir.exists()) {
                mikhmonDir.mkdirs()
            }

            context.assets.open("mikhmon.zip").use { assetIn ->
                ZipInputStream(assetIn).use { zipIn ->
                    var entry = zipIn.nextEntry
                    while (entry != null) {
                        val entryName = entry.name
                        // Normalize path, removing leading mikhmon/ if included in zip
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
                                // Preserve user configs in mikhmon/config/ if they already exist
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

            // 3. Write HWID to mikhmon config/.hwid
            syncHwid(hwid)

            // 4. Save version stamp
            versionFile.writeText(currentVersion)
            Log.i(TAG, "Environment preparation completed successfully.")
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
     * Starts the local PHP server process serving Mikhmon web app on 127.0.0.1:port
     */
    suspend fun startServer(hwid: String, port: Int = DEFAULT_PORT): Result<Int> = withContext(Dispatchers.IO) {
        runCatching {
            if (isServerListening(port)) {
                Log.i(TAG, "PHP server is already running on port $port")
                return@runCatching port
            }

            // Ensure binary exists and is executable
            if (!phpBinary.exists()) {
                throw IllegalStateException("PHP binary not found at ${phpBinary.absolutePath}")
            }
            ensureExecutable(phpBinary)

            if (!File(mikhmonDir, "admin.php").exists()) {
                throw IllegalStateException("Desktop Mikhmon files not found in ${mikhmonDir.absolutePath}")
            }

            val pb = ProcessBuilder(
                phpBinary.absolutePath,
                "-S", "127.0.0.1:$port",
                "-t", mikhmonDir.absolutePath
            )
            pb.directory(mikhmonDir)
            pb.redirectErrorStream(true)

            val env = pb.environment()
            env["NODERA_DESKTOP_HWID"] = hwid
            env["TMPDIR"] = context.cacheDir.absolutePath

            // Log output to file
            pb.redirectOutput(ProcessBuilder.Redirect.appendTo(logFile))

            Log.i(TAG, "Launching PHP: ${phpBinary.absolutePath} -S 127.0.0.1:$port -t ${mikhmonDir.absolutePath}")
            val process = pb.start()
            serverProcess = process

            // Wait for server to bind port
            val startTime = System.currentTimeMillis()
            val timeoutMs = 15000L
            var ready = false

            while (System.currentTimeMillis() - startTime < timeoutMs) {
                if (isServerListening(port)) {
                    ready = true
                    break
                }
                if (!process.isAlive) {
                    val logSnippet = if (logFile.exists()) {
                        logFile.readLines().takeLast(15).joinToString("\n")
                    } else "No logs recorded"
                    throw IllegalStateException("PHP server process exited unexpectedly with code ${process.exitValue()}:\n$logSnippet")
                }
                kotlinx.coroutines.delay(150)
            }

            if (!ready) {
                val logSnippet = if (logFile.exists()) {
                    logFile.readLines().takeLast(15).joinToString("\n")
                } else "No logs recorded"
                throw IllegalStateException("Timed out waiting for PHP server on 127.0.0.1:$port.\nLogs:\n$logSnippet")
            }

            Log.i(TAG, "PHP server verified running and listening on port $port")
            port
        }
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
