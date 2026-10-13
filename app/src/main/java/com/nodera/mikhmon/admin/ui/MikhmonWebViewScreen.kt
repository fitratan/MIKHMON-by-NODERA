package com.nodera.mikhmon.admin.ui

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Bitmap
import android.view.ViewGroup
import android.webkit.*
import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.nodera.mikhmon.admin.bluetooth.ThermalPrinterManager
import com.nodera.mikhmon.admin.data.RouterConfigManager
import com.nodera.mikhmon.admin.licensing.LicenseInfo
import com.nodera.mikhmon.admin.router.GeneratedVoucher
import com.nodera.mikhmon.admin.server.PhpServerManager
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.launch

class MikhmonPrinterBridge(
    private val context: Context,
    private val printerManager: ThermalPrinterManager
) {
    @JavascriptInterface
    fun printVoucher(user: String, pass: String, profile: String, timeLimit: String, price: String) {
        val config = RouterConfigManager(context).loadConfig()
        if (config.printerAddress.isEmpty()) {
            Toast.makeText(context, "Bluetooth Thermal Printer belum dipilih!", Toast.LENGTH_SHORT).show()
            return
        }
        val voucher = GeneratedVoucher(
            username = user,
            password = pass,
            profile = profile,
            timeLimit = timeLimit,
            price = price
        )
        kotlinx.coroutines.GlobalScope.launch {
            printerManager.printVouchers(
                printerAddress = config.printerAddress,
                hotspotName = config.hotspotName,
                dnsName = config.dnsName,
                vouchers = listOf(voucher)
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun MikhmonWebViewScreen(
    licenseInfo: LicenseInfo,
    onOpenLicenseSettings: () -> Unit
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val phpServerManager = remember { PhpServerManager(context) }
    val printerManager = remember { ThermalPrinterManager(context) }

    var serverUrl by remember { mutableStateOf<String?>(null) }
    var isStartingServer by remember { mutableStateOf(true) }
    var startError by remember { mutableStateOf<String?>(null) }
    var webViewInstance by remember { mutableStateOf<WebView?>(null) }
    var isLoadingPage by remember { mutableStateOf(false) }
    var showQuickMenu by remember { mutableStateOf(false) }

    fun startPhpServer() {
        coroutineScope.launch {
            isStartingServer = true
            startError = null
            val result = phpServerManager.startServer()
            if (result.isSuccess) {
                serverUrl = "${result.getOrNull()}/admin.php"
            } else {
                startError = result.exceptionOrNull()?.message ?: "Gagal menjalankan PHP server"
            }
            isStartingServer = false
        }
    }

    LaunchedEffect(Unit) {
        startPhpServer()
    }

    BackHandler(enabled = webViewInstance?.canGoBack() == true) {
        webViewInstance?.goBack()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg)
            .statusBarsPadding()
            .navigationBarsPadding()
    ) {
        when {
            isStartingServer -> {
                // Loading screen while internal PHP server starts
                Column(
                    modifier = Modifier.fillMaxSize(),
                    verticalArrangement = Arrangement.Center,
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    CircularProgressIndicator(color = MikhmonBlue, strokeWidth = 3.dp)
                    Spacer(modifier = Modifier.height(16.dp))
                    Text(
                        text = "Memulai Mesin MIKHMON Internal...",
                        color = MikhmonTextPrimary,
                        fontWeight = FontWeight.Bold,
                        fontSize = 15.sp
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = "100% Offline Standalone di HP Anda",
                        color = MikhmonTextMuted,
                        fontSize = 12.sp
                    )
                }
            }

            startError != null -> {
                // Error screen
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(20.dp),
                    verticalArrangement = Arrangement.Center,
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Icon(Icons.Default.ErrorOutline, contentDescription = null, tint = MikhmonRed, modifier = Modifier.size(48.dp))
                    Spacer(modifier = Modifier.height(14.dp))
                    Text(
                        text = "Gagal Memulai MIKHMON",
                        color = MikhmonTextPrimary,
                        fontWeight = FontWeight.Bold,
                        fontSize = 16.sp
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        text = startError ?: "",
                        color = MikhmonTextSecondary,
                        fontSize = 12.sp
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Button(
                        onClick = { startPhpServer() },
                        colors = ButtonDefaults.buttonColors(containerColor = MikhmonBlue),
                        shape = RoundedCornerShape(4.dp)
                    ) {
                        Text("Coba Lagi")
                    }
                }
            }

            serverUrl != null -> {
                // The REAL MIKHMON PHP Application inside full-screen Android WebView!
                AndroidView(
                    factory = { ctx ->
                        WebView(ctx).apply {
                            layoutParams = ViewGroup.LayoutParams(
                                ViewGroup.LayoutParams.MATCH_PARENT,
                                ViewGroup.LayoutParams.MATCH_PARENT
                            )

                            settings.apply {
                                javaScriptEnabled = true
                                domStorageEnabled = true
                                databaseEnabled = true
                                allowFileAccess = true
                                allowContentAccess = true
                                useWideViewPort = true
                                loadWithOverviewMode = true
                                builtInZoomControls = true
                                displayZoomControls = false
                                mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                                cacheMode = WebSettings.LOAD_DEFAULT
                                userAgentString = "$userAgentString MikhmonAndroidApp/3.2.0"
                            }

                            addJavascriptInterface(
                                MikhmonPrinterBridge(ctx, printerManager),
                                "AndroidPrinter"
                            )

                            webViewClient = object : WebViewClient() {
                                override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                                    super.onPageStarted(view, url, favicon)
                                    isLoadingPage = true
                                }

                                override fun onPageFinished(view: WebView?, url: String?) {
                                    super.onPageFinished(view, url)
                                    isLoadingPage = false
                                }

                                override fun onReceivedError(
                                    view: WebView?,
                                    request: WebResourceRequest?,
                                    error: WebResourceError?
                                ) {
                                    super.onReceivedError(view, request, error)
                                    isLoadingPage = false
                                }
                            }

                            webChromeClient = object : WebChromeClient() {
                                override fun onProgressChanged(view: WebView?, newProgress: Int) {
                                    if (newProgress >= 90) isLoadingPage = false
                                }
                            }

                            loadUrl(serverUrl!!)
                            webViewInstance = this
                        }
                    },
                    modifier = Modifier.fillMaxSize()
                )

                if (isLoadingPage) {
                    LinearProgressIndicator(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(3.dp),
                        color = MikhmonBlue,
                        trackColor = Color.Transparent
                    )
                }

                // Floating Action Pill (Quick Toolbar: Reload, Bluetooth Printer, Info)
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp),
                    contentAlignment = Alignment.BottomEnd
                ) {
                    FloatingActionButton(
                        onClick = { showQuickMenu = true },
                        containerColor = MikhmonCardBg,
                        contentColor = MikhmonBlue,
                        shape = CircleShape,
                        modifier = Modifier.size(46.dp)
                    ) {
                        Icon(Icons.Default.MoreVert, contentDescription = "Menu Cepat", modifier = Modifier.size(22.dp))
                    }
                }
            }
        }
    }

    // Quick Menu Bottom Sheet
    if (showQuickMenu) {
        ModalBottomSheet(
            onDismissRequest = { showQuickMenu = false },
            containerColor = MikhmonCardBg
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                Text(
                    text = "MENU CEPAT MIKHMON",
                    color = MikhmonTextMuted,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold
                )

                Spacer(modifier = Modifier.height(14.dp))

                // Reload
                OutlinedButton(
                    onClick = {
                        webViewInstance?.reload()
                        showQuickMenu = false
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonTextPrimary)
                ) {
                    Icon(Icons.Default.Refresh, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Muat Ulang Halaman (Reload)")
                }

                Spacer(modifier = Modifier.height(8.dp))

                // Go to Login / Home
                OutlinedButton(
                    onClick = {
                        webViewInstance?.loadUrl("http://127.0.0.1:8080/admin.php")
                        showQuickMenu = false
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonTextPrimary)
                ) {
                    Icon(Icons.Default.Home, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Beranda / Admin Sessions")
                }

                Spacer(modifier = Modifier.height(8.dp))

                // Test Print Bluetooth
                OutlinedButton(
                    onClick = {
                        val config = RouterConfigManager(context).loadConfig()
                        if (config.printerAddress.isEmpty()) {
                            Toast.makeText(context, "Printer belum dipasang di Bluetooth HP!", Toast.LENGTH_SHORT).show()
                        } else {
                            coroutineScope.launch {
                                printerManager.testPrint(config.printerAddress, config.hotspotName)
                                Toast.makeText(context, "Cetak sample ke printer!", Toast.LENGTH_SHORT).show()
                            }
                        }
                        showQuickMenu = false
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonGreen)
                ) {
                    Icon(Icons.Default.Print, contentDescription = null, modifier = Modifier.size(16.dp), tint = MikhmonGreen)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Tes Cetak Bluetooth Thermal")
                }

                Spacer(modifier = Modifier.height(8.dp))

                // License info
                OutlinedButton(
                    onClick = {
                        showQuickMenu = false
                        onOpenLicenseSettings()
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonTextMuted)
                ) {
                    Icon(Icons.Default.Security, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Info Lisensi HWID (${licenseInfo.hwid})")
                }

                Spacer(modifier = Modifier.height(20.dp))
            }
        }
    }
}
