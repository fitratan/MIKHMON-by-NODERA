package com.nodera.mikhmon.admin.ui

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
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
import com.nodera.mikhmon.admin.data.RouterConfig
import com.nodera.mikhmon.admin.data.RouterConfigManager
import com.nodera.mikhmon.admin.router.ActiveHotspotUser
import com.nodera.mikhmon.admin.theme.*
import kotlinx.coroutines.launch

@Composable
fun ActiveUsersScreen(
    config: RouterConfig
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()

    var users by remember { mutableStateOf<List<ActiveHotspotUser>>(emptyList()) }
    var searchQuery by remember { mutableStateOf("") }
    var isLoading by remember { mutableStateOf(false) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    var userToKick by remember { mutableStateOf<ActiveHotspotUser?>(null) }

    fun refreshUsers() {
        coroutineScope.launch {
            isLoading = true
            errorMessage = null
            val client = RouterConfigManager.getOrCreateClient(config)
            val conn = client.connect(config.username, config.password)
            if (conn.isSuccess) {
                val res = client.getActiveUsers()
                if (res.isSuccess) {
                    users = res.getOrDefault(emptyList())
                } else {
                    errorMessage = res.exceptionOrNull()?.message ?: "Gagal memuat pengguna aktif"
                }
            } else {
                errorMessage = conn.exceptionOrNull()?.message ?: "Gagal terhubung ke router"
            }
            isLoading = false
        }
    }

    LaunchedEffect(Unit) {
        refreshUsers()
    }

    val filteredUsers = remember(users, searchQuery) {
        if (searchQuery.isBlank()) users
        else users.filter {
            it.user.contains(searchQuery, ignoreCase = true) ||
                    it.address.contains(searchQuery, ignoreCase = true) ||
                    it.macAddress.contains(searchQuery, ignoreCase = true)
        }
    }

    fun formatBytes(bytes: Long): String {
        return when {
            bytes >= 1024 * 1024 * 1024 -> String.format("%.2f GB", bytes.toDouble() / (1024 * 1024 * 1024))
            bytes >= 1024 * 1024 -> String.format("%.1f MB", bytes.toDouble() / (1024 * 1024))
            bytes >= 1024 -> String.format("%.0f KB", bytes.toDouble() / 1024)
            else -> "$bytes B"
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MikhmonDarkBg)
            .statusBarsPadding()
            .padding(16.dp)
    ) {
        // Header
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "Pengguna Aktif Hotspot",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = TextPrimary
                )
                Text(
                    text = "Total ${users.size} sesi online saat ini",
                    style = MaterialTheme.typography.bodyMedium,
                    color = TextSecondary
                )
            }

            IconButton(
                onClick = { refreshUsers() },
                enabled = !isLoading
            ) {
                if (isLoading) {
                    CircularProgressIndicator(modifier = Modifier.size(20.dp), color = PrimaryBlueLight, strokeWidth = 2.dp)
                } else {
                    Icon(Icons.Default.Refresh, contentDescription = "Refresh", tint = PrimaryBlueLight)
                }
            }
        }

        Spacer(modifier = Modifier.height(12.dp))

        // Search Bar
        OutlinedTextField(
            value = searchQuery,
            onValueChange = { searchQuery = it },
            placeholder = { Text("Cari User / IP / MAC...") },
            leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = TextSecondary) },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(10.dp),
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = TextPrimary,
                unfocusedTextColor = TextPrimary,
                focusedBorderColor = PrimaryBlue,
                unfocusedBorderColor = MikhmonCardBorder
            )
        )

        Spacer(modifier = Modifier.height(12.dp))

        if (errorMessage != null) {
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
            Spacer(modifier = Modifier.height(10.dp))
        }

        // List
        if (filteredUsers.isEmpty() && !isLoading) {
            Box(
                modifier = Modifier.fillMaxSize().weight(1f),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Default.WifiOff, contentDescription = null, tint = TextMuted, modifier = Modifier.size(48.dp))
                    Spacer(modifier = Modifier.height(12.dp))
                    Text("Tidak ada user aktif ditemukan", color = TextSecondary)
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxWidth().weight(1f),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(filteredUsers, key = { it.id }) { u ->
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = MikhmonSurface),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column(modifier = Modifier.weight(1f)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(
                                        text = u.user,
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.Bold,
                                        color = TextPrimary
                                    )
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Surface(
                                        color = EmeraldBg,
                                        shape = RoundedCornerShape(4.dp)
                                    ) {
                                        Text(
                                            text = u.uptime,
                                            color = EmeraldActive,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.height(4.dp))

                                Text(
                                    text = "IP: ${u.address} • MAC: ${u.macAddress}",
                                    style = MaterialTheme.typography.bodyMedium.copy(fontFamily = FontFamily.Monospace),
                                    color = TextSecondary,
                                    fontSize = 11.sp
                                )

                                Spacer(modifier = Modifier.height(2.dp))

                                Text(
                                    text = "↓ ${formatBytes(u.bytesOut)}  •  ↑ ${formatBytes(u.bytesIn)}",
                                    color = TextMuted,
                                    fontSize = 11.sp
                                )
                            }

                            // Kick Action
                            IconButton(
                                onClick = { userToKick = u }
                            ) {
                                Icon(Icons.Default.PersonRemove, contentDescription = "Kick", tint = DangerRed)
                            }
                        }
                    }
                }
            }
        }
    }

    // Confirm Kick Dialog
    if (userToKick != null) {
        val target = userToKick!!
        AlertDialog(
            onDismissRequest = { userToKick = null },
            title = { Text("Kick Pengguna?") },
            text = { Text("Apakah Anda yakin ingin memutus koneksi user '${target.user}' (${target.address})?") },
            confirmButton = {
                Button(
                    onClick = {
                        coroutineScope.launch {
                            val client = RouterConfigManager.getOrCreateClient(config)
                            val kickRes = client.kickUser(target.id)
                            if (kickRes.isSuccess) {
                                Toast.makeText(context, "User ${target.user} berhasil di-kick!", Toast.LENGTH_SHORT).show()
                                refreshUsers()
                            } else {
                                Toast.makeText(context, "Gagal kick: ${kickRes.exceptionOrNull()?.message}", Toast.LENGTH_SHORT).show()
                            }
                            userToKick = null
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = DangerRed)
                ) {
                    Text("Kick Sekarang", color = Color.White)
                }
            },
            dismissButton = {
                TextButton(onClick = { userToKick = null }) {
                    Text("Batal")
                }
            }
        )
    }
}
