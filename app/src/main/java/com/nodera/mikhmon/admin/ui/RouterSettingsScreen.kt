package com.nodera.mikhmon.admin.ui

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
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
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nodera.mikhmon.admin.bluetooth.PairedPrinter
import com.nodera.mikhmon.admin.bluetooth.ThermalPrinterManager
import com.nodera.mikhmon.admin.data.RouterConfig
import com.nodera.mikhmon.admin.data.RouterConfigManager
import com.nodera.mikhmon.admin.licensing.LicenseInfo
import com.nodera.mikhmon.admin.router.RouterOsClient
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RouterSettingsScreen(
    currentConfig: RouterConfig,
    licenseInfo: LicenseInfo,
    onConfigSaved: (RouterConfig) -> Unit
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val scrollState = rememberScrollState()

    var host by remember { mutableStateOf(currentConfig.host) }
    var port by remember { mutableStateOf(currentConfig.port.toString()) }
    var username by remember { mutableStateOf(currentConfig.username) }
    var password by remember { mutableStateOf(currentConfig.password) }
    var showPassword by remember { mutableStateOf(false) }
    var hotspotName by remember { mutableStateOf(currentConfig.hotspotName) }
    var dnsName by remember { mutableStateOf(currentConfig.dnsName) }

    var selectedPrinterAddress by remember { mutableStateOf(currentConfig.printerAddress) }
    var selectedPrinterName by remember { mutableStateOf(currentConfig.printerName) }

    val printerManager = remember { ThermalPrinterManager(context) }
    var pairedPrinters by remember { mutableStateOf<List<PairedPrinter>>(emptyList()) }
    var isTestingConnection by remember { mutableStateOf(false) }
    var isTestingPrinter by remember { mutableStateOf(false) }

    fun scanPrinters() {
        pairedPrinters = printerManager.getPairedPrinters()
        if (pairedPrinters.isEmpty()) {
            Toast.makeText(context, "Tidak ada printer Bluetooth yang terpasang di HP ini. Pasangkan terlebih dahulu di Bluetooth Android.", Toast.LENGTH_LONG).show()
        }
    }

    LaunchedEffect(Unit) {
        scanPrinters()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonDarkBg)
            .statusBarsPadding()
            .verticalScroll(scrollState)
            .padding(16.dp)
    ) {
        // Header
        Text(
            text = "Pengaturan Router & Printer",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = TextPrimary
        )
        Text(
            text = "Koneksi MikroTik Port 8728 & ESC/POS Bluetooth",
            style = MaterialTheme.typography.bodyMedium,
            color = TextSecondary
        )

        Spacer(modifier = Modifier.height(16.dp))

        // Card 1: MikroTik Connection
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = MikhmonSurface)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Router, contentDescription = null, tint = PrimaryBlueLight, modifier = Modifier.size(20.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Parameter MikroTik RouterOS", fontWeight = FontWeight.Bold, color = TextPrimary, fontSize = 15.sp)
                }

                Spacer(modifier = Modifier.height(14.dp))

                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = host,
                        onValueChange = { host = it },
                        label = { Text("IP / Host") },
                        singleLine = true,
                        modifier = Modifier.weight(2f),
                        shape = RoundedCornerShape(8.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = TextPrimary,
                            unfocusedTextColor = TextPrimary,
                            focusedBorderColor = PrimaryBlue,
                            unfocusedBorderColor = MikhmonCardBorder
                        )
                    )

                    OutlinedTextField(
                        value = port,
                        onValueChange = { port = it },
                        label = { Text("Port API") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
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

                Spacer(modifier = Modifier.height(10.dp))

                OutlinedTextField(
                    value = username,
                    onValueChange = { username = it },
                    label = { Text("Username API") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary,
                        focusedBorderColor = PrimaryBlue,
                        unfocusedBorderColor = MikhmonCardBorder
                    )
                )

                Spacer(modifier = Modifier.height(10.dp))

                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text("Password API") },
                    visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                    singleLine = true,
                    trailingIcon = {
                        IconButton(onClick = { showPassword = !showPassword }) {
                            Icon(
                                if (showPassword) Icons.Default.Visibility else Icons.Default.VisibilityOff,
                                contentDescription = null,
                                tint = TextSecondary
                            )
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary,
                        focusedBorderColor = PrimaryBlue,
                        unfocusedBorderColor = MikhmonCardBorder
                    )
                )

                Spacer(modifier = Modifier.height(10.dp))

                OutlinedTextField(
                    value = hotspotName,
                    onValueChange = { hotspotName = it },
                    label = { Text("Nama Hotspot (Judul Struk)") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary,
                        focusedBorderColor = PrimaryBlue,
                        unfocusedBorderColor = MikhmonCardBorder
                    )
                )

                Spacer(modifier = Modifier.height(10.dp))

                OutlinedTextField(
                    value = dnsName,
                    onValueChange = { dnsName = it },
                    label = { Text("DNS Name Login (cth: hotspot.local)") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary,
                        focusedBorderColor = PrimaryBlue,
                        unfocusedBorderColor = MikhmonCardBorder
                    )
                )

                Spacer(modifier = Modifier.height(14.dp))

                // Test Connection Button
                OutlinedButton(
                    onClick = {
                        val p = port.toIntOrNull() ?: 8728
                        coroutineScope.launch {
                            isTestingConnection = true
                            val testClient = RouterOsClient(host.trim(), p)
                            val res = testClient.connect(username.trim(), password)
                            isTestingConnection = false
                            if (res.isSuccess) {
                                val info = testClient.getSystemInfo().getOrNull()
                                testClient.close()
                                Toast.makeText(
                                    context,
                                    "Koneksi Berhasil!\nRouter: ${info?.identity ?: "MikroTik"} (${info?.model})\nROS: v${info?.version}",
                                    Toast.LENGTH_LONG
                                ).show()
                            } else {
                                testClient.close()
                                Toast.makeText(
                                    context,
                                    "Koneksi Gagal: ${res.exceptionOrNull()?.message}",
                                    Toast.LENGTH_LONG
                                ).show()
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    enabled = !isTestingConnection
                ) {
                    if (isTestingConnection) {
                        CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Menguji Socket Port 8728...")
                    } else {
                        Icon(Icons.Default.NetworkCheck, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Uji Koneksi MikroTik")
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Card 2: Thermal Printer Setup
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = MikhmonSurface)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Print, contentDescription = null, tint = EmeraldActive, modifier = Modifier.size(20.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Thermal Printer Bluetooth", fontWeight = FontWeight.Bold, color = TextPrimary, fontSize = 15.sp)
                    }

                    IconButton(onClick = { scanPrinters() }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Scan", tint = PrimaryBlueLight)
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                Text(
                    text = "Pilih Printer yang sudah di-pairing di HP:",
                    color = TextSecondary,
                    fontSize = 12.sp
                )

                Spacer(modifier = Modifier.height(8.dp))

                var printerDropdownExpanded by remember { mutableStateOf(false) }
                ExposedDropdownMenuBox(
                    expanded = printerDropdownExpanded,
                    onExpandedChange = { printerDropdownExpanded = !printerDropdownExpanded }
                ) {
                    OutlinedTextField(
                        value = if (selectedPrinterName.isNotEmpty()) "$selectedPrinterName ($selectedPrinterAddress)" else "Pilih Bluetooth Printer...",
                        onValueChange = {},
                        readOnly = true,
                        trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = printerDropdownExpanded) },
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
                        expanded = printerDropdownExpanded,
                        onDismissRequest = { printerDropdownExpanded = false },
                        modifier = Modifier.background(MikhmonSurfaceVariant)
                    ) {
                        if (pairedPrinters.isEmpty()) {
                            DropdownMenuItem(
                                text = { Text("Belum ada printer Bluetooth terpasang", color = TextSecondary) },
                                onClick = { printerDropdownExpanded = false }
                            )
                        } else {
                            pairedPrinters.forEach { p ->
                                DropdownMenuItem(
                                    text = {
                                        Column {
                                            Text(p.name, color = TextPrimary, fontWeight = FontWeight.Bold)
                                            Text(p.address, color = TextSecondary, fontSize = 11.sp)
                                        }
                                    },
                                    onClick = {
                                        selectedPrinterName = p.name
                                        selectedPrinterAddress = p.address
                                        printerDropdownExpanded = false
                                    }
                                )
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                OutlinedButton(
                    onClick = {
                        if (selectedPrinterAddress.isEmpty()) {
                            Toast.makeText(context, "Pilih printer terlebih dahulu", Toast.LENGTH_SHORT).show()
                        } else {
                            coroutineScope.launch {
                                isTestingPrinter = true
                                val res = printerManager.testPrint(selectedPrinterAddress, hotspotName)
                                isTestingPrinter = false
                                if (res.isSuccess) {
                                    Toast.makeText(context, "Cetak sample berhasil!", Toast.LENGTH_SHORT).show()
                                } else {
                                    Toast.makeText(context, "Gagal mencetak: ${res.exceptionOrNull()?.message}", Toast.LENGTH_LONG).show()
                                }
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp),
                    enabled = !isTestingPrinter && selectedPrinterAddress.isNotEmpty()
                ) {
                    if (isTestingPrinter) {
                        CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Sedang Mencetak...")
                    } else {
                        Icon(Icons.Default.Receipt, contentDescription = null, modifier = Modifier.size(16.dp), tint = EmeraldActive)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Cetak Test Struk ke Printer")
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(20.dp))

        // Save Button
        Button(
            onClick = {
                val p = port.toIntOrNull() ?: 8728
                val updated = RouterConfig(
                    host = host.trim(),
                    port = p,
                    username = username.trim(),
                    password = password,
                    hotspotName = hotspotName.trim(),
                    dnsName = dnsName.trim(),
                    printerAddress = selectedPrinterAddress,
                    printerName = selectedPrinterName
                )
                RouterConfigManager(context).saveConfig(updated)
                RouterConfigManager.updateClient(updated)
                onConfigSaved(updated)
                Toast.makeText(context, "Konfigurasi berhasil disimpan!", Toast.LENGTH_SHORT).show()
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(50.dp),
            colors = ButtonDefaults.buttonColors(containerColor = PrimaryBlue),
            shape = RoundedCornerShape(10.dp)
        ) {
            Icon(Icons.Default.Save, contentDescription = null, tint = Color.White)
            Spacer(modifier = Modifier.width(8.dp))
            Text("Simpan Konfigurasi", fontWeight = FontWeight.Bold, color = Color.White)
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Info Card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(10.dp),
            colors = CardDefaults.cardColors(containerColor = MikhmonSurfaceVariant)
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text("INFORMASI LISENSI PERANGKAT", style = MaterialTheme.typography.labelSmall, color = TextSecondary, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(6.dp))
                Text("HWID: ${licenseInfo.hwid}", color = PrimaryBlueLight, fontSize = 12.sp, fontFamily = FontFamily.Monospace)
                Text("Status: ${licenseInfo.message}", color = TextSecondary, fontSize = 12.sp)
                Text("Versi Aplikasi: v3.2.0 Official MIKHMON Android", color = TextMuted, fontSize = 11.sp)
            }
        }

        Spacer(modifier = Modifier.height(30.dp))
    }
}
