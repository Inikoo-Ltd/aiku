package io.aiku.droid.ui.theme

import androidx.annotation.StringRes
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import io.aiku.droid.R

enum class AikuTheme(
    @StringRes val label: Int,
    val nav: Long,
    val onNav: Long,
    val navActive: Long,
    val navAccent: Long,
    val primary: Long,
    val onPrimary: Long,
    val scan: Long,
    val onScan: Long,
    val link: Long,
    val linkDark: Long,
    val shades: ThemeShades,
) {
    DeepSea(R.string.theme_deep_sea, 0xFF4F46E5, 0xFFF3F4F6, 0xFF362CDB, 0xFFFDE047, 0xFF4F46E5, 0xFFF3F4F6, 0xFFFCD34D, 0xFF374151, 0xFF4F46E5, 0xFFA5B4FC, ThemeShades(0xFF15803D, 0xFF92400E, 0xFFFFFBEB, 0xFFF5C451, 0xFFDC2626, 0xFFF19A9A, 0xFFB91C1C)),
    Rosewater(R.string.theme_rosewater, 0xFFF43F5E, 0xFFF5F5F5, 0xFFE3B7C8, 0xFF000000, 0xFFD6C7E2, 0xFF000000, 0xFFFCA5A5, 0xFF374151, 0xFFE11D48, 0xFFFDA4AF, ThemeShades(0xFF2F7D5B, 0xFF8A5A12, 0xFFFDF6E7, 0xFFEBC97E, 0xFFBE123C, 0xFFF4A3B4, 0xFF9F1239, softInk = 0xFF5B4570, softInkDark = 0xFFC9B3DA)),
    Amoled(R.string.theme_amoled, 0xFF000000, 0xFFF2F2F2, 0xFF424242, 0xFFFDE047, 0xFF8E44AD, 0xFFFFFFFF, 0xFFFCD34D, 0xFF374151, 0xFF8E44AD, 0xFFD2A8E8, ThemeShades(0xFF1E8449, 0xFF9A5B0B, 0xFFFEF5E7, 0xFFF2C38B, 0xFFC0392B, 0xFFE6A39C, 0xFF922B21)),
    BlackWhite(R.string.theme_black_white, 0xFF0F1626, 0xFFFFFFFF, 0xFF1E293B, 0xFFF1F5F9, 0xFF0F172A, 0xFFE2E8F0, 0xFFCCCCCC, 0xFF424242, 0xFF0F172A, 0xFFE2E8F0, ThemeShades(0xFF166534, 0xFF854D0E, 0xFFFEFCE8, 0xFFE5D38A, 0xFFB91C1C, 0xFFEBA3A3, 0xFF7F1D1D, cautionInkDark = 0xFFC0A282)),
    UkAw(R.string.theme_uk_aw, 0xFF957A65, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFE87928, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFFB85A14, 0xFFF5A867, ThemeShades(0xFF4D7C0F, 0xFF7C4A03, 0xFFFBF3E4, 0xFFE2C18A, 0xFFB42318, 0xFFEFA49B, 0xFF8F1D14, softInk = 0xFFAB5413, cautionInkDark = 0xFFBEA481)),
    SpainPurple(R.string.theme_es_purple, 0xFFA57FBC, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFA57FBC, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF7E5A96, 0xFFCDB3DD, ThemeShades(0xFF4E7D5B, 0xFF8A6A1F, 0xFFFAF5E8, 0xFFE3CB8F, 0xFFB4464B, 0xFFE8AEB0, 0xFF943236)),
    SpainBlue(R.string.theme_es_blue, 0xFF8DBAD8, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFF8DBAD8, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF3F7DA6, 0xFFB5D5EA, ThemeShades(0xFF3E7D6A, 0xFF8A6A1F, 0xFFFAF5E8, 0xFFE3CB8F, 0xFFB4464B, 0xFFE8AEB0, 0xFF943236, softInk = 0xFF3B749A)),
    SpainGold(R.string.theme_es_gold, 0xFFC1A027, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFC1A027, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF856C12, 0xFFE3C85E, ThemeShades(0xFF4E7D3A, 0xFF8B5E34, 0xFFF8F1EA, 0xFFDDBF9F, 0xFFB4464B, 0xFFE8AEB0, 0xFF943236)),
    SpainRose(R.string.theme_es_rose, 0xFFC48497, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFC48497, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF9E5A6E, 0xFFE3B3C1, ThemeShades(0xFF4E7D5B, 0xFF8A6A1F, 0xFFFAF5E8, 0xFFE3CB8F, 0xFFA8323E, 0xFFE3A7AE, 0xFF85222D)),
    SpainOlive(R.string.theme_es_olive, 0xFF909B44, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFF909B44, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF66702A, 0xFFC4CE7A, ThemeShades(0xFF2C7A6E, 0xFF8A6A1F, 0xFFFAF5E8, 0xFFE3CB8F, 0xFFB4464B, 0xFFE8AEB0, 0xFF943236)),
    PureBlackWhite(R.string.theme_pure_bw, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFE5E7EB, ThemeShades(0xFF166534, 0xFF713F12, 0xFFFFFFFF, 0xFF713F12, 0xFFB91C1C, 0xFFB91C1C, 0xFF991B1B, cautionInkDark = 0xFFBBA38D)),
}

