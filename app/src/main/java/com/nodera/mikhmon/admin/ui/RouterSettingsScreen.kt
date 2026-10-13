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
            Toast.makeText(context, "Pasangkan printer thermal di Bluetooth HP terlebih dahulu", Toast.LENGTH_LONG).show()
        }
    }

    LaunchedEffect(Unit) {
        scanPrinters()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg)
    ) {
        // Top Navbar
        MikhmonNavbar(
            sessionName = "Settings > Session",
            routerHost = host,
            isConnected = true,
            onRefreshClick = {},
            printerName = selectedPrinterName
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scrollState)
                .padding(12.dp)
        ) {
            // Card 1: Router MikroTik Session
            MikhmonCard(
                title = "Konfigurasi MikroTik (Port 8728 API)",
                icon = Icons.Default.Router
            ) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Column(modifier = Modifier.weight(2f)) {
                        Text("IP Router MikroTik", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        Spacer(modifier = Modifier.height(4.dp))
                        OutlinedTextField(
                            value = host,
                            onValueChange = { host = it },
                            placeholder = { Text("192.168.88.1", color = MikhmonTextMuted, fontSize = 12.sp) },
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
                        Text("Port API", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        Spacer(modifier = Modifier.height(4.dp))
                        OutlinedTextField(
                            value = port,
                            onValueChange = { port = it },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
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

                Text("Username API", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(4.dp))
                OutlinedTextField(
                    value = username,
                    onValueChange = { username = it },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
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

                Spacer(modifier = Modifier.height(10.dp))

                Text("Password API", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(4.dp))
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                    singleLine = true,
                    trailingIcon = {
                        IconButton(onClick = { showPassword = !showPassword }) {
                            Icon(
                                if (showPassword) Icons.Default.Visibility else Icons.Default.VisibilityOff,
                                contentDescription = null,
                                tint = MikhmonTextMuted
                            )
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
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

                Spacer(modifier = Modifier.height(10.dp))

                Text("Nama Hotspot (Judul Struk)", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(4.dp))
                OutlinedTextField(
                    value = hotspotName,
                    onValueChange = { hotspotName = it },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
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

                Spacer(modifier = Modifier.height(10.dp))

                Text("DNS Name Login (URL)", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.height(4.dp))
                OutlinedTextField(
                    value = dnsName,
                    onValueChange = { dnsName = it },
                    placeholder = { Text("hotspot.local", color = MikhmonTextMuted, fontSize = 12.sp) },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
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

                Spacer(modifier = Modifier.height(12.dp))

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
                                    "Gagal: ${res.exceptionOrNull()?.message}",
                                    Toast.LENGTH_LONG
                                ).show()
                            }
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonBlue),
                    enabled = !isTestingConnection
                ) {
                    if (isTestingConnection) {
                        CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Menguji Socket Port 8728...", fontSize = 12.sp)
                    } else {
                        Icon(Icons.Default.NetworkCheck, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Tes Koneksi MikroTik Offline", fontSize = 12.sp)
                    }
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            // Card 2: Bluetooth Thermal Printer
            MikhmonCard(
                title = "Thermal Printer Bluetooth",
                icon = Icons.Default.Print,
                headerRightContent = {
                    IconButton(onClick = { scanPrinters() }, modifier = Modifier.size(24.dp)) {
                        Icon(Icons.Default.Refresh, contentDescription = "Scan", tint = MikhmonTextPrimary, modifier = Modifier.size(16.dp))
                    }
                }
            ) {
                Text(
                    text = "Pilih Printer yang sudah dipasangkan di HP:",
                    color = MikhmonTextMuted,
                    fontSize = 11.sp
                )

                Spacer(modifier = Modifier.height(6.dp))

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
                        expanded = printerDropdownExpanded,
                        onDismissRequest = { printerDropdownExpanded = false },
                        modifier = Modifier.background(MikhmonCardBg)
                    ) {
                        if (pairedPrinters.isEmpty()) {
                            DropdownMenuItem(
                                text = { Text("Belum ada printer dipasangkan", color = MikhmonTextMuted) },
                                onClick = { printerDropdownExpanded = false }
                            )
                        } else {
                            pairedPrinters.forEach { p ->
                                DropdownMenuItem(
                                    text = {
                                        Column {
                                            Text(p.name, color = MikhmonTextPrimary, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                                            Text(p.address, color = MikhmonTextMuted, fontSize = 10.sp)
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

                Spacer(modifier = Modifier.height(10.dp))

                OutlinedButton(
                    onClick = {
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
                    },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonGreen),
                    enabled = !isTestingPrinter && selectedPrinterAddress.isNotEmpty()
                ) {
                    Icon(Icons.Default.Receipt, contentDescription = null, modifier = Modifier.size(16.dp), tint = MikhmonGreen)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Tes Cetak Struk ke Printer", fontSize = 12.sp)
                }
            }

            Spacer(modifier = Modifier.height(14.dp))

            // Save Configuration Button
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
                    Toast.makeText(context, "Konfigurasi MikroTik & Printer Disimpan!", Toast.LENGTH_SHORT).show()
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(44.dp),
                shape = RoundedCornerShape(4.dp),
                colors = ButtonDefaults.buttonColors(containerColor = MikhmonBlue)
            ) {
                Icon(Icons.Default.Save, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                Spacer(modifier = Modifier.width(8.dp))
                Text("Simpan Konfigurasi", fontWeight = FontWeight.Bold, color = Color.White, fontSize = 13.sp)
            }

            Spacer(modifier = Modifier.height(14.dp))

            // Card 3: License & HWID Info
            MikhmonCard(
                title = "Hardware Licensing (Anti-Piracy)",
                icon = Icons.Default.Security
            ) {
                Text("HWID Perangkat:", color = MikhmonTextMuted, fontSize = 11.sp)
                Text(licenseInfo.hwid, color = MikhmonBlue, fontWeight = FontWeight.Bold, fontSize = 13.sp, fontFamily = FontFamily.Monospace)
                Spacer(modifier = Modifier.height(4.dp))
                Text("Status: ${licenseInfo.message}", color = MikhmonTextSecondary, fontSize = 11.sp)
                Text("Official MIKHMON Android Admin by NODERA", color = MikhmonTextMuted, fontSize = 10.sp)
            }

            Spacer(modifier = Modifier.height(30.dp))
        }
    }
}
