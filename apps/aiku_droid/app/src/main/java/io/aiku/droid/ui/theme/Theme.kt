package io.aiku.droid.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.LocalTextStyle
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.remember
import androidx.compose.ui.text.TextStyle

data class Appearance(
    val theme: AikuTheme = AikuTheme.DeepSea,
    val mode: ThemeMode = ThemeMode.System,
)

private fun AikuColors.toMaterialColorScheme() = if (isDark) {
    darkColorScheme(
        primary = primary,
        onPrimary = onPrimary,
        background = background,
        onBackground = text,
        surface = surface,
        onSurface = text,
        surfaceContainerLow = surface,
        onSurfaceVariant = textTertiary,
        outline = borderStrong,
        error = danger,
    )
} else {
    lightColorScheme(
        primary = primary,
        onPrimary = onPrimary,
        background = background,
        onBackground = text,
        surface = surface,
        onSurface = text,
        surfaceContainerLow = surface,
        onSurfaceVariant = textTertiary,
        outline = borderStrong,
        error = danger,
    )
}

private val InterTypography = Typography().let { base ->
    Typography(
        displayLarge = base.displayLarge.copy(fontFamily = Inter),
        displayMedium = base.displayMedium.copy(fontFamily = Inter),
        displaySmall = base.displaySmall.copy(fontFamily = Inter),
        headlineLarge = base.headlineLarge.copy(fontFamily = Inter),
        headlineMedium = base.headlineMedium.copy(fontFamily = Inter),
        headlineSmall = base.headlineSmall.copy(fontFamily = Inter),
        titleLarge = base.titleLarge.copy(fontFamily = Inter),
        titleMedium = base.titleMedium.copy(fontFamily = Inter),
        titleSmall = base.titleSmall.copy(fontFamily = Inter),
        bodyLarge = base.bodyLarge.copy(fontFamily = Inter),
        bodyMedium = base.bodyMedium.copy(fontFamily = Inter),
        bodySmall = base.bodySmall.copy(fontFamily = Inter),
        labelLarge = base.labelLarge.copy(fontFamily = Inter),
        labelMedium = base.labelMedium.copy(fontFamily = Inter),
        labelSmall = base.labelSmall.copy(fontFamily = Inter),
    )
}

@Composable
fun AikuDroidTheme(
    appearance: Appearance = Appearance(),
    content: @Composable () -> Unit,
) {
    val isDark = when (appearance.mode) {
        ThemeMode.Light -> false
        ThemeMode.Dark -> true
        ThemeMode.System -> isSystemInDarkTheme()
    }
    val colors = remember(appearance.theme, isDark) { aikuColors(appearance.theme, isDark) }

    CompositionLocalProvider(LocalAikuColors provides colors) {
        MaterialTheme(
            colorScheme = colors.toMaterialColorScheme(),
            typography = InterTypography,
        ) {
            CompositionLocalProvider(LocalTextStyle provides TextStyle(fontFamily = Inter, color = colors.text), content = content)
        }
    }
}
