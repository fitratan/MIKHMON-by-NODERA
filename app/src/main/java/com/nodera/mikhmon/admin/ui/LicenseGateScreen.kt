package com.nodera.mikhmon.admin.ui

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nodera.mikhmon.admin.licensing.LicenseInfo
import com.nodera.mikhmon.admin.licensing.LicenseManager
import com.nodera.mikhmon.admin.licensing.LicenseState
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.launch

@Composable
fun LicenseGateScreen(
    licenseManager: LicenseManager,
    licenseInfo: LicenseInfo,
    onLicenseActivated: () -> Unit
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val scrollState = rememberScrollState()

    var inputKey by remember { mutableStateOf("") }
    var isLoading by remember { mutableStateOf(false) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    var successMessage by remember { mutableStateOf<String?>(null) }

    val hwid = licenseInfo.hwid

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonBg)
            .statusBarsPadding()
            .navigationBarsPadding()
            .verticalScroll(scrollState)
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(modifier = Modifier.height(20.dp))

        // Mikhmon Header Logo
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = "MIKHMON",
                color = Color.White,
                fontWeight = FontWeight.Black,
                fontSize = 24.sp,
                letterSpacing = 1.sp
            )
            Spacer(modifier = Modifier.width(8.dp))
            Surface(
                color = MikhmonBlue,
                shape = RoundedCornerShape(3.dp)
            ) {
                Text(
                    text = "OFFICIAL",
                    color = Color.White,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                )
            }
        }

        Spacer(modifier = Modifier.height(4.dp))

        Text(
            text = "MikroTik Hotspot Admin Mobile • by NODERA",
            color = MikhmonTextMuted,
            fontSize = 12.sp
        )

        Spacer(modifier = Modifier.height(20.dp))

        // HWID Card
        MikhmonCard(
            title = "Aktivasi Lisensi Perangkat (HWID)",
            icon = Icons.Default.Security
        ) {
            Text(
                text = "Hardware ID unik perangkat Anda:",
                color = MikhmonTextMuted,
                fontSize = 11.sp
            )

            Spacer(modifier = Modifier.height(8.dp))

            Surface(
                color = MikhmonBg,
                shape = RoundedCornerShape(4.dp),
                border = androidx.compose.foundation.BorderStroke(1.dp, MikhmonBorder),
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = hwid,
                    color = MikhmonBlue,
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    fontFamily = FontFamily.Monospace,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(12.dp)
                )
            }

            Spacer(modifier = Modifier.height(10.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = {
                        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                        val clip = ClipData.newPlainText("HWID", hwid)
                        clipboard.setPrimaryClip(clip)
                        Toast.makeText(context, "HWID berhasil disalin!", Toast.LENGTH_SHORT).show()
                    },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonTextPrimary)
                ) {
                    Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(14.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Salin HWID", fontSize = 11.sp)
                }

                Button(
                    onClick = {
                        try {
                            val url = licenseManager.getAdminWhatsAppUrl()
                            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                            context.startActivity(intent)
                        } catch (e: Exception) {
                            Toast.makeText(context, "Gagal buka WA: ${e.message}", Toast.LENGTH_SHORT).show()
                        }
                    },
                    modifier = Modifier.weight(1.3f),
                    shape = RoundedCornerShape(4.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = MikhmonGreen)
                ) {
                    Icon(Icons.Default.Share, contentDescription = null, modifier = Modifier.size(14.dp), tint = Color.White)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Hubungi Admin WA", fontSize = 11.sp, color = Color.White, fontWeight = FontWeight.Bold)
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            Text("Masukkan Serial Key:", color = MikhmonTextMuted, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.height(4.dp))

            OutlinedTextField(
                value = inputKey,
                onValueChange = { inputKey = it },
                placeholder = { Text("NDR-LIC-...", color = MikhmonTextMuted, fontSize = 12.sp) },
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
                ),
                trailingIcon = {
                    IconButton(onClick = {
                        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                        val item = clipboard.primaryClip?.getItemAt(0)?.text?.toString()
                        if (!item.isNullOrEmpty()) {
                            inputKey = item.trim()
                        }
                    }) {
                        Icon(Icons.Default.ContentPaste, contentDescription = "Paste", tint = MikhmonBlue, modifier = Modifier.size(18.dp))
                    }
                }
            )

            if (errorMessage != null) {
                Spacer(modifier = Modifier.height(8.dp))
                Text(errorMessage ?: "", color = MikhmonRed, fontSize = 12.sp)
            }

            if (successMessage != null) {
                Spacer(modifier = Modifier.height(8.dp))
                Text(successMessage ?: "", color = MikhmonGreen, fontSize = 12.sp)
            }

            Spacer(modifier = Modifier.height(14.dp))

            Button(
                onClick = {
                    coroutineScope.launch {
                        isLoading = true
                        errorMessage = null
                        val res = licenseManager.activateLicense(inputKey)
                        isLoading = false
                        if (res.first) {
                            successMessage = res.second
                            Toast.makeText(context, res.second, Toast.LENGTH_LONG).show()
                            onLicenseActivated()
                        } else {
                            errorMessage = res.second
                        }
                    }
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(44.dp),
                shape = RoundedCornerShape(4.dp),
                colors = ButtonDefaults.buttonColors(containerColor = MikhmonBlue),
                enabled = !isLoading && inputKey.isNotBlank()
            ) {
                if (isLoading) {
                    CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Memverifikasi...", color = Color.White, fontSize = 13.sp)
                } else {
                    Icon(Icons.Default.Key, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Aktivasi Serial Key", fontWeight = FontWeight.Bold, color = Color.White, fontSize = 13.sp)
                }
            }

            Spacer(modifier = Modifier.height(10.dp))

            // Trial Button
            OutlinedButton(
                onClick = {
                    coroutineScope.launch {
                        isLoading = true
                        errorMessage = null
                        val trialRes = licenseManager.activateTrial()
                        isLoading = false
                        if (trialRes.first) {
                            Toast.makeText(context, trialRes.second, Toast.LENGTH_LONG).show()
                            onLicenseActivated()
                        } else {
                            errorMessage = trialRes.second
                        }
                    }
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(40.dp),
                shape = RoundedCornerShape(4.dp),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = MikhmonYellow),
                enabled = !isLoading
            ) {
                Icon(Icons.Default.RocketLaunch, contentDescription = null, modifier = Modifier.size(14.dp), tint = MikhmonYellow)
                Spacer(modifier = Modifier.width(6.dp))
                Text("Aktifkan Uji Coba (Trial 3 Hari)", fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
            }
        }

        Spacer(modifier = Modifier.height(20.dp))

        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.Lock, contentDescription = null, tint = MikhmonTextMuted, modifier = Modifier.size(12.dp))
            Spacer(modifier = Modifier.width(4.dp))
            Text("Hardware-Locked Protection • NODERA Billing Ecosystem", color = MikhmonTextMuted, fontSize = 10.sp)
        }

        Spacer(modifier = Modifier.height(20.dp))
    }
}
