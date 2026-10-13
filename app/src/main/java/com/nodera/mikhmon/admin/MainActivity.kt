package com.nodera.mikhmon.admin

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Surface
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.core.content.ContextCompat
import com.nodera.mikhmon.admin.licensing.LicenseManager
import com.nodera.mikhmon.admin.licensing.LicenseState
import com.nodera.mikhmon.admin.theme.MikhmonBg
import com.nodera.mikhmon.admin.theme.MikhmonTheme
import com.nodera.mikhmon.admin.ui.LicenseGateScreen

class MainActivity : ComponentActivity() {

    private lateinit var licenseManager: LicenseManager

    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { _ ->
        // Permissions handled
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        licenseManager = LicenseManager(this)

        requestBluetoothPermissions()

        setContent {
            MikhmonTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MikhmonBg
                ) {
                    var licenseInfo by remember { mutableStateOf(licenseManager.checkLicense()) }
                    var forceShowLicenseGate by remember { mutableStateOf(false) }

                    val isAuthorized = when (licenseInfo.state) {
                        LicenseState.ACTIVE_LIFETIME,
                        LicenseState.ACTIVE_SUBSCRIPTION,
                        LicenseState.ACTIVE_TRIAL -> true
                        else -> false
                    }

                    if (isAuthorized && !forceShowLicenseGate) {
                        val config = remember { com.nodera.mikhmon.admin.data.RouterConfigManager(this@MainActivity).loadConfig() }
                        com.nodera.mikhmon.admin.ui.MainContainer(
                            initialConfig = config,
                            licenseInfo = licenseInfo
                        )
                    } else {
                        // Hardware-Locked License Gatekeeper Screen
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

    override fun onDestroy() {
        super.onDestroy()
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