data class ThemeShades(
    val finish: Long,
    val cautionInk: Long,
    val cautionSoft: Long,
    val cautionBorder: Long,
    val danger: Long,
    val dangerBorder: Long,
    val dangerStrong: Long,
    val softInk: Long? = null,
    val softInkDark: Long? = null,
    val cautionInkDark: Long? = null,
)

enum class ThemeMode { Light, Dark, System }

@Immutable
data class AikuColors(
    val nav: Color,
    val onNav: Color,
    val onNavSecondary: Color,
    val navActive: Color,
    val navAccent: Color,
    val navRing: Color,
    val primary: Color,
    val primaryPressed: Color,
    val primaryBorder: Color,
    val onPrimary: Color,
    val scan: Color,
    val onScan: Color,
    val link: Color,
    val focus: Color,
    val soft: Color,
    val softBorder: Color,
    val softInk: Color,
    val background: Color,
    val surface: Color,
    val surfaceMuted: Color,
    val border: Color,
    val borderStrong: Color,
    val divider: Color,
    val text: Color,
    val textSecondary: Color,
    val textTertiary: Color,
    val textQuaternary: Color,
    val faint: Color,
    val danger: Color,
    val dangerSoft: Color,
    val dangerBorder: Color,
    val dangerInk: Color,
    val dangerDot: Color,
    val dangerButtonBorder: Color,
    val dangerStrong: Color,
    val onDangerStrong: Color,
    val success: Color,
    val successSoft: Color,
    val finish: Color,
    val onFinish: Color,
    val warn: Color,
    val warnSoft: Color,
    val warnBorder: Color,
    val warnInk: Color,
    val cautionInk: Color,
    val cautionSoft: Color,
    val cautionBorder: Color,
    val orange: Color,
    val orangeSoft: Color,
    val lime: Color,
    val limeSoft: Color,
    val shadow: Color,
    val toast: Color,
    val onToast: Color,
    val toastAction: Color,
    val scrim: Color,
    val tagline: Color,
    val isDark: Boolean,
)

private fun mix(first: Color, second: Color, firstWeight: Float) = Color(
    red = first.red * firstWeight + second.red * (1 - firstWeight),
    green = first.green * firstWeight + second.green * (1 - firstWeight),
    blue = first.blue * firstWeight + second.blue * (1 - firstWeight),
)

private val White = Color(0xFFFFFFFF)
private val DarkInk = Color(0xFF111827)
private const val READABLE_CONTRAST = 4.5f

private fun contrastBetween(first: Color, second: Color): Float {
    val lighter = maxOf(first.luminance(), second.luminance())
    val darker = minOf(first.luminance(), second.luminance())
    return (lighter + 0.05f) / (darker + 0.05f)
}

