package com.nodera.mikhmon.admin.theme

import android.app.Activity
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val MikhmonColorScheme = darkColorScheme(
    primary = MikhmonBlue,
    onPrimary = MikhmonTextPrimary,
    primaryContainer = MikhmonCardBg,
    onPrimaryContainer = MikhmonTextPrimary,
    secondary = MikhmonGreen,
    onSecondary = MikhmonTextPrimary,
    background = MikhmonBg,
    onBackground = MikhmonTextPrimary,
    surface = MikhmonCardBg,
    onSurface = MikhmonTextPrimary,
    surfaceVariant = MikhmonCardHeaderBg,
    onSurfaceVariant = MikhmonTextSecondary,
    outline = MikhmonBorder,
    error = MikhmonRed,
    onError = MikhmonTextPrimary
)

@Composable
fun MikhmonTheme(
    content: @Composable () -> Unit
) {
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as? Activity)?.window
            if (window != null) {
                // Set system status bar and navigation bar to exact Mikhmon navbar color
                window.statusBarColor = MikhmonCardBg.toArgb()
                window.navigationBarColor = MikhmonCardBg.toArgb()
                WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = false
                WindowCompat.getInsetsController(window, view).isAppearanceLightNavigationBars = false
            }
        }
    }

    MaterialTheme(
        colorScheme = MikhmonColorScheme,
        typography = Typography,
        content = content
    )
}
