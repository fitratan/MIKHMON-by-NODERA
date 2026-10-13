package com.nodera.mikhmon.admin.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nodera.mikhmon.admin.data.RouterConfig
import com.nodera.mikhmon.admin.licensing.LicenseInfo
import com.nodera.mikhmon.admin.theme.*

data class NavigationTabItem(
    val title: String,
    val icon: ImageVector,
    val tabIndex: Int
)

@Composable
fun MainContainer(
    initialConfig: RouterConfig,
    licenseInfo: LicenseInfo
) {
    var selectedTab by remember { mutableIntStateOf(0) }
    var currentConfig by remember { mutableStateOf(initialConfig) }

    // 4 Iconic Buttons of MIKHMON Mobile (bottomnav.php)
    val navItems = listOf(
        NavigationTabItem("Dashboard", Icons.Default.Speed, 0),
        NavigationTabItem("Pengguna", Icons.Default.People, 1),
        NavigationTabItem("Generate", Icons.Default.ConfirmationNumber, 2),
        NavigationTabItem("Pengaturan", Icons.Default.Settings, 3)
    )

    Scaffold(
        modifier = Modifier.fillMaxSize(),
        containerColor = MikhmonBg,
        bottomBar = {
            NavigationBar(
                containerColor = MikhmonCardBg,
                tonalElevation = 0.dp,
                modifier = Modifier
                    .navigationBarsPadding()
                    .height(60.dp)
                    .border(width = 1.dp, color = MikhmonBorder)
            ) {
                navItems.forEach { item ->
                    val isSelected = selectedTab == item.tabIndex
                    NavigationBarItem(
                        selected = isSelected,
                        onClick = { selectedTab = item.tabIndex },
                        icon = {
                            Icon(
                                imageVector = item.icon,
                                contentDescription = item.title,
                                tint = if (isSelected) MikhmonBlue else MikhmonTextMuted,
                                modifier = Modifier.size(20.dp)
                            )
                        },
                        label = {
                            Text(
                                text = item.title,
                                color = if (isSelected) MikhmonBlue else MikhmonTextMuted,
                                fontSize = 10.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                            )
                        },
                        colors = NavigationBarItemDefaults.colors(
                            indicatorColor = MikhmonBg,
                            selectedIconColor = MikhmonBlue,
                            unselectedIconColor = MikhmonTextMuted,
                            selectedTextColor = MikhmonBlue,
                            unselectedTextColor = MikhmonTextMuted
                        )
                    )
                }
            }
        }
    ) { innerPadding ->
        Surface(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding),
            color = MikhmonBg
        ) {
            when (selectedTab) {
                0 -> DashboardScreen(
                    config = currentConfig,
                    licenseInfo = licenseInfo,
                    onNavigateToTab = { tab ->
                        // Map 0: Dash, 1: Gen, 2: Active, 3: Settings
                        selectedTab = when (tab) {
                            1 -> 2 // Generate
                            2 -> 1 // Users
                            3 -> 3 // Settings
                            else -> 0
                        }
                    }
                )
                1 -> ActiveUsersScreen(config = currentConfig)
                2 -> VoucherGeneratorScreen(config = currentConfig)
                3 -> RouterSettingsScreen(
                    currentConfig = currentConfig,
                    licenseInfo = licenseInfo,
                    onConfigSaved = { updated -> currentConfig = updated }
                )
            }
        }
    }
}
