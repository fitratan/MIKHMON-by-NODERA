package com.nodera.mikhmon.admin.ui

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import com.nodera.mikhmon.admin.bluetooth.ThermalPrinterManager
import com.nodera.mikhmon.admin.data.RouterConfig
import com.nodera.mikhmon.admin.data.RouterConfigManager
import com.nodera.mikhmon.admin.licensing.LicenseInfo
import com.nodera.mikhmon.admin.licensing.LicenseState
import com.nodera.mikhmon.admin.router.HotspotProfile
import com.nodera.mikhmon.admin.router.RouterSystemInfo
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
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

    // Quick print state
    var profiles by remember { mutableStateOf<List<HotspotProfile>>(emptyList()) }
    var quickProfile by remember { mutableStateOf("default") }
    var quickQty by remember { mutableStateOf("1") }
    var quickTimeLimit by remember { mutableStateOf("3h") }
    var quickPrice by remember { mutableStateOf("3.000") }
    var isQuickPrinting by remember { mutableStateOf(false) }

    val printerManager = remember { ThermalPrinterManager(context) }

    val currentDate = remember {
        SimpleDateFormat("dd-MMM-yyyy", Locale.US).format(Date())
    }
    val currentTime = remember {
        SimpleDateFormat("HH:mm:ss", Locale.US).format(Date())
    }

    fun loadData() {
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

                // Load profiles for Quick Print
                val profRes = client.getHotspotProfiles()
                if (profRes.isSuccess) {
                    val list = profRes.getOrDefault(emptyList())
                    profiles = list
                    if (list.isNotEmpty() && quickProfile == "default") {
                        quickProfile = list.first().name
                    }
                }
            } else {
                isConnected = false
                connectionError = connRes.exceptionOrNull()?.message ?: "Gagal terhubung ke router"
            }
            isRefreshing = false
        }
    }

    LaunchedEffect(Unit) {
        loadData()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg)
    ) {
        // Top Authentic MIKHMON Navbar
        MikhmonNavbar(
            sessionName = if (systemInfo.identity.isNotEmpty()) systemInfo.identity else config.hotspotName,
            routerHost = "${config.host}:${config.port}",
            isConnected = isConnected,
            onRefreshClick = { loadData() },
            isRefreshing = isRefreshing,
            printerName = config.printerName,
            onPrinterClick = { onNavigateToTab(3) }
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scrollState)
                .padding(12.dp)
        ) {
            // License Ribbon Banner
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 10.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "DASHBOARD HOTSPOT",
                    color = MikhmonTextMuted,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 0.5.sp
                )

                Surface(
                    color = when (licenseInfo.state) {
                        LicenseState.ACTIVE_LIFETIME -> MikhmonGreenDark
                        LicenseState.ACTIVE_SUBSCRIPTION -> MikhmonBlueDark
                        LicenseState.ACTIVE_TRIAL -> MikhmonYellowDark
                        else -> MikhmonRedDark
                    },
                    shape = RoundedCornerShape(3.dp)
                ) {
                    Text(
                        text = when (licenseInfo.state) {
                            LicenseState.ACTIVE_LIFETIME -> "LIFETIME AKTIF"
                            LicenseState.ACTIVE_SUBSCRIPTION -> "${licenseInfo.remainingDays} HARI AKTIF"
                            LicenseState.ACTIVE_TRIAL -> "TRIAL ${licenseInfo.remainingDays} HARI"
                            else -> "BELUM TERLISENSI"
                        },
                        color = Color.White,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                    )
                }
            }

            if (connectionError != null) {
                Surface(
                    color = MikhmonRedDark,
                    shape = RoundedCornerShape(4.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 10.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.ErrorOutline, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Koneksi Offline Terputus: $connectionError",
                            color = Color.White,
                            fontSize = 12.sp
                        )
                    }
                }
            }

            // Authentic Mikhmon 3 Info Boxes
            Column(
                modifier = Modifier.fillMaxWidth(),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Box 1: Clock / Uptime
                MikhmonInfoBox(
                    icon = Icons.Default.Schedule,
                    lines = listOf(
                        "Jam" to currentTime,
                        "Tanggal" to currentDate,
                        "Uptime" to systemInfo.uptime
                    ),
                    modifier = Modifier.fillMaxWidth()
                )

                // Box 2: Board Info
                MikhmonInfoBox(
                    icon = Icons.Default.Info,
                    lines = listOf(
                        "Model" to systemInfo.model,
                        "Versi ROS" to "v${systemInfo.version}",
                        "Router IP" to "${config.host}:${config.port}"
                    ),
                    modifier = Modifier.fillMaxWidth()
                )

                // Box 3: Resource Info
                MikhmonInfoBox(
                    icon = Icons.Default.Memory,
                    lines = listOf(
                        "CPU Load" to "${systemInfo.cpuLoad}%",
                        "Free RAM" to "${systemInfo.freeMemoryMb} MB",
                        "Total RAM" to "${systemInfo.totalMemoryMb} MB"
                    ),
                    modifier = Modifier.fillMaxWidth()
                )
            }

            Spacer(modifier = Modifier.height(14.dp))

            // Card: Hotspot Summary with 4 Action Boxes
            MikhmonCard(
                title = "Hotspot Overview",
                icon = Icons.Default.Wifi
            ) {
                // 4 Famous Action Boxes (2x2 Grid)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Box Biru: Hotspot Active
                    MikhmonActionBox(
                        countOrText = "${systemInfo.activeUsersCount}",
                        unitText = "items",
                        label = "Hotspot Active",
                        icon = Icons.Default.Laptop,
                        bgColor = MikhmonBlue,
                        onClick = { onNavigateToTab(2) },
                        modifier = Modifier.weight(1f)
                    )

                    // Box Hijau: Hotspot Users
                    MikhmonActionBox(
                        countOrText = "${systemInfo.activeUsersCount}",
                        unitText = "items",
                        label = "Hotspot Users",
                        icon = Icons.Default.People,
                        bgColor = MikhmonGreen,
                        onClick = { onNavigateToTab(2) },
                        modifier = Modifier.weight(1f)
                    )
                }

                Spacer(modifier = Modifier.height(8.dp))

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Box Kuning: User Profile
                    MikhmonActionBox(
                        countOrText = "${profiles.size}",
                        unitText = "profil",
                        label = "User Profile",
                        icon = Icons.Default.PersonAdd,
                        bgColor = MikhmonYellow,
                        onClick = { onNavigateToTab(1) },
                        modifier = Modifier.weight(1f)
                    )

                    // Box Merah: Generate Vouchers
                    MikhmonActionBox(
                        countOrText = "GEN",
                        unitText = "voucher",
                        label = "Generate",
                        icon = Icons.Default.ConfirmationNumber,
                        bgColor = MikhmonRed,
                        onClick = { onNavigateToTab(1) },
                        modifier = Modifier.weight(1f)
                    )
                }
            }

            Spacer(modifier = Modifier.height(14.dp))

            // Card: Quick Print (Identik Mikhmon Quick Print Box)
            MikhmonCard(
                title = "Quick Print",
                icon = Icons.Default.Print
            ) {
                Text(
                    text = "Cetak cepat voucher hotspot langsung ke Bluetooth Thermal Printer:",
                    color = MikhmonTextMuted,
                    fontSize = 12.sp
                )

                Spacer(modifier = Modifier.height(10.dp))

                // Profile Selector
                Text("Profil Paket", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(4.dp))

                var profileExpanded by remember { mutableStateOf(false) }
                ExposedDropdownMenuBox(
                    expanded = profileExpanded,
                    onExpandedChange = { profileExpanded = !profileExpanded }
                ) {
                    OutlinedTextField(
                        value = quickProfile,
                        onValueChange = {},
                        readOnly = true,
                        trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = profileExpanded) },
                        modifier = Modifier
                            .menuAnchor()
                            .fillMaxWidth(),
                        shape = RoundedCornerShape(4.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = MikhmonTextPrimary,
                            unfocusedTextColor = MikhmonTextPrimary,
                            focusedBorderColor = MikhmonBlue,
                            unfocusedBorderColor = MikhmonBorder,
                            focusedContainerColor = MikhmonBg,
                            unfocusedContainerColor = MikhmonBg
                        )
                    )

                    ExposedDropdownMenu(
                        expanded = profileExpanded,
                        onDismissRequest = { profileExpanded = false },
                        modifier = Modifier.background(MikhmonCardBg)
                    ) {
                        if (profiles.isEmpty()) {
                            DropdownMenuItem(
                                text = { Text("default", color = MikhmonTextPrimary) },
                                onClick = { quickProfile = "default"; profileExpanded = false }
                            )
                        } else {
                            profiles.forEach { p ->
                                DropdownMenuItem(
                                    text = { Text(p.name, color = MikhmonTextPrimary) },
                                    onClick = { quickProfile = p.name; profileExpanded = false }
                                )
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Qty & Price
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Column(modifier = Modifier.weight(1f)) {
                        Text("Jumlah (Qty)", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        Spacer(modifier = Modifier.height(4.dp))
                        OutlinedTextField(
                            value = quickQty,
                            onValueChange = { quickQty = it },
                            singleLine = true,
                            shape = RoundedCornerShape(4.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = MikhmonTextPrimary,
                                unfocusedTextColor = MikhmonTextPrimary,
                                focusedBorderColor = MikhmonBlue,
                                unfocusedBorderColor = MikhmonBorder,
                                focusedContainerColor = MikhmonBg,
                                unfocusedContainerColor = MikhmonBg
                            )
                        )
                    }

                    Column(modifier = Modifier.weight(1f)) {
                        Text("Harga (Rp)", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        Spacer(modifier = Modifier.height(4.dp))
                        OutlinedTextField(
                            value = quickPrice,
                            onValueChange = { quickPrice = it },
                            singleLine = true,
                            shape = RoundedCornerShape(4.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = MikhmonTextPrimary,
                                unfocusedTextColor = MikhmonTextPrimary,
                                focusedBorderColor = MikhmonBlue,
                                unfocusedBorderColor = MikhmonBorder,
                                focusedContainerColor = MikhmonBg,
                                unfocusedContainerColor = MikhmonBg
                            )
                        )
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Action Generate & Print Button
                Button(
                    onClick = {
                        val count = quickQty.toIntOrNull() ?: 1
                        coroutineScope.launch {
                            isQuickPrinting = true
                            val client = RouterConfigManager.getOrCreateClient(config)
                            val connRes = client.connect(config.username, config.password)
                            if (connRes.isSuccess) {
                                val genRes = client.generateVouchers(
                                    qty = count,
                                    profile = quickProfile,
                                    prefix = "",
                                    userSameAsPass = true,
                                    charLength = 4,
                                    timeLimit = quickTimeLimit,
                                    price = quickPrice
                                )
                                if (genRes.isSuccess) {
                                    val vouchers = genRes.getOrDefault(emptyList())
                                    if (config.printerAddress.isNotEmpty()) {
                                        printerManager.printVouchers(
                                            printerAddress = config.printerAddress,
                                            hotspotName = config.hotspotName,
                                            dnsName = config.dnsName,
                                            vouchers = vouchers
                                        )
                                        Toast.makeText(context, "${vouchers.size} Voucher dicetak ke printer!", Toast.LENGTH_SHORT).show()
                                    } else {
                                        Toast.makeText(context, "${vouchers.size} Voucher dibuat di MikroTik! (Pilih printer di Pengaturan untuk cetak)", Toast.LENGTH_LONG).show()
                                    }
                                } else {
                                    Toast.makeText(context, "Gagal generate: ${genRes.exceptionOrNull()?.message}", Toast.LENGTH_LONG).show()
                                }
                            } else {
                                Toast.makeText(context, "Gagal konek router: ${connRes.exceptionOrNull()?.message}", Toast.LENGTH_LONG).show()
                            }
                            isQuickPrinting = false
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(44.dp),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = MikhmonGreen),
                    enabled = !isQuickPrinting
                ) {
                    if (isQuickPrinting) {
                        CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Memproses & Mencetak...", color = Color.White, fontSize = 13.sp)
                    } else {
                        Icon(Icons.Default.Print, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Generate & Cetak Struk Cepat", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                    }
                }
            }

            Spacer(modifier = Modifier.height(30.dp))
        }
    }
}
