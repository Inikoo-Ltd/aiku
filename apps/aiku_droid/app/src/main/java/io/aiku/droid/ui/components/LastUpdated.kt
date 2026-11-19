package io.aiku.droid.ui.components

import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

private const val SAME_DAY_PATTERN = "HH:mm"
private const val OTHER_DAY_PATTERN = "d MMM HH:mm"

@Composable
fun LastUpdatedNote(updatedAt: Instant, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Text(
        text = lastUpdatedLabel(updatedAt),
        fontSize = 12.sp,
        lineHeight = 16.sp,
        color = colors.textTertiary,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
fun lastUpdatedLabel(updatedAt: Instant): String {
    val locale = LocalConfiguration.current.locales[0]
    return stringResource(R.string.cache_last_updated, lastUpdatedTime(updatedAt, Instant.now(), ZoneId.systemDefault(), locale))
}

internal fun lastUpdatedTime(updatedAt: Instant, now: Instant, zone: ZoneId, locale: Locale): String {
    val updated = updatedAt.atZone(zone)
    val pattern = if (updated.toLocalDate() == now.atZone(zone).toLocalDate()) SAME_DAY_PATTERN else OTHER_DAY_PATTERN
    return DateTimeFormatter.ofPattern(pattern, locale).format(updated)
}
