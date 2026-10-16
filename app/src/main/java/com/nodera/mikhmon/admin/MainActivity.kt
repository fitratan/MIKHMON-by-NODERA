package com.nodera.mikhmon.admin

import android.Manifest
import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import com.nodera.mikhmon.admin.licensing.LicenseManager
import com.nodera.mikhmon.admin.server.PhpServerManager
import com.nodera.mikhmon.admin.theme.*
import com.nodera.mikhmon.admin.ui.MikhmonWebViewScreen
import kotlinx.coroutines.launch

enum class AppScreen {
    LAUNCHER,
    WEBVIEW
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
                    val context = LocalContext.current
                    val coroutineScope = rememberCoroutineScope()
                    val hwid = remember { licenseManager.getHardwareId() }

                    var currentScreen by remember { mutableStateOf(AppScreen.LAUNCHER) }
                    var isServerStarting by remember { mutableStateOf(false) }
                    var startingStage by remember { mutableStateOf("Mempersiapkan...") }
                    var serverPort by remember { mutableStateOf<Int?>(null) }
                    var errorMessage by remember { mutableStateOf<String?>(null) }

                    // Check if server is already running on resume/create
                    LaunchedEffect(Unit) {
                        if (serverManager.isHttpReady(PhpServerManager.DEFAULT_PORT)) {
                            serverPort = PhpServerManager.DEFAULT_PORT
                        }
                    }

                    fun startServerAndLaunch() {
                        if (isServerStarting) return
                        isServerStarting = true
                        errorMessage = null

                        coroutineScope.launch {
                            try {
                                startingStage = "Mengekstrak Engine & Mikhmon..."
                                val prepResult = serverManager.prepareEnvironment(hwid)
                                if (prepResult.isFailure) {
                                    isServerStarting = false
                                    errorMessage = prepResult.exceptionOrNull()?.message ?: "Gagal menyiapkan berkas Mikhmon"
                                    return@launch
                                }

                                startingStage = "Menjalankan PHP Engine Lokal..."
                                val targetPort = serverManager.findAvailablePort(PhpServerManager.DEFAULT_PORT)
                                val startResult = serverManager.startServer(hwid, targetPort)
                                if (startResult.isSuccess) {
                                    val port = startResult.getOrThrow()
                                    serverPort = port
                                    isServerStarting = false
                                    currentScreen = AppScreen.WEBVIEW
                                } else {
                                    isServerStarting = false
                                    errorMessage = startResult.exceptionOrNull()?.message ?: "Gagal menjalankan server PHP"
                                }
                            } catch (e: Exception) {
                                isServerStarting = false
                                errorMessage = e.message ?: "Terjadi kesalahan saat memulai server"
                            }
                        }
                    }

                    fun stopServer() {
                        serverManager.stopServer()
                        serverPort = null
                        Toast.makeText(context, "Server PHP dihentikan", Toast.LENGTH_SHORT).show()
                    }