private fun readableOn(fill: Color, preferred: Color): Color =
    listOf(preferred, DarkInk, Black).firstOrNull { contrastBetween(it, fill) >= READABLE_CONTRAST }
        ?: listOf(preferred, DarkInk, Black, White).maxBy { contrastBetween(it, fill) }
private val Black = Color(0xFF000000)
private val DarkOnSignal = Color(0xFF1F1F1F)
private const val DARK_SIGNAL_WEIGHT = 0.55f
private const val DARK_SOFT_ALPHA = 0x24 / 255f
private const val DARK_CAUTION_BORDER_ALPHA = 0x59 / 255f
private const val DARK_DANGER_BORDER_ALPHA = 0x66 / 255f
private val ToastBackground = Color(0xFF1F2937)
private val ToastText = Color(0xFFF9FAFB)
private val ToastAction = Color(0xFFFDE047)
private val Scrim = Color(0x8C111827)

private data class Signals(
    val finish: Color,
    val successSoft: Color,
    val cautionInk: Color,
    val cautionSoft: Color,
    val cautionBorder: Color,
    val warn: Color,
    val warnBorder: Color,
    val danger: Color,
    val dangerSoft: Color,
    val dangerBorder: Color,
    val dangerButtonBorder: Color,
    val dangerInk: Color,
    val dangerDot: Color,
    val dangerStrong: Color,
)

private fun lightSignals(theme: AikuTheme): Signals {
    val shades = theme.shades
    val finish = Color(shades.finish)
    val cautionInk = Color(shades.cautionInk)
    val cautionBorder = Color(shades.cautionBorder)
    val danger = Color(shades.danger)
    val derived = Signals(
        finish = finish,
        successSoft = mix(finish, White, 0.08f),
        cautionInk = cautionInk,
        cautionSoft = Color(shades.cautionSoft),
        cautionBorder = cautionBorder,
        warn = mix(cautionInk, cautionBorder, 0.75f),
        warnBorder = cautionBorder,
        danger = danger,
        dangerSoft = mix(danger, White, 0.06f),
        dangerBorder = Color(shades.dangerBorder),
        dangerButtonBorder = Color(shades.dangerBorder),
        dangerInk = mix(danger, Black, 0.70f),
        dangerDot = danger,
        dangerStrong = Color(shades.dangerStrong),
    )
    return if (theme == AikuTheme.DeepSea) {
        derived.copy(
            successSoft = Color(0xFFF0FDF4),
            warn = Color(0xFFB45309),
            warnBorder = Color(0xFFFDE68A),
            dangerSoft = Color(0xFFFEF2F2),
            dangerBorder = Color(0xFFFECACA),
            dangerInk = Color(0xFF991B1B),
            dangerDot = Color(0xFFEF4444),
        )
    } else {
        derived
    }
}

private val DeepSeaDarkSignals = Signals(
    finish = Color(0xFF81C995),
    successSoft = Color(0x2481C995),
    cautionInk = Color(0xFFFDE293),
    cautionSoft = Color(0x24FDD663),
    cautionBorder = Color(0xFF7A6A33),
    warn = Color(0xFFFDD663),
    warnBorder = Color(0x59FDD663),
    danger = Color(0xFFF28B82),
    dangerSoft = Color(0x24F28B82),
    dangerBorder = Color(0x66F28B82),
    dangerButtonBorder = Color(0x66F28B82),
    dangerInk = Color(0xFFF6AEA9),
    dangerDot = Color(0xFFF28B82),
    dangerStrong = Color(0xFFF28B82),
)

private fun lightened(color: Long) = mix(Color(color), White, DARK_SIGNAL_WEIGHT)

