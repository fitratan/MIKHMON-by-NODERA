package com.nodera.mikhmon.admin.ui

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import com.nodera.mikhmon.admin.bluetooth.ThermalPrinterManager
import com.nodera.mikhmon.admin.data.RouterConfig
import com.nodera.mikhmon.admin.data.RouterConfigManager
import com.nodera.mikhmon.admin.licensing.LicenseInfo
import com.nodera.mikhmon.admin.licensing.LicenseState
import com.nodera.mikhmon.admin.router.RouterOsClient
import com.nodera.mikhmon.admin.router.RouterSystemInfo
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.launch

@Composable
fun DashboardScreen(
    config: RouterConfig,
    licenseInfo: LicenseInfo,
    onNavigateToTab: (Int) -> Unit
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val scrollState = rememberScrollState()

    var systemInfo by remember { mutableStateOf(RouterSystemInfo()) }
    var isConnected by remember { mutableStateOf(false) }
    var isRefreshing by remember { mutableStateOf(false) }
    var connectionError by remember { mutableStateOf<String?>(null) }

    val printerManager = remember { ThermalPrinterManager(context) }

    fun refreshTelemetry() {
        coroutineScope.launch {
            isRefreshing = true
            connectionError = null
            val client = RouterConfigManager.getOrCreateClient(config)
            val connRes = client.connect(config.username, config.password)
            if (connRes.isSuccess) {
                isConnected = true
                val infoRes = client.getSystemInfo()
                if (infoRes.isSuccess) {
                    systemInfo = infoRes.getOrDefault(RouterSystemInfo())
                } else {
                    connectionError = infoRes.exceptionOrNull()?.message
                }
            } else {
                isConnected = false
                connectionError = connRes.exceptionOrNull()?.message ?: "Gagal terhubung"
            }
            isRefreshing = false
        }
    }

    LaunchedEffect(Unit) {
        refreshTelemetry()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonDarkBg)
            .statusBarsPadding()
            .verticalScroll(scrollState)
            .padding(16.dp)
    ) {
        // Top App Bar row
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "MIKHMON by NODERA",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = TextPrimary
                )
                Text(
                    text = "Admin Hotspot & Vouchers",
                    style = MaterialTheme.typography.bodyMedium,
                    color = TextSecondary
                )
            }

            IconButton(
                onClick = { refreshTelemetry() },
                enabled = !isRefreshing,
                modifier = Modifier
                    .clip(CircleShape)
                    .background(MikhmonSurface)
            ) {
                if (isRefreshing) {
                    CircularProgressIndicator(modifier = Modifier.size(20.dp), color = PrimaryBlueLight, strokeWidth = 2.dp)
                } else {
                    Icon(Icons.Default.Refresh, contentDescription = "Refresh", tint = PrimaryBlueLight)
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Connection Status Banner
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(
                containerColor = if (isConnected) MikhmonSurface else MikhmonSurfaceVariant
            )
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(12.dp)
                            .clip(CircleShape)
                            .background(if (isConnected) EmeraldActive else DangerRed)
                    )
                    Spacer(modifier = Modifier.width(10.dp))
                    Column {
                        Text(
                            text = if (isConnected) "MikroTik Terhubung" else "MikroTik Terputus",
                            fontWeight = FontWeight.Bold,
                            color = TextPrimary,
                            fontSize = 14.sp
                        )
                        Text(
                            text = "${config.host}:${config.port} (${systemInfo.identity})",
                            color = TextSecondary,
                            fontSize = 12.sp
                        )
                    }
                }

                AssistChip(
                    onClick = { onNavigateToTab(3) }, // Go to settings
                    label = { Text(if (isConnected) "Kelola" else "Hubungkan", fontSize = 11.sp) },
                    leadingIcon = {
                        Icon(
                            if (isConnected) Icons.Default.CheckCircle else Icons.Default.Settings,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp)
                        )
                    }
                )
            }
        }

        if (connectionError != null) {
            Spacer(modifier = Modifier.height(10.dp))
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = DangerRedBg),
                shape = RoundedCornerShape(10.dp)
            ) {
                Text(
                    text = "Gagal konek: $connectionError",
                    color = TextPrimary,
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(12.dp)
                )
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // License Status Chip
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "STATUS SISTEM",
                style = MaterialTheme.typography.labelSmall,
                color = TextSecondary,
                fontWeight = FontWeight.Bold
            )

            Surface(
                color = when (licenseInfo.state) {
                    LicenseState.ACTIVE_LIFETIME -> EmeraldBg
                    LicenseState.ACTIVE_SUBSCRIPTION -> PrimaryBlueDark
                    LicenseState.ACTIVE_TRIAL -> WarningAmberBg
                    else -> DangerRedBg
                },
                shape = RoundedCornerShape(16.dp)
            ) {
                Text(
                    text = when (licenseInfo.state) {
                        LicenseState.ACTIVE_LIFETIME -> "LIFETIME RESMI"
                        LicenseState.ACTIVE_SUBSCRIPTION -> "${licenseInfo.remainingDays} HARI AKTIF"
                        LicenseState.ACTIVE_TRIAL -> "TRIAL ${licenseInfo.remainingDays} HARI"
                        else -> "BELUM TERLISENSI"
                    },
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                    style = MaterialTheme.typography.labelSmall,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        // Telemetry Grid
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            // CPU Card
            Card(
                modifier = Modifier.weight(1f),
                colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("CPU Load", color = TextSecondary, fontSize = 12.sp)
                        Icon(Icons.Default.Memory, contentDescription = null, tint = PrimaryBlueLight, modifier = Modifier.size(18.dp))
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("${systemInfo.cpuLoad}%", color = TextPrimary, fontSize = 22.sp, fontWeight = FontWeight.Bold)
                    Spacer(modifier = Modifier.height(6.dp))
                    LinearProgressIndicator(
                        progress = { (systemInfo.cpuLoad / 100f).coerceIn(0f, 1f) },
                        modifier = Modifier.fillMaxWidth().height(4.dp).clip(RoundedCornerShape(2.dp)),
                        color = if (systemInfo.cpuLoad > 80) DangerRed else PrimaryBlueLight,
                        trackColor = MikhmonSurfaceVariant
                    )
                }
            }

            // RAM Card
            Card(
                modifier = Modifier.weight(1f),
                colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Free RAM", color = TextSecondary, fontSize = 12.sp)
                        Icon(Icons.Default.Speed, contentDescription = null, tint = EmeraldActive, modifier = Modifier.size(18.dp))
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("${systemInfo.freeMemoryMb} MB", color = TextPrimary, fontSize = 22.sp, fontWeight = FontWeight.Bold)
                    Spacer(modifier = Modifier.height(6.dp))
                    Text(
                        text = "Total: ${systemInfo.totalMemoryMb} MB",
                        color = TextMuted,
                        fontSize = 11.sp
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(10.dp))

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            // Active Users Card
            Card(
                modifier = Modifier.weight(1f),
                colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
                shape = RoundedCornerShape(12.dp),
                onClick = { onNavigateToTab(2) } // Navigate to Active Users
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("User Aktif", color = TextSecondary, fontSize = 12.sp)
                        Icon(Icons.Default.Group, contentDescription = null, tint = WarningAmber, modifier = Modifier.size(18.dp))
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("${systemInfo.activeUsersCount}", color = TextPrimary, fontSize = 22.sp, fontWeight = FontWeight.Bold)
                    Spacer(modifier = Modifier.height(6.dp))
                    Text("Klik untuk melihat list", color = PrimaryBlueLight, fontSize = 11.sp)
                }
            }

            // Uptime Card
            Card(
                modifier = Modifier.weight(1f),
                colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("Uptime", color = TextSecondary, fontSize = 12.sp)
                        Icon(Icons.Default.Timer, contentDescription = null, tint = PrimaryBlue, modifier = Modifier.size(18.dp))
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(systemInfo.uptime, color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                    Spacer(modifier = Modifier.height(6.dp))
                    Text("ROS v${systemInfo.version}", color = TextMuted, fontSize = 11.sp, maxLines = 1)
                }
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Quick Actions Header
        Text(
            text = "MENU CEPAT ADMIN",
            style = MaterialTheme.typography.labelSmall,
            color = TextSecondary,
            fontWeight = FontWeight.Bold
        )

        Spacer(modifier = Modifier.height(12.dp))

        // Button: Generate Voucher
        Button(
            onClick = { onNavigateToTab(1) },
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = PrimaryBlue),
            shape = RoundedCornerShape(12.dp)
        ) {
            Icon(Icons.Default.ConfirmationNumber, contentDescription = null, tint = Color.White)
            Spacer(modifier = Modifier.width(10.dp))
            Text("Generate & Cetak Voucher Baru", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Color.White)
        }

        Spacer(modifier = Modifier.height(10.dp))

        // Button: Active Users
        OutlinedButton(
            onClick = { onNavigateToTab(2) },
            modifier = Modifier.fillMaxWidth().height(50.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = TextPrimary)
        ) {
            Icon(Icons.Default.PeopleAlt, contentDescription = null, modifier = Modifier.size(18.dp), tint = PrimaryBlueLight)
            Spacer(modifier = Modifier.width(10.dp))
            Text("Daftar Pengguna Aktif Hotspot", fontSize = 14.sp)
        }

        Spacer(modifier = Modifier.height(10.dp))

        // Button: Test Print
        OutlinedButton(
            onClick = {
                if (config.printerAddress.isEmpty()) {
                    Toast.makeText(context, "Printer belum dipilih! Buka tab Pengaturan untuk pilih printer Bluetooth.", Toast.LENGTH_LONG).show()
                } else {
                    coroutineScope.launch {
                        Toast.makeText(context, "Mencetak voucher sample ke ${config.printerName}...", Toast.LENGTH_SHORT).show()
                        val res = printerManager.testPrint(config.printerAddress, config.hotspotName)
                        if (res.isSuccess) {
                            Toast.makeText(context, "Cetak sample berhasil!", Toast.LENGTH_SHORT).show()
                        } else {
                            Toast.makeText(context, "Gagal mencetak: ${res.exceptionOrNull()?.message}", Toast.LENGTH_LONG).show()
                        }
                    }
                }
            },
            modifier = Modifier.fillMaxWidth().height(50.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = TextPrimary)
        ) {
            Icon(Icons.Default.Print, contentDescription = null, modifier = Modifier.size(18.dp), tint = EmeraldActive)
            Spacer(modifier = Modifier.width(10.dp))
            Text(
                text = if (config.printerName.isNotEmpty()) "Tes Cetak: ${config.printerName}" else "Tes Cetak Thermal Printer",
                fontSize = 14.sp
            )
        }

        Spacer(modifier = Modifier.height(30.dp))
    }
}