                    AnimatedContent(
                        targetState = currentScreen,
                        label = "screen_transition"
                    ) { target ->
                        when (target) {
                            AppScreen.LAUNCHER -> {
                                NoderaLauncherScreen(
                                    hwid = hwid,
                                    serverPort = serverPort,
                                    isStarting = isServerStarting,
                                    startingStage = startingStage,
                                    errorMessage = errorMessage,
                                    onDismissError = { errorMessage = null },
                                    onStartServer = { startServerAndLaunch() },
                                    onOpenWebView = { currentScreen = AppScreen.WEBVIEW },
                                    onStopServer = { stopServer() }
                                )
                            }
                            AppScreen.WEBVIEW -> {
                                MikhmonWebViewScreen(
                                    initialUrl = "http://127.0.0.1:${serverPort ?: PhpServerManager.DEFAULT_PORT}/admin.php",
                                    hwid = hwid,
                                    onBackToLauncher = {
                                        currentScreen = AppScreen.LAUNCHER
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
fun NoderaLauncherScreen(
    hwid: String,
    serverPort: Int?,
    isStarting: Boolean,
    startingStage: String,
    errorMessage: String?,
    onDismissError: () -> Unit,
    onStartServer: () -> Unit,
    onOpenWebView: () -> Unit,
    onStopServer: () -> Unit
) {
    val context = LocalContext.current

    fun copyHwid() {
        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        val clip = ClipData.newPlainText("HWID", hwid)
        clipboard.setPrimaryClip(clip)
        Toast.makeText(context, "HWID disalin ke clipboard", Toast.LENGTH_SHORT).show()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg)
            .statusBarsPadding()
            .navigationBarsPadding()
            .padding(24.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(vertical = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            // Top Section: Logo & Titles
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.padding(top = 16.dp)
            ) {
                // Official NODERA Logo
                Box(
                    modifier = Modifier
                        .size(110.dp)
                        .clip(CircleShape)
                        .background(Color(0xFF0F172A))
                        .border(2.dp, MikhmonBlue.copy(alpha = 0.5f), CircleShape),
                    contentAlignment = Alignment.Center
                ) {
                    Image(
                        painter = painterResource(id = R.drawable.nodera_logo),
                        contentDescription = "NODERA Logo",
                        modifier = Modifier
                            .size(96.dp)
                            .clip(CircleShape)
                    )
                }

                Spacer(modifier = Modifier.height(20.dp))

                Text(
                    text = "MIKHMON",
                    color = Color.White,
                    fontWeight = FontWeight.Black,
                    fontSize = 30.sp,
                    letterSpacing = 1.5.sp
                )

                Spacer(modifier = Modifier.height(6.dp))

                Text(
                    text = "MikroTik Hotspot Manager • Standalone Engine",
                    color = MikhmonTextMuted,
                    fontSize = 13.sp,
                    textAlign = TextAlign.Center
                )

                Spacer(modifier = Modifier.height(14.dp))

                // HWID Chip
                Surface(
                    color = MikhmonSurface,
                    shape = RoundedCornerShape(20.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, MikhmonBorder),
                    modifier = Modifier.clickable { copyHwid() }
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "HWID: $hwid",
                            color = MikhmonTextSecondary,
                            fontSize = 11.sp,
                            fontFamily = FontFamily.Monospace,
                            fontWeight = FontWeight.Medium
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Icon(
                            Icons.Default.ContentCopy,
                            contentDescription = "Salin HWID",
                            tint = MikhmonBlue,
                            modifier = Modifier.size(13.dp)
                        )
                    }
                }
            }

            // Middle Section: Status & Error if any
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.fillMaxWidth()
            ) {
                if (errorMessage != null) {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
                        border = androidx.compose.foundation.BorderStroke(1.dp, MikhmonRed.copy(alpha = 0.5f)),
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 16.dp)
                    ) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.ErrorOutline, contentDescription = null, tint = MikhmonRed, modifier = Modifier.size(20.dp))
                                Spacer(modifier = Modifier.width(8.dp))
                                Text("Gagal Menjalankan Server", color = MikhmonRed, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                            }
                            Spacer(modifier = Modifier.height(6.dp))
                            Text(
                                text = errorMessage,
                                color = MikhmonTextSecondary,
                                fontSize = 11.5.sp,
                                lineHeight = 16.sp
                            )
                        }
                    }
                }

                // Status Badge Card
                Surface(
                    color = MikhmonSurface,
                    shape = RoundedCornerShape(12.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, MikhmonBorder),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 14.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(10.dp)
                                    .clip(CircleShape)
                                    .background(
                                        when {
                                            serverPort != null -> MikhmonGreen
                                            isStarting -> MikhmonYellow
                                            else -> MikhmonTextMuted
                                        }
                                    )
                            )
                            Spacer(modifier = Modifier.width(10.dp))
                            Text(
                                text = when {
                                    serverPort != null -> "Server Aktif (Port $serverPort)"
                                    isStarting -> startingStage
                                    else -> "Server Offline"
                                },
                                color = Color.White,
                                fontWeight = FontWeight.SemiBold,
                                fontSize = 13.5.sp
                            )
                        }

                        if (serverPort != null) {
                            Text(
                                text = "127.0.0.1:$serverPort",
                                color = MikhmonGreen,
                                fontSize = 11.5.sp,
                                fontFamily = FontFamily.Monospace,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }
            }

            // Bottom Section: The Single START Button / Action Controls
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.fillMaxWidth()
            ) {
                if (serverPort == null) {
                    // THE SINGLE START BUTTON requested by user
                    Button(
                        onClick = onStartServer,
                        enabled = !isStarting,
                        colors = ButtonDefaults.buttonColors(
                            containerColor = MikhmonBlue,
                            disabledContainerColor = MikhmonBlue.copy(alpha = 0.5f)
                        ),
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(54.dp)
                    ) {
                        if (isStarting) {
                            CircularProgressIndicator(
                                color = Color.White,
                                strokeWidth = 2.5.dp,
                                modifier = Modifier.size(22.dp)
                            )
                            Spacer(modifier = Modifier.width(10.dp))
                            Text(
                                text = "MEMULAI ENGINE...",
                                fontWeight = FontWeight.Bold,
                                fontSize = 15.sp,
                                letterSpacing = 1.sp
                            )
                        } else {
                            Icon(
                                Icons.Default.PlayArrow,
                                contentDescription = null,
                                modifier = Modifier.size(24.dp)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = "START MIKHMON",
                                fontWeight = FontWeight.Black,
                                fontSize = 16.sp,
                                letterSpacing = 1.sp
                            )
                        }
                    }
                } else {
                    // When server is already running: BUKA MIKHMON or HENTIKAN SERVER
                    Button(
                        onClick = onOpenWebView,
                        colors = ButtonDefaults.buttonColors(containerColor = MikhmonGreen),
                        shape = RoundedCornerShape(12.dp),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(52.dp)
                    ) {
                        Icon(Icons.Default.Launch, contentDescription = null, modifier = Modifier.size(20.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "BUKA MIKHMON",
                            fontWeight = FontWeight.Bold,
                            fontSize = 15.sp,
                            letterSpacing = 0.5.sp
                        )
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    OutlinedButton(
                        onClick = onStopServer,
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonRed),
                        border = androidx.compose.foundation.BorderStroke(1.dp, MikhmonRed.copy(alpha = 0.6f)),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(44.dp)
                    ) {
                        Icon(Icons.Default.Stop, contentDescription = null, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "Hentikan Server",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }

                Spacer(modifier = Modifier.height(18.dp))

                Text(
                    text = "v${BuildConfig.VERSION_NAME} • Standalone Offline Engine",
                    color = MikhmonTextMuted,
                    fontSize = 11.sp,
                    textAlign = TextAlign.Center
                )
            }
        }
    }
}
