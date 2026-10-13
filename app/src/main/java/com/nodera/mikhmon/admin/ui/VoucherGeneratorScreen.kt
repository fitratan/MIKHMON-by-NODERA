package com.nodera.mikhmon.admin.ui

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
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
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nodera.mikhmon.admin.bluetooth.ThermalPrinterManager
import com.nodera.mikhmon.admin.data.RouterConfig
import com.nodera.mikhmon.admin.data.RouterConfigManager
import com.nodera.mikhmon.admin.router.GeneratedVoucher
import com.nodera.mikhmon.admin.router.HotspotProfile
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun VoucherGeneratorScreen(
    config: RouterConfig
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val printerManager = remember { ThermalPrinterManager(context) }

    var profiles by remember { mutableStateOf<List<HotspotProfile>>(emptyList()) }
    var selectedProfile by remember { mutableStateOf("default") }
    var isProfilesLoading by remember { mutableStateOf(false) }

    var qty by remember { mutableStateOf("5") }
    var userSameAsPass by remember { mutableStateOf(true) }
    var prefix by remember { mutableStateOf("") }
    var charLength by remember { mutableStateOf("4") }
    var timeLimit by remember { mutableStateOf("3h") }
    var price by remember { mutableStateOf("3.000") }

    var isGenerating by remember { mutableStateOf(false) }
    var isPrinting by remember { mutableStateOf(false) }
    var generatedVouchers by remember { mutableStateOf<List<GeneratedVoucher>>(emptyList()) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    fun loadProfiles() {
        coroutineScope.launch {
            isProfilesLoading = true
            val client = RouterConfigManager.getOrCreateClient(config)
            val conn = client.connect(config.username, config.password)
            if (conn.isSuccess) {
                val res = client.getHotspotProfiles()
                if (res.isSuccess) {
                    val list = res.getOrDefault(emptyList())
                    profiles = list
                    if (list.isNotEmpty()) {
                        selectedProfile = list.first().name
                    }
                }
            }
            isProfilesLoading = false
        }
    }

    LaunchedEffect(Unit) {
        loadProfiles()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg)
    ) {
        // Top Header
        MikhmonNavbar(
            sessionName = "Hotspot > Generate",
            routerHost = config.host,
            isConnected = true,
            onRefreshClick = { loadProfiles() },
            isRefreshing = isProfilesLoading,
            printerName = config.printerName
        )

        if (generatedVouchers.isEmpty()) {
            val scrollState = rememberScrollState()
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(scrollState)
                    .padding(12.dp)
            ) {
                MikhmonCard(
                    title = "Generate User Hotspot",
                    icon = Icons.Default.ConfirmationNumber,
                    headerRightContent = {
                        Text(
                            text = "Offline Mode",
                            color = MikhmonGreen,
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                ) {
                    // Qty Field
                    Text("Qty (Jumlah Voucher)", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    Spacer(modifier = Modifier.height(4.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        listOf("1", "5", "10", "20", "50").forEach { q ->
                            FilterChip(
                                selected = qty == q,
                                onClick = { qty = q },
                                label = { Text(q, fontSize = 12.sp) },
                                colors = FilterChipDefaults.filterChipColors(
                                    selectedContainerColor = MikhmonBlue,
                                    selectedLabelColor = Color.White,
                                    containerColor = MikhmonBg,
                                    labelColor = MikhmonTextPrimary
                                ),
                                shape = RoundedCornerShape(4.dp)
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    // Profile Field
                    Text("User Profile", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    Spacer(modifier = Modifier.height(4.dp))

                    var expanded by remember { mutableStateOf(false) }
                    ExposedDropdownMenuBox(
                        expanded = expanded,
                        onExpandedChange = { expanded = !expanded }
                    ) {
                        OutlinedTextField(
                            value = selectedProfile,
                            onValueChange = {},
                            readOnly = true,
                            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = expanded) },
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
                            expanded = expanded,
                            onDismissRequest = { expanded = false },
                            modifier = Modifier.background(MikhmonCardBg)
                        ) {
                            profiles.forEach { p ->
                                DropdownMenuItem(
                                    text = { Text(p.name, color = MikhmonTextPrimary) },
                                    onClick = { selectedProfile = p.name; expanded = false }
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    // User Mode Checkbox (Username = Password)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Checkbox(
                            checked = userSameAsPass,
                            onCheckedChange = { userSameAsPass = it },
                            colors = CheckboxDefaults.colors(
                                checkedColor = MikhmonBlue,
                                checkmarkColor = Color.White
                            )
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "Mode Username = Password",
                            color = MikhmonTextPrimary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    // Prefix & Length
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text("Prefix", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            Spacer(modifier = Modifier.height(4.dp))
                            OutlinedTextField(
                                value = prefix,
                                onValueChange = { prefix = it },
                                placeholder = { Text("cth: ndr-", color = MikhmonTextMuted, fontSize = 12.sp) },
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
                            Text("Panjang Karakter", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            Spacer(modifier = Modifier.height(4.dp))
                            OutlinedTextField(
                                value = charLength,
                                onValueChange = { charLength = it },
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

                    Spacer(modifier = Modifier.height(10.dp))

                    // Time Limit & Price
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text("Limit Waktu", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            Spacer(modifier = Modifier.height(4.dp))
                            OutlinedTextField(
                                value = timeLimit,
                                onValueChange = { timeLimit = it },
                                placeholder = { Text("cth: 3h, 1d", color = MikhmonTextMuted, fontSize = 12.sp) },
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
                            Text("Harga Cetak (Rp)", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                            Spacer(modifier = Modifier.height(4.dp))
                            OutlinedTextField(
                                value = price,
                                onValueChange = { price = it },
                                placeholder = { Text("cth: 3.000", color = MikhmonTextMuted, fontSize = 12.sp) },
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

                    if (errorMessage != null) {
                        Spacer(modifier = Modifier.height(10.dp))
                        Text(
                            text = errorMessage ?: "",
                            color = MikhmonRed,
                            fontSize = 12.sp
                        )
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    // Generate Button (Merah Khas Mikhmon)
                    Button(
                        onClick = {
                            val count = qty.toIntOrNull() ?: 1
                            val len = charLength.toIntOrNull() ?: 4
                            coroutineScope.launch {
                                isGenerating = true
                                errorMessage = null
                                val client = RouterConfigManager.getOrCreateClient(config)
                                val connRes = client.connect(config.username, config.password)
                                if (connRes.isSuccess) {
                                    val genRes = client.generateVouchers(
                                        qty = count,
                                        profile = selectedProfile,
                                        prefix = prefix.trim(),
                                        userSameAsPass = userSameAsPass,
                                        charLength = len,
                                        timeLimit = timeLimit.trim(),
                                        price = price.trim()
                                    )
                                    if (genRes.isSuccess) {
                                        generatedVouchers = genRes.getOrDefault(emptyList())
                                    } else {
                                        errorMessage = genRes.exceptionOrNull()?.message ?: "Gagal membuat voucher"
                                    }
                                } else {
                                    errorMessage = connRes.exceptionOrNull()?.message ?: "Gagal konek router"
                                }
                                isGenerating = false
                            }
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(44.dp),
                        shape = RoundedCornerShape(4.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = MikhmonRed),
                        enabled = !isGenerating
                    ) {
                        if (isGenerating) {
                            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Memproses ke MikroTik...", color = Color.White, fontSize = 13.sp)
                        } else {
                            Icon(Icons.Default.Bolt, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Generate Vouchers Sekarang", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                        }
                    }
                }

                Spacer(modifier = Modifier.height(30.dp))
            }
        } else {
            // Result View with Batch Print
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(12.dp)
            ) {
                // Header card
                Surface(
                    color = MikhmonGreenDark,
                    shape = RoundedCornerShape(4.dp),
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(10.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column {
                            Text(
                                text = "Sukses! ${generatedVouchers.size} Voucher Dibuat",
                                color = Color.White,
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.sp
                            )
                            Text(
                                text = "Profil: $selectedProfile • Rp $price",
                                color = Color.White.copy(alpha = 0.85f),
                                fontSize = 11.sp
                            )
                        }

                        IconButton(onClick = { generatedVouchers = emptyList() }) {
                            Icon(Icons.Default.Close, contentDescription = "Tutup", tint = Color.White)
                        }
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Batch Print Button
                Button(
                    onClick = {
                        if (config.printerAddress.isEmpty()) {
                            Toast.makeText(context, "Pilih printer Bluetooth di menu Pengaturan terlebih dahulu", Toast.LENGTH_LONG).show()
                        } else {
                            coroutineScope.launch {
                                isPrinting = true
                                val res = printerManager.printVouchers(
                                    printerAddress = config.printerAddress,
                                    hotspotName = config.hotspotName,
                                    dnsName = config.dnsName,
                                    vouchers = generatedVouchers
                                )
                                isPrinting = false
                                if (res.isSuccess) {
                                    Toast.makeText(context, "Semua voucher selesai dicetak!", Toast.LENGTH_SHORT).show()
                                } else {
                                    Toast.makeText(context, "Gagal mencetak: ${res.exceptionOrNull()?.message}", Toast.LENGTH_LONG).show()
                                }
                            }
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(44.dp),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = MikhmonGreen),
                    enabled = !isPrinting
                ) {
                    if (isPrinting) {
                        CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Mencetak...", color = Color.White)
                    } else {
                        Icon(Icons.Default.Print, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Cetak Semua (${generatedVouchers.size}) ke Thermal Printer", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                // List of Vouchers
                LazyColumn(
                    modifier = Modifier.fillMaxWidth().weight(1f),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    itemsIndexed(generatedVouchers) { index, v ->
                        Surface(
                            color = MikhmonCardBg,
                            shape = RoundedCornerShape(4.dp),
                            border = androidx.compose.foundation.BorderStroke(1.dp, MikhmonBorder),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(10.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(
                                        text = "#${index + 1} Kode: ${v.username}",
                                        color = MikhmonBlue,
                                        fontWeight = FontWeight.Bold,
                                        fontFamily = FontFamily.Monospace,
                                        fontSize = 14.sp
                                    )
                                    if (v.password != v.username) {
                                        Text("Pass: ${v.password}", color = MikhmonTextSecondary, fontSize = 11.sp)
                                    }
                                    Text("Paket: ${v.profile} • Limit: ${v.timeLimit}", color = MikhmonTextMuted, fontSize = 10.sp)
                                }

                                IconButton(
                                    onClick = {
                                        if (config.printerAddress.isEmpty()) {
                                            Toast.makeText(context, "Pilih printer di Pengaturan", Toast.LENGTH_SHORT).show()
                                        } else {
                                            coroutineScope.launch {
                                                printerManager.printVouchers(
                                                    printerAddress = config.printerAddress,
                                                    hotspotName = config.hotspotName,
                                                    dnsName = config.dnsName,
                                                    vouchers = listOf(v)
                                                )
                                                Toast.makeText(context, "Voucher #${index + 1} dicetak!", Toast.LENGTH_SHORT).show()
                                            }
                                        }
                                    }
                                ) {
                                    Icon(Icons.Default.Print, contentDescription = "Cetak", tint = MikhmonGreen, modifier = Modifier.size(20.dp))
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
