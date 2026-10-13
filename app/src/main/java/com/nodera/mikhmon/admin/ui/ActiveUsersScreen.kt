package com.nodera.mikhmon.admin.ui

import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
            .background(MikhmonBg)
    ) {
        // Top Navbar
        MikhmonNavbar(
            sessionName = "Hotspot > Active Users",
            routerHost = config.host,
            isConnected = true,
            onRefreshClick = { refreshUsers() },
            isRefreshing = isLoading,
            printerName = config.printerName
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(12.dp)
        ) {
            // Search Input
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                placeholder = { Text("Filter User / IP / MAC...", color = MikhmonTextMuted, fontSize = 12.sp) },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = MikhmonTextMuted, modifier = Modifier.size(18.dp)) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(4.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedTextColor = MikhmonTextPrimary,
                    unfocusedTextColor = MikhmonTextPrimary,
                    focusedBorderColor = MikhmonBlue,
                    unfocusedBorderColor = MikhmonBorder,
                    focusedContainerColor = MikhmonCardBg,
                    unfocusedContainerColor = MikhmonCardBg
                )
            )

            Spacer(modifier = Modifier.height(8.dp))

            // Subheader
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Total ${filteredUsers.size} sesi online aktif",
                    color = MikhmonTextMuted,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold
                )

                Text(
                    text = "Tarik untuk segarkan",
                    color = MikhmonTextMuted,
                    fontSize = 10.sp
                )
            }

            Spacer(modifier = Modifier.height(8.dp))

            if (errorMessage != null) {
                Surface(
                    color = MikhmonRedDark,
                    shape = RoundedCornerShape(4.dp),
                    modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp)
                ) {
                    Text(
                        text = errorMessage ?: "",
                        color = Color.White,
                        fontSize = 11.sp,
                        modifier = Modifier.padding(8.dp)
                    )
                }
            }

            // Table / List
            if (filteredUsers.isEmpty() && !isLoading) {
                Box(
                    modifier = Modifier.fillMaxSize().weight(1f),
                    contentAlignment = Alignment.Center
                ) {
                    Text("Tidak ada user aktif ditemukan", color = MikhmonTextMuted, fontSize = 13.sp)
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxWidth().weight(1f),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    items(filteredUsers, key = { it.id }) { u ->
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
                                Column(modifier = Modifier.weight(1f)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Text(
                                            text = u.user,
                                            color = MikhmonTextPrimary,
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 14.sp
                                        )
                                        Spacer(modifier = Modifier.width(6.dp))
                                        Surface(
                                            color = MikhmonGreenDark,
                                            shape = RoundedCornerShape(3.dp)
                                        ) {
                                            Text(
                                                text = u.uptime,
                                                color = Color.White,
                                                fontSize = 9.sp,
                                                fontWeight = FontWeight.Bold,
                                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 1.dp)
                                            )
                                        }
                                    }

                                    Spacer(modifier = Modifier.height(3.dp))

                                    Text(
                                        text = "IP: ${u.address} • MAC: ${u.macAddress}",
                                        color = MikhmonTextSecondary,
                                        fontFamily = FontFamily.Monospace,
                                        fontSize = 11.sp
                                    )

                                    Spacer(modifier = Modifier.height(2.dp))

                                    Text(
                                        text = "↓ ${formatBytes(u.bytesOut)}  •  ↑ ${formatBytes(u.bytesIn)}",
                                        color = MikhmonTextMuted,
                                        fontSize = 10.sp
                                    )
                                }

                                // Kick Button (Merah Khas Mikhmon)
                                IconButton(
                                    onClick = { userToKick = u },
                                    modifier = Modifier.size(32.dp)
                                ) {
                                    Icon(Icons.Default.DeleteOutline, contentDescription = "Kick", tint = MikhmonRed, modifier = Modifier.size(20.dp))
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Confirm Dialog
    if (userToKick != null) {
        val target = userToKick!!
        AlertDialog(
            onDismissRequest = { userToKick = null },
            title = { Text("Kick Sesi User?", color = MikhmonTextPrimary, fontWeight = FontWeight.Bold) },
            text = { Text("Putus koneksi user '${target.user}' (${target.address}) dari MikroTik?", color = MikhmonTextSecondary) },
            containerColor = MikhmonCardBg,
            confirmButton = {
                Button(
                    onClick = {
                        coroutineScope.launch {
                            val client = RouterConfigManager.getOrCreateClient(config)
                            val res = client.kickUser(target.id)
                            if (res.isSuccess) {
                                Toast.makeText(context, "User ${target.user} berhasil di-kick!", Toast.LENGTH_SHORT).show()
                                refreshUsers()
                            }
                            userToKick = null
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = MikhmonRed),
                    shape = RoundedCornerShape(4.dp)
                ) {
                    Text("Kick", color = Color.White)
                }
            },
            dismissButton = {
                TextButton(onClick = { userToKick = null }) {
                    Text("Batal", color = MikhmonTextSecondary)
                }
            }
        )
    }
}
