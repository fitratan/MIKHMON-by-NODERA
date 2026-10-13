package com.nodera.mikhmon.admin

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import com.nodera.mikhmon.admin.licensing.LicenseManager
import com.nodera.mikhmon.admin.licensing.LicenseState
import com.nodera.mikhmon.admin.server.PhpServerManager
import com.nodera.mikhmon.admin.theme.*
import com.nodera.mikhmon.admin.ui.LicenseGateScreen
import com.nodera.mikhmon.admin.ui.MikhmonWebViewScreen
import kotlinx.coroutines.launch

sealed class EngineStatus {
    data class Loading(val stage: String) : EngineStatus()
    data class Running(val port: Int) : EngineStatus()
    data class Error(val error: String) : EngineStatus()
}

class MainActivity : ComponentActivity() {

    private lateinit var licenseManager: LicenseManager
    private lateinit var serverManager: PhpServerManager

    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { _ ->
        // Permissions handled
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        licenseManager = LicenseManager(this)
        serverManager = PhpServerManager(this)

        requestBluetoothPermissions()

        setContent {
            MikhmonTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MikhmonBg
                ) {
                    var engineStatus by remember {
                        mutableStateOf<EngineStatus>(EngineStatus.Loading("Mempersiapkan sistem..."))
                    }
                    var licenseInfo by remember { mutableStateOf(licenseManager.checkLicense()) }
                    var forceShowLicenseGate by remember { mutableStateOf(false) }
                    val coroutineScope = rememberCoroutineScope()

                    fun bootServer() {
                        coroutineScope.launch {
                            engineStatus = EngineStatus.Loading("Mengekstrak Engine PHP & Mikhmon...")
                            val hwid = licenseInfo.hwid

                            val prepResult = serverManager.prepareEnvironment(hwid)
                            if (prepResult.isFailure) {
                                engineStatus = EngineStatus.Error(
                                    prepResult.exceptionOrNull()?.message ?: "Gagal inisialisasi berkas server"
                                )
                                return@launch
                            }

                            engineStatus = EngineStatus.Loading("Menjalankan Server PHP Lokal...")
                            val startResult = serverManager.startServer(hwid)
                            if (startResult.isSuccess) {
                                val port = startResult.getOrThrow()
                                engineStatus = EngineStatus.Running(port)
                            } else {
                                engineStatus = EngineStatus.Error(
                                    startResult.exceptionOrNull()?.message ?: "Gagal memulai server PHP"
                                )
                            }
                        }
                    }

                    LaunchedEffect(Unit) {
                        bootServer()
                    }

                    when (val status = engineStatus) {
                        is EngineStatus.Loading -> {
                            ServerLoadingScreen(statusText = status.stage)
                        }
                        is EngineStatus.Error -> {
                            ServerErrorScreen(
                                errorMessage = status.error,
                                onRetry = { bootServer() }
                            )
                        }
                        is EngineStatus.Running -> {
                            val isAuthorized = when (licenseInfo.state) {
                                LicenseState.ACTIVE_LIFETIME,
                                LicenseState.ACTIVE_SUBSCRIPTION,
                                LicenseState.ACTIVE_TRIAL -> true
                                else -> false
                            }

                            if (isAuthorized && !forceShowLicenseGate) {
                                MikhmonWebViewScreen(
                                    initialUrl = "http://127.0.0.1:${status.port}/admin.php?id=sessions",
                                    hwid = licenseInfo.hwid,
                                    licenseInfo = licenseInfo,
                                    onOpenLicenseGate = { forceShowLicenseGate = true }
                                )
                            } else {
                                LicenseGateScreen(
                                    licenseManager = licenseManager,
                                    licenseInfo = licenseInfo,
                                    onLicenseActivated = {
                                        licenseInfo = licenseManager.checkLicense()
                                        forceShowLicenseGate = false
                                    }
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        serverManager.stopServer()
    }

    private fun requestBluetoothPermissions() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            val permissions = arrayOf(
                Manifest.permission.BLUETOOTH_CONNECT,
                Manifest.permission.BLUETOOTH_SCAN
            )
            val needed = permissions.filter {
                ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
            }
            if (needed.isNotEmpty()) {
                requestPermissionLauncher.launch(needed.toTypedArray())
            }
        }
    }
}

@Composable
fun ServerLoadingScreen(statusText: String) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(32.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = "MIKHMON",
                    color = Color.White,
                    fontWeight = FontWeight.Black,
                    fontSize = 28.sp,
                    letterSpacing = 1.sp
                )
                Spacer(modifier = Modifier.width(8.dp))
                Surface(
                    color = MikhmonBlue,
                    shape = RoundedCornerShape(4.dp)
                ) {
                    Text(
                        text = "MOBILE",
                        color = Color.White,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.height(6.dp))

            Text(
                text = "MikroTik Hotspot Admin • Standalone Engine",
                color = MikhmonTextMuted,
                fontSize = 13.sp
            )

            Spacer(modifier = Modifier.height(36.dp))

            CircularProgressIndicator(
                color = MikhmonBlue,
                strokeWidth = 3.dp,
                modifier = Modifier.size(44.dp)
            )

            Spacer(modifier = Modifier.height(20.dp))

            Text(
                text = statusText,
                color = Color.White.copy(alpha = 0.85f),
                fontSize = 14.sp,
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
fun ServerErrorScreen(
    errorMessage: String,
    onRetry: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg)
            .padding(24.dp),
        contentAlignment = Alignment.Center
    ) {
        Card(
            colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
            shape = RoundedCornerShape(12.dp),
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(
                modifier = Modifier.padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Icon(
                    Icons.Default.ErrorOutline,
                    contentDescription = "Error",
                    tint = MikhmonRed,
                    modifier = Modifier.size(48.dp)
                )

                Spacer(modifier = Modifier.height(16.dp))

                Text(
                    text = "Gagal Memulai Engine Server",
                    color = Color.White,
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp,
                    textAlign = TextAlign.Center
                )

                Spacer(modifier = Modifier.height(8.dp))

                Text(
                    text = errorMessage,
                    color = MikhmonTextMuted,
                    fontSize = 12.sp,
                    textAlign = TextAlign.Center
                )

                Spacer(modifier = Modifier.height(24.dp))

                Button(
                    onClick = onRetry,
                    colors = ButtonDefaults.buttonColors(containerColor = MikhmonBlue),
                    shape = RoundedCornerShape(8.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Icon(Icons.Default.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Coba Lagi")
                }
            }
        }
    }
}
