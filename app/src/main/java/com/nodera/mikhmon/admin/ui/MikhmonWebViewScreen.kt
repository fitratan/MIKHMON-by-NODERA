package com.nodera.mikhmon.admin.ui

import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.print.PrintAttributes
import android.print.PrintManager
import android.view.ViewGroup
import android.webkit.*
import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.nodera.mikhmon.admin.bluetooth.ThermalPrinterManager
import com.nodera.mikhmon.admin.licensing.LicenseInfo
import com.nodera.mikhmon.admin.theme.*

class MikhmonWebBridge(
    private val context: Context,
    private val hwid: String,
    private val onBackToLauncher: () -> Unit,
    private val getWebView: () -> WebView?
) {
    @JavascriptInterface
    fun getHwid(): String = hwid

    @JavascriptInterface
    fun backToHome() {
        onBackToLauncher()
    }

    @JavascriptInterface
    fun openLicenseSettings() {
        // Desktop mikhmon handles licensing internally in PHP
    }

    @JavascriptInterface
    fun printPage() {
        val webView = getWebView() ?: return
        webView.post {
            try {
                val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
                val printAdapter = webView.createPrintDocumentAdapter("Mikhmon_Print")
                printManager?.print("Mikhmon Voucher", printAdapter, PrintAttributes.Builder().build())
            } catch (e: Exception) {
                Toast.makeText(context, "Print error: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }
    }
}

@SuppressLint("SetJavaScriptEnabled")
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MikhmonWebViewScreen(
    initialUrl: String,
    hwid: String,
    onBackToLauncher: () -> Unit
) {
    val context = LocalContext.current
    var webViewInstance by remember { mutableStateOf<WebView?>(null) }
    var currentUrl by remember { mutableStateOf(initialUrl) }
    var pageTitle by remember { mutableStateOf("MIKHMON") }
    var isLoading by remember { mutableStateOf(true) }
    var canGoBack by remember { mutableStateOf(false) }
    var showTopBar by remember { mutableStateOf(false) }

    // File Chooser state for file uploads (Logo, Voucher template, etc.)
    var fileUploadCallback by remember { mutableStateOf<ValueCallback<Array<Uri>>?>(null) }
    val filePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.OpenMultipleDocuments()
    ) { uris ->
        fileUploadCallback?.onReceiveValue(uris.toTypedArray())
        fileUploadCallback = null
    }

    BackHandler(enabled = true) {
        webViewInstance?.let { webView ->
            if (webView.canGoBack()) {
                webView.goBack()
            } else {
                onBackToLauncher()
            }
        } ?: onBackToLauncher()
    }

    Scaffold(
        topBar = {
            AnimatedVisibility(
                visible = showTopBar,
                enter = expandVertically() + fadeIn(),
                exit = shrinkVertically() + fadeOut()
            ) {
                TopAppBar(
                    title = {
                        Column {
                            Text(
                                text = "MIKHMON by NODERA",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                            Text(
                                text = "HWID: $hwid",
                                fontSize = 10.sp,
                                color = MikhmonTextMuted
                            )
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MikhmonSurface
                    ),
                    actions = {
                        IconButton(onClick = {
                            webViewInstance?.loadUrl(initialUrl)
                        }) {
                            Icon(Icons.Default.Home, contentDescription = "Home", tint = Color.White)
                        }
                        IconButton(onClick = {
                            webViewInstance?.reload()
                        }) {
                            Icon(Icons.Default.Refresh, contentDescription = "Reload", tint = Color.White)
                        }
                        IconButton(onClick = onBackToLauncher) {
                            Icon(Icons.Default.PowerSettingsNew, contentDescription = "Keluar / Launcher", tint = Color(0xFFEF4444))
                        }
                        IconButton(onClick = { showTopBar = false }) {
                            Icon(Icons.Default.Fullscreen, contentDescription = "Full Screen", tint = MikhmonTextMuted)
                        }
                    }
                )
            }
        },
        floatingActionButton = {
            if (!showTopBar) {
                SmallFloatingActionButton(
                    onClick = { showTopBar = true },
                    containerColor = MikhmonBlue.copy(alpha = 0.85f),
                    contentColor = Color.White,
                    modifier = Modifier.padding(bottom = 8.dp)
                ) {
                    Icon(Icons.Default.Menu, contentDescription = "Menu Navigasi", modifier = Modifier.size(18.dp))
                }
            }
        }
    ) { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .background(MikhmonBg)
        ) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
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
                            loadsImagesAutomatically = true
                            useWideViewPort = true
                            loadWithOverviewMode = true
                            setSupportZoom(true)
                            builtInZoomControls = true
                            displayZoomControls = false
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                                mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                            }
                            userAgentString = "$userAgentString MikhmonAndroid/${com.nodera.mikhmon.admin.BuildConfig.VERSION_NAME}"
                        }

                        // Add JavaScript Bridge
                        val bridge = MikhmonWebBridge(
                            context = ctx,
                            hwid = hwid,
                            onBackToLauncher = onBackToLauncher,
                            getWebView = { this }
                        )
                        addJavascriptInterface(bridge, "nodera")
                        addJavascriptInterface(bridge, "Android")

                        webViewClient = object : WebViewClient() {
                            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                                isLoading = true
                                currentUrl = url ?: ""
                                canGoBack = view?.canGoBack() == true
                            }

                            override fun onPageFinished(view: WebView?, url: String?) {
                                isLoading = false
                                currentUrl = url ?: ""
                                canGoBack = view?.canGoBack() == true
                                pageTitle = view?.title ?: "MIKHMON"

                                // Inject helper for window.print to invoke native Android print
                                val printOverrideScript = """
                                    (function() {
                                        if (window.nodera && !window._noderaPrintHooked) {
                                            window._noderaPrintHooked = true;
                                            var origPrint = window.print;
                                            window.print = function() {
                                                window.nodera.printPage();
                                            };
                                        }
                                    })();
                                """.trimIndent()
                                view?.evaluateJavascript(printOverrideScript, null)
                            }

                            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                                val url = request?.url?.toString() ?: return false
                                return handleCustomUrl(ctx, view, url)
                            }

                            @Deprecated("Deprecated in Java")
                            override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                                if (url == null) return false
                                return handleCustomUrl(ctx, view, url)
                            }

                            private fun handleCustomUrl(context: Context, webView: WebView?, url: String): Boolean {
                                if (url.startsWith("http://127.0.0.1") || url.startsWith("http://localhost")) {
                                    return false // Load in WebView
                                }

                                // External or App intents (QuickPrinter, RawBT, WhatsApp, tel:)
                                return try {
                                    if (url.startsWith("intent:")) {
                                        val intent = Intent.parseUri(url, Intent.URI_INTENT_SCHEME)
                                        if (intent != null) {
                                            context.startActivity(intent)
                                            return true
                                        }
                                    } else if (url.startsWith("tel:") || url.startsWith("whatsapp:") || url.startsWith("https://wa.me/")) {
                                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                                        context.startActivity(intent)
                                        return true
                                    }
                                    // Other external web links open in system browser
                                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                                    context.startActivity(intent)
                                    true
                                } catch (e: Exception) {
                                    Toast.makeText(context, "Aplikasi pendukung tidak ditemukan", Toast.LENGTH_SHORT).show()
                                    true
                                }
                            }
                        }

                        webChromeClient = object : WebChromeClient() {
                            override fun onReceivedTitle(view: WebView?, title: String?) {
                                super.onReceivedTitle(view, title)
                                if (!title.isNullOrBlank()) pageTitle = title
                            }

                            override fun onShowFileChooser(
                                webView: WebView?,
                                filePathCallback: ValueCallback<Array<Uri>>?,
                                fileChooserParams: FileChooserParams?
                            ): Boolean {
                                fileUploadCallback?.onReceiveValue(null)
                                fileUploadCallback = filePathCallback
                                val mimeTypes = fileChooserParams?.acceptTypes ?: arrayOf("*/*")
                                filePickerLauncher.launch(mimeTypes)
                                return true
                            }
                        }

                        loadUrl(initialUrl)
                        webViewInstance = this
                    }
                },
                update = {
                    // Update instance if needed
                }
            )

            // Linear Progress Indicator when loading
            if (isLoading) {
                LinearProgressIndicator(
                    modifier = Modifier
                        .fillMaxWidth()
                        .align(Alignment.TopCenter),
                    color = MikhmonBlue,
                    trackColor = Color.Transparent
                )
            }
        }
    }
}
