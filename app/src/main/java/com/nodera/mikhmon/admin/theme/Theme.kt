package com.nodera.mikhmon.admin.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val DarkColorScheme = darkColorScheme(
    primary = PrimaryBlueLight,
    onPrimary = MikhmonDarkBg,
    primaryContainer = PrimaryBlueDark,
    onPrimaryContainer = TextPrimary,
    secondary = EmeraldActive,
    onSecondary = MikhmonDarkBg,
    background = MikhmonDarkBg,
    onBackground = TextPrimary,
    surface = MikhmonSurface,
    onSurface = TextPrimary,
    surfaceVariant = MikhmonSurfaceVariant,
    onSurfaceVariant = TextSecondary,
    error = DangerRed,
    onError = TextPrimary
)

@Composable
fun MikhmonTheme(
    darkTheme: Boolean = true, // Default to sleek dark mode
    content: @Composable () -> Unit
) {
    val colorScheme = DarkColorScheme
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as? Activity)?.window
            if (window != null) {
                window.statusBarColor = MikhmonDarkBg.toArgb()
                window.navigationBarColor = MikhmonDarkBg.toArgb()
                WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = false
                WindowCompat.getInsetsController(window, view).isAppearanceLightNavigationBars = false
            }
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        content = content
    )
}
