package io.aiku.droid.ui.theme

import androidx.compose.ui.graphics.Color
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.Locale
import kotlin.math.max
import kotlin.math.min
import kotlin.math.pow

private const val MINIMUM_CONTRAST = 4.5

class ButtonContrastTest {

    @Test
    fun `button text keeps at least 4,5 to 1 on its fill in every theme, light and dark`() {
        val failures = AikuTheme.entries.flatMap { theme ->
            listOf(false, true).flatMap { isDark ->
                val colors = aikuColors(theme, isDark)
                val mode = if (isDark) "dark" else "light"
                listOf(
                    "onPrimary on primary" to contrast(colors.onPrimary, colors.primary, colors.surface),
                    "onFinish on finish" to contrast(colors.onFinish, colors.finish, colors.surface),
                    "onDangerStrong on dangerStrong" to contrast(colors.onDangerStrong, colors.dangerStrong, colors.surface),
                    "danger on surface" to contrast(colors.danger, colors.surface, colors.surface),
                    "cautionInk on cautionSoft" to contrast(colors.cautionInk, colors.cautionSoft, colors.surface),
                    "softInk on soft" to contrast(colors.softInk, colors.soft, colors.surface),
                ).filter { (_, ratio) -> ratio < MINIMUM_CONTRAST }
                    .map { (pair, ratio) -> "${theme.name} $mode, $pair: ${String.format(Locale.ROOT, "%.2f", ratio)}" }
            }
        }

        assertTrue(failures.joinToString("\n"), failures.isEmpty())
    }

    @Test
    fun `deep sea keeps its status colours in light and dark`() {
        val light = aikuColors(AikuTheme.DeepSea, isDark = false)
        val dark = aikuColors(AikuTheme.DeepSea, isDark = true)

        assertEquals(
            listOf(0xFFDC2626, 0xFFFEF2F2, 0xFFFECACA, 0xFF991B1B, 0xFFEF4444, 0xFF15803D, 0xFFF0FDF4, 0xFFB45309, 0xFFFFFBEB, 0xFFFDE68A, 0xFF92400E).map(::Color),
            listOf(light.danger, light.dangerSoft, light.dangerBorder, light.dangerInk, light.dangerDot, light.success, light.successSoft, light.warn, light.warnSoft, light.warnBorder, light.warnInk),
        )
        assertEquals(
            listOf(0xFFF28B82, 0x24F28B82L, 0x66F28B82L, 0xFFF6AEA9, 0xFFF28B82, 0xFF81C995, 0x2481C995L, 0xFFFDD663, 0x24FDD663L, 0x59FDD663L, 0xFFFDE293).map(::Color),
            listOf(dark.danger, dark.dangerSoft, dark.dangerBorder, dark.dangerInk, dark.dangerDot, dark.success, dark.successSoft, dark.warn, dark.warnSoft, dark.warnBorder, dark.warnInk),
        )
        assertEquals(Color(0xFFF19A9A), light.dangerButtonBorder)
        assertEquals(Color(0xFFB91C1C), light.dangerStrong)
        assertEquals(Color(0xFF7A6A33), dark.cautionBorder)
    }

    @Test
    fun `status chips follow the button shades of each theme`() {
        AikuTheme.entries.forEach { theme ->
            listOf(false, true).forEach { isDark ->
                val colors = aikuColors(theme, isDark)
                assertEquals(theme.name, colors.finish, colors.success)
                assertEquals(theme.name, colors.cautionInk, colors.warnInk)
                assertEquals(theme.name, colors.cautionSoft, colors.warnSoft)
            }
        }
    }

    private fun contrast(foreground: Color, background: Color, base: Color): Double {
        val fill = background.over(base)
        val first = foreground.over(fill).relativeLuminance()
        val second = fill.relativeLuminance()
        return (max(first, second) + 0.05) / (min(first, second) + 0.05)
    }

    private fun Color.over(base: Color): Color = Color(
        red = red * alpha + base.red * (1 - alpha),
        green = green * alpha + base.green * (1 - alpha),
        blue = blue * alpha + base.blue * (1 - alpha),
    )

    private fun Color.relativeLuminance(): Double = 0.2126 * channel(red) + 0.7152 * channel(green) + 0.0722 * channel(blue)

    private fun channel(value: Float): Double = if (value <= 0.03928f) value / 12.92 else ((value + 0.055) / 1.055).pow(2.4)
}
