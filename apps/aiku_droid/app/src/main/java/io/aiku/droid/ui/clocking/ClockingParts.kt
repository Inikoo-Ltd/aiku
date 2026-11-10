package io.aiku.droid.ui.clocking

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.SectionTabItem
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalAppearanceController
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.NotDesignedRoute
import io.aiku.droid.ui.navigation.TimesheetsRoute
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors
import io.aiku.droid.ui.theme.aikuColors
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

internal const val CLOCKING_DAY_TITLE_PATTERN = "EEE dd MMM yyyy"
internal const val CLOCKING_DAY_SHORT_PATTERN = "EEE dd MMM"

internal val ClockingPanelShape = RoundedCornerShape(16.dp)
internal val ClockingTileShape = RoundedCornerShape(10.dp)
private val ClockingFieldShape = RoundedCornerShape(6.dp)

internal enum class ClockingMethod(@StringRes val label: Int) {
    QrCode(R.string.clocking_method_qr_code),
    Pin(R.string.clocking_method_pin),
    Barcode(R.string.clocking_method_barcode),
    CameraQr(R.string.clocking_method_camera_qr),
}

internal enum class ClockingSection { ClockInOut, Timesheets }

@Composable
internal fun clockingSectionTabs(active: ClockingSection, onAdjustment: () -> Unit): List<SectionTabItem> {
    val navigator = LocalAppNavigator.current
    val leave = stringResource(R.string.clocking_tab_leave)
    val overtime = stringResource(R.string.clocking_tab_overtime)
    val calendar = stringResource(R.string.clocking_tab_calendar)

    return listOf(
        SectionTabItem(
            label = stringResource(R.string.clocking_tab_clock_in_out),
            isActive = active == ClockingSection.ClockInOut,
            onClick = { if (active != ClockingSection.ClockInOut) navigator.switchSection(ClockingRoute) },
        ),
        SectionTabItem(
            label = stringResource(R.string.clocking_tab_timesheets),
            isActive = active == ClockingSection.Timesheets,
            onClick = { if (active != ClockingSection.Timesheets) navigator.switchSection(TimesheetsRoute) },
        ),
        SectionTabItem(label = leave, isActive = false, onClick = { navigator.open(NotDesignedRoute(title = leave)) }),
        SectionTabItem(label = stringResource(R.string.clocking_tab_adjustment), isActive = false, onClick = onAdjustment),
        SectionTabItem(label = overtime, isActive = false, onClick = { navigator.open(NotDesignedRoute(title = overtime)) }),
        SectionTabItem(label = calendar, isActive = false, onClick = { navigator.open(NotDesignedRoute(title = calendar)) }),
    )
}

@Composable
internal fun clockingKicker(lead: String): String =
    listOf(lead, LocalWarehouse.current.organisation.name).filter { it.isNotBlank() }.joinToString(" · ")

@Composable
internal fun rememberClockingLightColors(): AikuColors {
    val theme = LocalAppearanceController.current.appearance.theme
    return remember(theme) { aikuColors(theme, isDark = false) }
}

@Composable
internal fun rememberClockingDarkColors(): AikuColors {
    val theme = LocalAppearanceController.current.appearance.theme
    return remember(theme) { aikuColors(theme, isDark = true) }
}

internal fun clockingDateLabel(isoDate: String, pattern: String): String =
    runCatching { LocalDate.parse(isoDate).format(DateTimeFormatter.ofPattern(pattern, Locale.getDefault())) }
        .getOrDefault(isoDate)

@Composable
internal fun ClockingIconCircle(
    @DrawableRes icon: Int,
    tone: Tone,
    modifier: Modifier = Modifier,
    size: Dp = 72.dp,
    iconSize: Dp = 34.dp,
) {
    val colors = LocalAikuColors.current
    val toneColors = colors.tone(tone)
    val container = if (tone == Tone.Neutral) colors.surface else toneColors.container
    val content = if (tone == Tone.Neutral) colors.textTertiary else toneColors.content

    Box(contentAlignment = Alignment.Center, modifier = modifier.size(size).clip(CircleShape).background(container)) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(iconSize))
    }
}

@Composable
internal fun ClockingDetailRow(label: String, value: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(modifier = modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(text = label, fontSize = 14.sp, color = colors.textTertiary)
        Text(
            text = value,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.text,
            textAlign = TextAlign.End,
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
internal fun ClockingField(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    onValueChange: ((String) -> Unit)? = null,
    helper: String? = null,
) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(text = label, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textSecondary)
        Box(
            contentAlignment = Alignment.CenterStart,
            modifier = Modifier
                .fakeData(cornerRadius = 6.dp)
                .fillMaxWidth()
                .height(48.dp)
                .clip(ClockingFieldShape)
                .background(colors.surface)
                .border(1.dp, colors.borderStrong, ClockingFieldShape)
                .padding(horizontal = 12.dp),
        ) {
            if (onValueChange == null) {
                Text(text = value, fontSize = 16.sp, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis)
            } else {
                BasicTextField(
                    value = value,
                    onValueChange = onValueChange,
                    singleLine = true,
                    textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, color = colors.text),
                    cursorBrush = SolidColor(colors.primary),
                    modifier = Modifier.fillMaxWidth().semantics { contentDescription = label },
                )
            }
        }
        if (helper != null) {
            Text(text = helper, fontSize = 13.sp, color = colors.textTertiary, modifier = Modifier.fakeData(cornerRadius = 4.dp).padding(horizontal = 4.dp, vertical = 2.dp))
        }
    }
}
