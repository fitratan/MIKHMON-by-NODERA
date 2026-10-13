package com.nodera.mikhmon.admin.server

import android.content.Context
import android.util.Log
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream
import java.io.InputStream
import java.net.InetSocketAddress
import java.net.Socket

class PhpServerManager(private val context: Context) {

    companion object {
        private const val TAG = "PhpServerManager"
        const val DEFAULT_PORT = 8080
        @Volatile
        var isRunning: Boolean = false
            private set
        @Volatile
        var process: Process? = null
            private set
    }

    val serverUrl: String
        get() = "http://127.0.0.1:$DEFAULT_PORT"

    /**
     * Start the internal standalone PHP server
     */
    suspend fun startServer(): Result<String> = withContext(Dispatchers.IO) {
        try {
            // Check if already running and responding
            if (isPortListening(DEFAULT_PORT)) {
                isRunning = true
                return@withContext Result.success(serverUrl)
            }

            // 1. Locate PHP executable (libphp.so in nativeLibraryDir)
            val phpBinary = locatePhpBinary()
            if (!phpBinary.exists() || !phpBinary.canExecute()) {
                // Try setting executable
                phpBinary.setExecutable(true, false)
            }

            if (!phpBinary.exists()) {
                return@withContext Result.failure(
                    Exception("Binary PHP tidak ditemukan di ${phpBinary.absolutePath}")
                )
            }

            // 2. Extract Mikhmon files from assets to filesDir/mikhmon
            val docRoot = File(context.filesDir, "mikhmon")
            extractAssetsFolder("mikhmon", docRoot)

            // Make sure cacert.pem and config directories exist
            val configDir = File(docRoot, "include")
            if (!configDir.exists()) configDir.mkdirs()

            Log.i(TAG, "Starting PHP server using: ${phpBinary.absolutePath} in ${docRoot.absolutePath}")

            // 3. Launch PHP Built-in Server: php -S 127.0.0.1:8080 -t /path/to/mikhmon
            val pb = ProcessBuilder(
                phpBinary.absolutePath,
                "-S",
                "127.0.0.1:$DEFAULT_PORT",
                "-t",
                docRoot.absolutePath
            )
            pb.directory(docRoot)
            pb.redirectErrorStream(true)

            val proc = pb.start()
            process = proc

            // 4. Wait for server to bind and listen
            var connected = false
            for (i in 1..25) {
                delay(150)
                if (isPortListening(DEFAULT_PORT)) {
                    connected = true
                    break
                }
            }

            if (connected) {
                isRunning = true
                Log.i(TAG, "PHP Server successfully running at $serverUrl")
                Result.success(serverUrl)
            } else {
                val errorStream = proc.inputStream.bufferedReader().readText()
                Result.failure(Exception("PHP Server gagal start pada port $DEFAULT_PORT: $errorStream"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error starting PHP server", e)
            Result.failure(e)
        }
    }

    private fun locatePhpBinary(): File {
        // Primary: nativeLibraryDir (standard for extractNativeLibs=true)
        val inNativeDir = File(context.applicationInfo.nativeLibraryDir, "libphp.so")
        if (inNativeDir.exists()) return inNativeDir

        // Fallback: check filesDir/bin/php
        val fallback = File(context.filesDir, "libphp.so")
        return fallback
    }

    private fun isPortListening(port: Int): Boolean {
        return try {
            Socket().use { socket ->
                socket.connect(InetSocketAddress("127.0.0.1", port), 200)
                true
            }
        } catch (e: Exception) {
            false
        }
    }

    private fun extractAssetsFolder(assetPath: String, targetDir: File) {
        val assetManager = context.assets
        val list = assetManager.list(assetPath) ?: return

        if (!targetDir.exists()) {
            targetDir.mkdirs()
        }

        if (list.isEmpty()) {
            // It's a file
            copyAssetFile(assetPath, targetDir)
        } else {
            // It's a directory
            for (item in list) {
                val subAssetPath = if (assetPath.isEmpty()) item else "$assetPath/$item"
                val subTarget = File(targetDir, item)
                val subList = assetManager.list(subAssetPath)
                if (subList != null && subList.isNotEmpty()) {
                    extractAssetsFolder(subAssetPath, subTarget)
                } else {
                    copyAssetFile(subAssetPath, subTarget)
                }
            }
        }
    }

    private fun copyAssetFile(assetPath: String, targetFile: File) {
        // If file already exists and is not zero bytes, check size or skip to preserve userdata/configs
        if (targetFile.exists() && targetFile.length() > 0) {
            // Do not overwrite user configuration files in include/ or settings/
            if (targetFile.name.endsWith(".php") && (targetFile.parentFile?.name == "include" || targetFile.parentFile?.name == "settings")) {
                return
            }
        }

        try {
            targetFile.parentFile?.mkdirs()
            context.assets.open(assetPath).use { inp ->
                FileOutputStream(targetFile).use { out ->
                    inp.copyTo(out)
                }
            }
        } catch (e: Exception) {
            Log.w(TAG, "Failed copying asset file: $assetPath", e)
        }
    }

    fun stopServer() {
        try {
            process?.destroy()
            process = null
            isRunning = false
        } catch (e: Exception) {
            Log.e(TAG, "Error stopping PHP server", e)
        }
    }
}
