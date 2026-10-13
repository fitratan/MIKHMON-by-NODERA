package com.nodera.mikhmon.admin.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
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

    val navItems = listOf(
        NavigationTabItem("Dashboard", Icons.Default.Dashboard, 0),
        NavigationTabItem("Generator", Icons.Default.ConfirmationNumber, 1),
        NavigationTabItem("User Aktif", Icons.Default.PeopleAlt, 2),
        NavigationTabItem("Pengaturan", Icons.Default.Settings, 3)
    )

    Scaffold(
        modifier = Modifier.fillMaxSize(),
        containerColor = MikhmonDarkBg,
        bottomBar = {
            NavigationBar(
                containerColor = MikhmonSurface,
                tonalElevation = 8.dp,
                modifier = Modifier.navigationBarsPadding()
            ) {
                navItems.forEach { item ->
                    val isSelected = selectedTab == item.tabIndex
                    NavigationBarItem(
                        selected = isSelected,
                        onClick = { selectedTab = item.tabIndex },
                        icon = {
                            Icon(
                                item.icon,
                                contentDescription = item.title,
                                tint = if (isSelected) PrimaryBlueLight else TextSecondary
                            )
                        },
                        label = {
                            Text(
                                item.title,
                                color = if (isSelected) PrimaryBlueLight else TextSecondary
                            )
                        },
                        colors = NavigationBarItemDefaults.colors(
                            indicatorColor = MikhmonSurfaceVariant
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
            color = MikhmonDarkBg
        ) {
            when (selectedTab) {
                0 -> DashboardScreen(
                    config = currentConfig,
                    licenseInfo = licenseInfo,
                    onNavigateToTab = { tab -> selectedTab = tab }
                )
                1 -> VoucherGeneratorScreen(config = currentConfig)
                2 -> ActiveUsersScreen(config = currentConfig)
                3 -> RouterSettingsScreen(
                    currentConfig = currentConfig,
                    licenseInfo = licenseInfo,
                    onConfigSaved = { updated -> currentConfig = updated }
                )
            }
        }
    }
}
