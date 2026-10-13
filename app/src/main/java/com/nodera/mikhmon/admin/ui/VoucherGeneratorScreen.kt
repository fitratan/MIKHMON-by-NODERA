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
import androidx.compose.ui.draw.clip
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
    var statusMessage by remember { mutableStateOf<String?>(null) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    // Load profiles from MikroTik
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
            .background(MikhmonDarkBg)
            .statusBarsPadding()
            .padding(16.dp)
    ) {
        // Title Bar
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "Generator Voucher",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = TextPrimary
                )
                Text(
                    text = "Buat & Cetak Thermal MikroTik",
                    style = MaterialTheme.typography.bodyMedium,
                    color = TextSecondary
                )
            }

            IconButton(
                onClick = { loadProfiles() },
                enabled = !isProfilesLoading
            ) {
                if (isProfilesLoading) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), color = PrimaryBlueLight, strokeWidth = 2.dp)
                } else {
                    Icon(Icons.Default.Sync, contentDescription = "Muat Profile", tint = PrimaryBlueLight)
                }
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        if (generatedVouchers.isEmpty()) {
            // Generator Form
            val scrollState = rememberScrollState()
            Column(
                modifier = Modifier
                    .weight(1f)
                    .verticalScroll(scrollState)
            ) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = MikhmonSurface)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Profil Hotspot MikroTik",
                            color = TextSecondary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(modifier = Modifier.height(8.dp))

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
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = TextPrimary,
                                    unfocusedTextColor = TextPrimary,
                                    focusedBorderColor = PrimaryBlue,
                                    unfocusedBorderColor = MikhmonCardBorder
                                )
                            )
                            ExposedDropdownMenu(
                                expanded = expanded,
                                onDismissRequest = { expanded = false },
                                modifier = Modifier.background(MikhmonSurfaceVariant)
                            ) {
                                if (profiles.isEmpty()) {
                                    DropdownMenuItem(
                                        text = { Text("default (muat ulang jika kosong)", color = TextPrimary) },
                                        onClick = {
                                            selectedProfile = "default"
                                            expanded = false
                                        }
                                    )
                                } else {
                                    profiles.forEach { profile ->
                                        DropdownMenuItem(
                                            text = {
                                                Column {
                                                    Text(profile.name, color = TextPrimary, fontWeight = FontWeight.Bold)
                                                    if (profile.rateLimit.isNotEmpty()) {
                                                        Text("Speed: ${profile.rateLimit}", color = TextSecondary, fontSize = 11.sp)
                                                    }
                                                }
                                            },
                                            onClick = {
                                                selectedProfile = profile.name
                                                expanded = false
                                            }
                                        )
                                    }
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // Quick Qty Chips
                        Text(
                            text = "Jumlah Voucher (Qty)",
                            color = TextSecondary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(modifier = Modifier.height(6.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            listOf("1", "5", "10", "20", "50").forEach { q ->
                                FilterChip(
                                    selected = qty == q,
                                    onClick = { qty = q },
                                    label = { Text(q) }
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // User Mode Switch
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Column {
                                Text("Mode Username = Password", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                                Text("Mempermudah pembeli voucher cukup ketik 1 kode", color = TextSecondary, fontSize = 11.sp)
                            }
                            Switch(
                                checked = userSameAsPass,
                                onCheckedChange = { userSameAsPass = it },
                                colors = SwitchDefaults.colors(
                                    checkedThumbColor = Color.White,
                                    checkedTrackColor = PrimaryBlue
                                )
                            )
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // Prefix & Length
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(
                                value = prefix,
                                onValueChange = { prefix = it },
                                label = { Text("Prefix (opsional)") },
                                placeholder = { Text("cth: ndr-") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = TextPrimary,
                                    unfocusedTextColor = TextPrimary,
                                    focusedBorderColor = PrimaryBlue,
                                    unfocusedBorderColor = MikhmonCardBorder
                                )
                            )

                            OutlinedTextField(
                                value = charLength,
                                onValueChange = { charLength = it },
                                label = { Text("Pjg Karakter") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = TextPrimary,
                                    unfocusedTextColor = TextPrimary,
                                    focusedBorderColor = PrimaryBlue,
                                    unfocusedBorderColor = MikhmonCardBorder
                                )
                            )
                        }

                        Spacer(modifier = Modifier.height(14.dp))

                        // Time Limit & Price
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            OutlinedTextField(
                                value = timeLimit,
                                onValueChange = { timeLimit = it },
                                label = { Text("Limit Waktu") },
                                placeholder = { Text("cth: 3h, 1d") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = TextPrimary,
                                    unfocusedTextColor = TextPrimary,
                                    focusedBorderColor = PrimaryBlue,
                                    unfocusedBorderColor = MikhmonCardBorder
                                )
                            )

                            OutlinedTextField(
                                value = price,
                                onValueChange = { price = it },
                                label = { Text("Harga Cetak (Rp)") },
                                placeholder = { Text("cth: 3.000") },
                                singleLine = true,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedTextColor = TextPrimary,
                                    unfocusedTextColor = TextPrimary,
                                    focusedBorderColor = PrimaryBlue,
                                    unfocusedBorderColor = MikhmonCardBorder
                                )
                            )
                        }
                    }
                }

                if (errorMessage != null) {
                    Spacer(modifier = Modifier.height(10.dp))
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = DangerRedBg),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Text(
                            text = errorMessage ?: "",
                            color = TextPrimary,
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.padding(12.dp)
                        )
                    }
                }

                Spacer(modifier = Modifier.height(20.dp))

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
                                    val list = genRes.getOrDefault(emptyList())
                                    generatedVouchers = list
                                    statusMessage = "Berhasil membuat ${list.size} voucher di MikroTik!"
                                } else {
                                    errorMessage = genRes.exceptionOrNull()?.message ?: "Gagal membuat voucher"
                                }
                            } else {
                                errorMessage = connRes.exceptionOrNull()?.message ?: "Gagal terhubung ke router"
                            }
                            isGenerating = false
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(50.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = PrimaryBlue),
                    shape = RoundedCornerShape(10.dp),
                    enabled = !isGenerating
                ) {
                    if (isGenerating) {
                        CircularProgressIndicator(color = Color.White, modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Memproses ke MikroTik...", color = Color.White)
                    } else {
                        Icon(Icons.Default.Bolt, contentDescription = null, tint = Color.White)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Generate ke MikroTik Sekarang", fontWeight = FontWeight.Bold, color = Color.White)
                    }
                }
                Spacer(modifier = Modifier.height(20.dp))
            }
        } else {
            // Results list & Print Options
            Column(modifier = Modifier.weight(1f)) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = EmeraldBg)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(14.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column {
                            Text(
                                text = "Sukses! ${generatedVouchers.size} Voucher Dibuat",
                                fontWeight = FontWeight.Bold,
                                color = Color.White,
                                fontSize = 14.sp
                            )
                            Text(
                                text = "Profile: $selectedProfile • Rp $price",
                                color = TextPrimary,
                                fontSize = 12.sp
                            )
                        }

                        IconButton(onClick = { generatedVouchers = emptyList() }) {
                            Icon(Icons.Default.Close, contentDescription = "Tutup", tint = Color.White)
                        }
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                // Action Print All
                Button(
                    onClick = {
                        if (config.printerAddress.isEmpty()) {
                            Toast.makeText(context, "Pilih Bluetooth printer terlebih dahulu di tab Pengaturan", Toast.LENGTH_LONG).show()
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
                                    Toast.makeText(context, "Selesai mencetak semua voucher!", Toast.LENGTH_SHORT).show()
                                } else {
                                    Toast.makeText(context, "Gagal mencetak: ${res.exceptionOrNull()?.message}", Toast.LENGTH_LONG).show()
                                }
                            }
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = EmeraldActive),
                    shape = RoundedCornerShape(10.dp),
                    enabled = !isPrinting
                ) {
                    if (isPrinting) {
                        CircularProgressIndicator(color = Color.White, modifier = Modifier.size(18.dp), strokeWidth = 2.dp)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Sedang Mencetak...", color = Color.White)
                    } else {
                        Icon(Icons.Default.Print, contentDescription = null, tint = Color.White)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Cetak Semua (${generatedVouchers.size}) ke Thermal Printer", color = Color.White, fontWeight = FontWeight.Bold)
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                // Vouchers Lazy List
                LazyColumn(
                    modifier = Modifier.fillMaxWidth().weight(1f),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    itemsIndexed(generatedVouchers) { idx, v ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(12.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column {
                                    Text(
                                        text = "#${idx + 1} Kode: ${v.username}",
                                        style = MaterialTheme.typography.titleMedium.copy(
                                            fontFamily = FontFamily.Monospace,
                                            fontWeight = FontWeight.Bold
                                        ),
                                        color = PrimaryBlueLight
                                    )
                                    if (v.password != v.username) {
                                        Text("Pass: ${v.password}", color = TextSecondary, fontSize = 12.sp)
                                    }
                                    Text("Paket: ${v.profile} • Limit: ${v.timeLimit}", color = TextMuted, fontSize = 11.sp)
                                }

                                IconButton(
                                    onClick = {
                                        if (config.printerAddress.isEmpty()) {
                                            Toast.makeText(context, "Pilih printer di Pengaturan", Toast.LENGTH_SHORT).show()
                                        } else {
                                            coroutineScope.launch {
                                                val res = printerManager.printVouchers(
                                                    printerAddress = config.printerAddress,
                                                    hotspotName = config.hotspotName,
                                                    dnsName = config.dnsName,
                                                    vouchers = listOf(v)
                                                )
                                                if (res.isSuccess) {
                                                    Toast.makeText(context, "Voucher #${idx + 1} dicetak!", Toast.LENGTH_SHORT).show()
                                                } else {
                                                    Toast.makeText(context, "Gagal: ${res.exceptionOrNull()?.message}", Toast.LENGTH_SHORT).show()
                                                }
                                            }
                                        }
                                    }
                                ) {
                                    Icon(Icons.Default.Print, contentDescription = "Cetak", tint = EmeraldActive)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