private fun darkSignals(theme: AikuTheme): Signals {
    if (theme == AikuTheme.DeepSea) {
        return DeepSeaDarkSignals
    }
    val shades = theme.shades
    val finish = lightened(shades.finish)
    val cautionInk = shades.cautionInkDark?.let(::Color) ?: lightened(shades.cautionInk)
    val danger = lightened(shades.danger)
    val lightWarn = mix(Color(shades.cautionInk), Color(shades.cautionBorder), 0.75f)
    return Signals(
        finish = finish,
        successSoft = finish.copy(alpha = DARK_SOFT_ALPHA),
        cautionInk = cautionInk,
        cautionSoft = cautionInk.copy(alpha = DARK_SOFT_ALPHA),
        cautionBorder = cautionInk.copy(alpha = DARK_CAUTION_BORDER_ALPHA),
        warn = mix(lightWarn, White, DARK_SIGNAL_WEIGHT),
        warnBorder = cautionInk.copy(alpha = DARK_CAUTION_BORDER_ALPHA),
        danger = danger,
        dangerSoft = danger.copy(alpha = DARK_SOFT_ALPHA),
        dangerBorder = danger.copy(alpha = DARK_DANGER_BORDER_ALPHA),
        dangerButtonBorder = danger.copy(alpha = DARK_DANGER_BORDER_ALPHA),
        dangerInk = mix(danger, White, 0.70f),
        dangerDot = danger,
        dangerStrong = lightened(shades.dangerStrong),
    )
}

fun aikuColors(theme: AikuTheme, isDark: Boolean): AikuColors {
    val nav = Color(theme.nav)
    val navActive = Color(theme.navActive)
    val navAccent = Color(theme.navAccent)
    val primary = Color(theme.primary)
    val scan = Color(theme.scan)
    return if (isDark) darkColors(theme, nav, navActive, navAccent, primary, scan) else lightColors(theme, nav, navActive, primary, scan)
}

private fun lightColors(theme: AikuTheme, nav: Color, navActive: Color, primary: Color, scan: Color): AikuColors {
    val onNav = Color(theme.onNav)
    val signals = lightSignals(theme)
    return AikuColors(
        nav = nav,
        onNav = onNav,
        onNavSecondary = mix(onNav, nav, 0.82f),
        navActive = navActive,
        navAccent = Color(theme.navAccent),
        navRing = mix(navActive, White, 0.80f),
        primary = primary,
        primaryPressed = mix(primary, Black, 0.85f),
        primaryBorder = mix(primary, Black, 0.80f),
        onPrimary = readableOn(primary, Color(theme.onPrimary)),
        scan = scan,
        onScan = Color(theme.onScan),
        link = Color(theme.link),
        focus = primary,
        soft = mix(primary, White, 0.12f),
        softBorder = mix(primary, White, 0.35f),
        softInk = Color(theme.shades.softInk ?: theme.link),
        background = Color(0xFFF3F4F6),
        surface = White,
        surfaceMuted = Color(0xFFF9FAFB),
        border = Color(0xFFE5E7EB),
        borderStrong = Color(0xFFD1D5DB),
        divider = Color(0xFFF3F4F6),
        text = Color(0xFF111827),
        textSecondary = Color(0xFF374151),
        textTertiary = Color(0xFF4B5563),
        textQuaternary = Color(0xFF6B7280),
        faint = Color(0xFF9CA3AF),
        danger = signals.danger,
        dangerSoft = signals.dangerSoft,
        dangerBorder = signals.dangerBorder,
        dangerInk = signals.dangerInk,
        dangerDot = signals.dangerDot,
        dangerButtonBorder = signals.dangerButtonBorder,
        dangerStrong = signals.dangerStrong,
        onDangerStrong = White,
        success = signals.finish,
        successSoft = signals.successSoft,
        finish = signals.finish,
        onFinish = White,
        warn = signals.warn,
        warnSoft = signals.cautionSoft,
        warnBorder = signals.warnBorder,
        warnInk = signals.cautionInk,
        cautionInk = signals.cautionInk,
        cautionSoft = signals.cautionSoft,
        cautionBorder = signals.cautionBorder,
        orange = Color(0xFFC2410C),
        orangeSoft = Color(0xFFFFF7ED),
        lime = Color(0xFF4D7C0F),
        limeSoft = Color(0xFFF7FEE7),
        shadow = Color(0x14111827),
        toast = ToastBackground,
        onToast = ToastText,
        toastAction = ToastAction,
        scrim = Scrim,
        tagline = Color(0xFF4B4458),
        isDark = false,
    )
}

