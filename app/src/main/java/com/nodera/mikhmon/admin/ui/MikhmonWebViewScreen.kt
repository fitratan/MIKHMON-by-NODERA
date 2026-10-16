package com.nodera.mikhmon.admin.ui

import android.annotation.SuppressLint
import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.media.MediaScannerConnection
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.os.Handler
import android.os.Looper
import android.print.PrintAttributes
import android.print.PrintManager
import android.util.Base64
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
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File

class MikhmonWebBridge(
    private val context: Context,
    private val hwid: String,
    private val printerManager: ThermalPrinterManager,
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
        // Handled internally in PHP
    }

    @JavascriptInterface
    fun getPairedPrinters(): String {
        return printerManager.getPairedPrintersJson()
    }

    @JavascriptInterface
    fun getSelectedPrinterAddress(): String {
        return printerManager.getSelectedPrinterAddress()
    }

    @JavascriptInterface
    fun getSelectedPrinterName(): String {
        return printerManager.getSelectedPrinterName()
    }

    @JavascriptInterface
    fun setSelectedPrinter(address: String, name: String): Boolean {
        printerManager.setSelectedPrinter(address, name)
        try {
            val cfgMgr = com.nodera.mikhmon.admin.data.RouterConfigManager(context)
            val current = cfgMgr.loadConfig()
            cfgMgr.saveConfig(current.copy(printerAddress = address, printerName = name))
        } catch (_: Exception) {}
        return true
    }

    @JavascriptInterface
    fun printThermalEscPos(base64Data: String): String {
        return try {
            val bytes = Base64.decode(base64Data, Base64.DEFAULT)
            printerManager.printRawEscPosSync(null, bytes)
        } catch (e: Exception) {
            "Gagal memproses data cetak: ${e.message}"
        }
    }

    @JavascriptInterface
    fun isPrinterConnected(): Boolean {
        return printerManager.isPrinterConnected()
    }

    @JavascriptInterface
    fun connectPrinter(address: String?): Boolean {
        return printerManager.connectPrinter(address)
    }

    @JavascriptInterface
    fun disconnectPrinter(): Boolean {
        printerManager.disconnectPrinter()
        return true
    }

    @JavascriptInterface
    fun printPage() {
        val webView = getWebView() ?: return
        webView.post {
            try {
                val printManager = context.getSystemService(Context.PRINT_SERVICE) as? PrintManager
                val printAdapter = webView.createPrintDocumentAdapter("Mikhmon_Voucher")
                printManager?.print("Mikhmon Voucher", printAdapter, PrintAttributes.Builder().build())
            } catch (e: Exception) {
                Toast.makeText(context, "Gagal mencetak: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }
    }

    @JavascriptInterface
    fun saveBase64File(base64Data: String, filename: String, mimeType: String): Boolean {
        return try {
            val bytes = Base64.decode(base64Data, Base64.DEFAULT)
            val downloadDir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS)
            if (!downloadDir.exists()) downloadDir.mkdirs()
            val targetFile = File(downloadDir, filename)
            targetFile.writeBytes(bytes)
            MediaScannerConnection.scanFile(context, arrayOf(targetFile.absolutePath), arrayOf(mimeType), null)
            Handler(Looper.getMainLooper()).post {
                Toast.makeText(context, "Tersimpan di Download: $filename", Toast.LENGTH_LONG).show()
            }
            true
        } catch (e: Exception) {
            Handler(Looper.getMainLooper()).post {
                Toast.makeText(context, "Gagal mengunduh berkas: ${e.message}", Toast.LENGTH_SHORT).show()
            }
            false
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
    val printerManager = remember { ThermalPrinterManager(context) }
    var webViewInstance by remember { mutableStateOf<WebView?>(null) }
    var currentUrl by remember { mutableStateOf(initialUrl) }
    var pageTitle by remember { mutableStateOf("MIKHMON") }
    var isLoading by remember { mutableStateOf(true) }
    var canGoBack by remember { mutableStateOf(false) }
    var showTopBar by remember { mutableStateOf(false) }

    // Auto-connect to saved printer when Mikhmon WebView launches to keep LED ON solid
    LaunchedEffect(Unit) {
        withContext(Dispatchers.IO) {
            try {
                printerManager.connectPrinter()
            } catch (_: Exception) {}
        }
    }

    val isVoucherPrintPage = currentUrl.contains("voucher/print.php")

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
                visible = showTopBar || isVoucherPrintPage,
                enter = expandVertically() + fadeIn(),
                exit = shrinkVertically() + fadeOut()
            ) {
                TopAppBar(
                    navigationIcon = {
                        if (canGoBack || isVoucherPrintPage) {
                            IconButton(onClick = {
                                webViewInstance?.let { wb ->
                                    if (wb.canGoBack()) wb.goBack() else onBackToLauncher()
                                }
                            }) {
                                Icon(Icons.Default.ArrowBack, contentDescription = "Kembali", tint = Color.White)
                            }
                        }
                    },
                    title = {
                        Column {
                            Text(
                                text = if (isVoucherPrintPage) "Cetak Voucher Mikhmon" else "MIKHMON by NODERA",
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                            Text(
                                text = if (isVoucherPrintPage) "Pratinjau Struk & Dokumen" else "HWID: $hwid",
                                fontSize = 10.sp,
                                color = MikhmonTextMuted
                            )
                        }
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = MikhmonSurface
                    ),
                    actions = {
                        if (isVoucherPrintPage) {
                            // Tombol khusus halaman cetak voucher: Cetak Thermal Bluetooth & Cetak PDF
                            IconButton(
                                onClick = {
                                    webViewInstance?.evaluateJavascript("""
                                        (function() {
                                            if (window.MikhmonThermal) {
                                                window.MikhmonThermal.printAllThermalBt();
                                            } else {
                                                alert('Modul thermal belum siap');
                                            }
                                        })();
                                    """.trimIndent(), null)
                                }
                            ) {
                                Icon(Icons.Default.Receipt, contentDescription = "Cetak Thermal", tint = MikhmonYellow)
                            }
                            IconButton(
                                onClick = {
                                    webViewInstance?.evaluateJavascript("window.print();", null)
                                }
                            ) {
                                Icon(Icons.Default.Print, contentDescription = "Cetak PDF/Browser", tint = MikhmonBlue)
                            }
                        } else {
                            // Halaman biasa: hanya Refresh dan Keluar ke Launcher
                            IconButton(onClick = {
                                webViewInstance?.reload()
                            }) {
                                Icon(Icons.Default.Refresh, contentDescription = "Muat Ulang", tint = Color.White)
                            }
                            IconButton(onClick = onBackToLauncher) {
                                Icon(Icons.Default.PowerSettingsNew, contentDescription = "Keluar / Ganti Host", tint = Color(0xFFEF4444))
                            }
                        }
                    }
                )
            }
        },
        floatingActionButton = {
            if (!showTopBar && !isVoucherPrintPage) {
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

                        // Explicitly configure CookieManager
                        val cookieManager = CookieManager.getInstance()
                        cookieManager.setAcceptCookie(true)
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                            cookieManager.setAcceptThirdPartyCookies(this, true)
                        }

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
                            setSupportMultipleWindows(true)
                            javaScriptCanOpenWindowsAutomatically = true
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                                mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                            }
                            userAgentString = "$userAgentString MikhmonAndroid/${com.nodera.mikhmon.admin.BuildConfig.VERSION_NAME}"
                        }

                        // Add JavaScript Bridge
                        val bridge = MikhmonWebBridge(
                            context = ctx,
                            hwid = hwid,
                            printerManager = printerManager,
                            onBackToLauncher = onBackToLauncher,
                            getWebView = { this }
                        )
                        addJavascriptInterface(bridge, "nodera")
                        addJavascriptInterface(bridge, "Android")

                        // Handle downloads via native DownloadManager
                        setDownloadListener { url, userAgent, contentDisposition, mimetype, _ ->
                            try {
                                val request = DownloadManager.Request(Uri.parse(url)).apply {
                                    setMimeType(mimetype)
                                    val filename = URLUtil.guessFileName(url, contentDisposition, mimetype)
                                    setTitle(filename)
                                    setDescription("Mengunduh berkas Mikhmon...")
                                    setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                                    setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, filename)
                                    addRequestHeader("User-Agent", userAgent)
                                    val cookies = CookieManager.getInstance().getCookie(url)
                                    if (!cookies.isNullOrBlank()) {
                                        addRequestHeader("Cookie", cookies)
                                    }
                                }
                                val dm = ctx.getSystemService(Context.DOWNLOAD_SERVICE) as? DownloadManager
                                dm?.enqueue(request)
                                Toast.makeText(ctx, "Mengunduh ke folder Download...", Toast.LENGTH_SHORT).show()
                            } catch (e: Exception) {
                                Toast.makeText(ctx, "Gagal mengunduh: ${e.message}", Toast.LENGTH_SHORT).show()
                            }
                        }

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

                                // Inject helpers for window.print, window.open, and Blob downloads
                                val clientInjectionScript = """
                                    (function() {
                                        // 1. Hook window.print to native Android PrintManager
                                        if (window.nodera && !window._noderaPrintHooked) {
                                            window._noderaPrintHooked = true;
                                            window.print = function() {
                                                window.nodera.printPage();
                                            };
                                        }

                                        // 2. Intercept window.open so voucher printing popups open directly in WebView
                                        if (window.nodera && !window._noderaOpenHooked) {
                                            window._noderaOpenHooked = true;
                                            window.open = function(url, target, features) {
                                                if (url) {
                                                    window.location.href = url;
                                                }
                                                return {
                                                    print: function() {
                                                        // Handled automatically or by print button
                                                    },
                                                    focus: function() {},
                                                    close: function() {}
                                                };
                                            };
                                        }

                                        // 3. Intercept Blob downloads (Export Users CSV, Selling Report CSV, User Log CSV)
                                        if (window.nodera && !window._noderaDownloadHooked) {
                                            window._noderaDownloadHooked = true;
                                            var blobMap = {};
                                            var origCreateObjectURL = window.URL.createObjectURL;
                                            if (origCreateObjectURL) {
                                                window.URL.createObjectURL = function(blob) {
                                                    var url = origCreateObjectURL.call(window.URL, blob);
                                                    blobMap[url] = blob;
                                                    return url;
                                                };
                                            }
                                            document.addEventListener('click', function(e) {
                                                var a = e.target.closest('a[download]');
                                                if (a && a.href && a.href.indexOf('blob:') === 0) {
                                                    e.preventDefault();
                                                    var blob = blobMap[a.href];
                                                    var filename = a.getAttribute('download') || 'download.csv';
                                                    if (blob) {
                                                        var reader = new FileReader();
                                                        reader.onload = function() {
                                                            var res = reader.result;
                                                            if (typeof res === 'string') {
                                                                var b64 = res.split(',')[1];
                                                                if (b64) {
                                                                    window.nodera.saveBase64File(b64, filename, blob.type || 'text/csv');
                                                                }
                                                            }
                                                        };
                                                        reader.readAsDataURL(blob);
                                                    }
                                                }
                                            }, true);
                                        }
                                    })();
                                """.trimIndent()
                                view?.evaluateJavascript(clientInjectionScript, null)
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

                                // External or App intents (RawBT, WhatsApp, tel:)
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

                            override fun onCreateWindow(
                                view: WebView?,
                                isDialog: Boolean,
                                isUserGesture: Boolean,
                                resultMsg: android.os.Message?
                            ): Boolean {
                                val proxyWebView = WebView(ctx)
                                proxyWebView.webViewClient = object : WebViewClient() {
                                    override fun shouldOverrideUrlLoading(v: WebView?, request: WebResourceRequest?): Boolean {
                                        val targetUrl = request?.url?.toString() ?: return false
                                        v?.destroy()
                                        this@apply.loadUrl(targetUrl)
                                        return true
                                    }

                                    @Deprecated("Deprecated in Java")
                                    override fun shouldOverrideUrlLoading(v: WebView?, targetUrl: String?): Boolean {
                                        if (targetUrl == null) return false
                                        v?.destroy()
                                        this@apply.loadUrl(targetUrl)
                                        return true
                                    }
                                }
                                val transport = resultMsg?.obj as? WebView.WebViewTransport
                                transport?.webView = proxyWebView
                                resultMsg?.sendToTarget()
                                return true
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
