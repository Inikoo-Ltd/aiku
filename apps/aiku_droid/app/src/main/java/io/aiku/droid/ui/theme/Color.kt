package io.aiku.droid.ui.theme

import androidx.annotation.StringRes
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
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
) {
    DeepSea(R.string.theme_deep_sea, 0xFF4F46E5, 0xFFF3F4F6, 0xFF362CDB, 0xFFFDE047, 0xFF4F46E5, 0xFFF3F4F6, 0xFFFCD34D, 0xFF374151, 0xFF4F46E5, 0xFFA5B4FC),
    Rosewater(R.string.theme_rosewater, 0xFFF43F5E, 0xFFF5F5F5, 0xFFE3B7C8, 0xFF000000, 0xFFD6C7E2, 0xFF000000, 0xFFFCA5A5, 0xFF374151, 0xFFE11D48, 0xFFFDA4AF),
    Amoled(R.string.theme_amoled, 0xFF000000, 0xFFF2F2F2, 0xFF424242, 0xFFFDE047, 0xFF8E44AD, 0xFFFFFFFF, 0xFFFCD34D, 0xFF374151, 0xFF8E44AD, 0xFFD2A8E8),
    BlackWhite(R.string.theme_black_white, 0xFF0F1626, 0xFFFFFFFF, 0xFF1E293B, 0xFFF1F5F9, 0xFF0F172A, 0xFFE2E8F0, 0xFFCCCCCC, 0xFF424242, 0xFF0F172A, 0xFFE2E8F0),
    UkAw(R.string.theme_uk_aw, 0xFF957A65, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFE87928, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFFB85A14, 0xFFF5A867),
    SpainPurple(R.string.theme_es_purple, 0xFFA57FBC, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFA57FBC, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF7E5A96, 0xFFCDB3DD),
    SpainBlue(R.string.theme_es_blue, 0xFF8DBAD8, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFF8DBAD8, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF3F7DA6, 0xFFB5D5EA),
    SpainGold(R.string.theme_es_gold, 0xFFC1A027, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFC1A027, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF856C12, 0xFFE3C85E),
    SpainRose(R.string.theme_es_rose, 0xFFC48497, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFFC48497, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF9E5A6E, 0xFFE3B3C1),
    SpainOlive(R.string.theme_es_olive, 0xFF909B44, 0xFFFFFFFF, 0xFF4B5058, 0xFFFFFFFF, 0xFF909B44, 0xFFFFFFFF, 0xFFCCCCCC, 0xFF957A65, 0xFF66702A, 0xFFC4CE7A),
    PureBlackWhite(R.string.theme_pure_bw, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFFFFFFF, 0xFF000000, 0xFFE5E7EB),
}

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
    val soft: Color,
    val softBorder: Color,
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
    val success: Color,
    val successSoft: Color,
    val warn: Color,
    val warnSoft: Color,
    val warnBorder: Color,
    val warnInk: Color,
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
private val Black = Color(0xFF000000)
private val ToastBackground = Color(0xFF1F2937)
private val ToastText = Color(0xFFF9FAFB)
private val ToastAction = Color(0xFFFDE047)
private val Scrim = Color(0x8C111827)

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
        onPrimary = Color(theme.onPrimary),
        scan = scan,
        onScan = Color(theme.onScan),
        link = Color(theme.link),
        soft = mix(primary, White, 0.12f),
        softBorder = mix(primary, White, 0.35f),
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
        danger = Color(0xFFDC2626),
        dangerSoft = Color(0xFFFEF2F2),
        dangerBorder = Color(0xFFFECACA),
        dangerInk = Color(0xFF991B1B),
        dangerDot = Color(0xFFEF4444),
        success = Color(0xFF15803D),
        successSoft = Color(0xFFF0FDF4),
        warn = Color(0xFFB45309),
        warnSoft = Color(0xFFFFFBEB),
        warnBorder = Color(0xFFFDE68A),
        warnInk = Color(0xFF92400E),
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
        onPrimary = if (monochrome) Color(0xFF1F1F1F) else Color(theme.onPrimary),
        scan = if (pureBlackWhite) Color(0xFF3C4043) else mix(scan, Color(0xFF303134), 0.24f),
        onScan = if (pureBlackWhite) Color(0xFFE8EAED) else mix(scan, Color(0xFFE8EAED), 0.60f),
        link = linkDark,
        soft = mix(linkDark, surface, 0.14f),
        softBorder = mix(linkDark, surface, 0.38f),
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
        danger = Color(0xFFF28B82),
        dangerSoft = Color(0x24F28B82),
        dangerBorder = Color(0x66F28B82),
        dangerInk = Color(0xFFF6AEA9),
        dangerDot = Color(0xFFF28B82),
        success = Color(0xFF81C995),
        successSoft = Color(0x2481C995),
        warn = Color(0xFFFDD663),
        warnSoft = Color(0x24FDD663),
        warnBorder = Color(0x59FDD663),
        warnInk = Color(0xFFFDE293),
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