private fun darkColors(theme: AikuTheme, nav: Color, navActive: Color, navAccent: Color, primary: Color, scan: Color): AikuColors {
    val monochrome = theme == AikuTheme.PureBlackWhite || theme == AikuTheme.BlackWhite
    val pureBlackWhite = theme == AikuTheme.PureBlackWhite
    val linkDark = Color(theme.linkDark)
    val surface = Color(0xFF292A2D)
    val signals = darkSignals(theme)
    return AikuColors(
        nav = mix(nav, Color(0xFF1F1F1F), 0.30f),
        onNav = White,
        onNavSecondary = Color(0xBDFFFFFF),
        navActive = if (monochrome) Color(0xFF3C4043) else mix(navActive, Color(0xFF2A2B2E), 0.38f),
        navAccent = if (pureBlackWhite) White else mix(navAccent, Color(0xFFE8EAED), 0.45f),
        navRing = mix(navActive, Color(0xFF5F6368), 0.45f),
        primary = if (monochrome) Color(0xFFDADCE0) else mix(primary, Color(0xFF1F1F1F), 0.72f),
        primaryPressed = if (monochrome) Color(0xFFBDC1C6) else mix(mix(primary, Color(0xFF1F1F1F), 0.72f), Black, 0.85f),
        primaryBorder = if (monochrome) Color(0xFFE8EAED) else mix(primary, Color(0xFF5F6368), 0.55f),
        onPrimary = if (monochrome) Color(0xFF1F1F1F) else readableOn(mix(primary, Color(0xFF1F1F1F), 0.72f), Color(theme.onPrimary)),
        scan = if (pureBlackWhite) Color(0xFF3C4043) else mix(scan, Color(0xFF303134), 0.24f),
        onScan = if (pureBlackWhite) Color(0xFFE8EAED) else mix(scan, Color(0xFFE8EAED), 0.60f),
        link = linkDark,
        focus = linkDark,
        soft = mix(linkDark, surface, 0.14f),
        softBorder = mix(linkDark, surface, 0.38f),
        softInk = Color(theme.shades.softInkDark ?: theme.linkDark),
        background = Color(0xFF1F1F1F),
        surface = surface,
        surfaceMuted = Color(0xFF303134),
        border = Color(0xFF3C4043),
        borderStrong = Color(0xFF5F6368),
        divider = Color(0xFF303134),
        text = Color(0xFFE8EAED),
        textSecondary = Color(0xFFDADCE0),
        textTertiary = Color(0xFFBDC1C6),
        textQuaternary = Color(0xFF9AA0A6),
        faint = Color(0xFF80868B),
        danger = signals.danger,
        dangerSoft = signals.dangerSoft,
        dangerBorder = signals.dangerBorder,
        dangerInk = signals.dangerInk,
        dangerDot = signals.dangerDot,
        dangerButtonBorder = signals.dangerButtonBorder,
        dangerStrong = signals.dangerStrong,
        onDangerStrong = DarkOnSignal,
        success = signals.finish,
        successSoft = signals.successSoft,
        finish = signals.finish,
        onFinish = DarkOnSignal,
        warn = signals.warn,
        warnSoft = signals.cautionSoft,
        warnBorder = signals.warnBorder,
        warnInk = signals.cautionInk,
        cautionInk = signals.cautionInk,
        cautionSoft = signals.cautionSoft,
        cautionBorder = signals.cautionBorder,
        orange = Color(0xFFFCAD70),
        orangeSoft = Color(0x24FCAD70),
        lime = Color(0xFFC5E1A5),
        limeSoft = Color(0x24C5E1A5),
        shadow = Color(0x80000000),
        toast = ToastBackground,
        onToast = ToastText,
        toastAction = ToastAction,
        scrim = Scrim,
        tagline = Color(0xFFBDC1C6),
        isDark = true,
    )
}

val DeepSeaLight = aikuColors(AikuTheme.DeepSea, isDark = false)

val LocalAikuColors = staticCompositionLocalOf { DeepSeaLight }
