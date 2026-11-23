package io.aiku.droid.ui.components

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.FlowRowScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant
import java.time.LocalDate
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

private const val RECORD_DATE_PATTERN = "d MMM yyyy"
private const val ISO_DATE_LENGTH = 10
private const val DETAIL_SEPARATOR = " · "

internal fun recordDate(value: String?, zone: ZoneId): String? {
    val text = value?.takeIf { it.isNotBlank() } ?: return null
    val formatter = DateTimeFormatter.ofPattern(RECORD_DATE_PATTERN, Locale.getDefault())
    val instant = runCatching { OffsetDateTime.parse(text).toInstant() }.getOrNull() ?: runCatching { Instant.parse(text) }.getOrNull()
    if (instant != null) {
        return formatter.withZone(zone).format(instant)
    }
    return runCatching { LocalDate.parse(text.take(ISO_DATE_LENGTH)).format(formatter) }.getOrNull()
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun RecordRow(
    reference: String,
    modifier: Modifier = Modifier,
    date: String? = null,
    dateCaption: String? = null,
    party: String? = null,
    details: List<String> = emptyList(),
    leading: (@Composable () -> Unit)? = null,
    badges: @Composable FlowRowScope.() -> Unit = {},
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 64.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        leading?.invoke()
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(text = reference, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis)
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalArrangement = Arrangement.spacedBy(4.dp),
                itemVerticalAlignment = Alignment.CenterVertically,
            ) {
                badges()
                if (!party.isNullOrBlank()) {
                    Text(text = party, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
            if (details.isNotEmpty()) {
                Text(text = details.joinToString(DETAIL_SEPARATOR), fontSize = 13.sp, lineHeight = 18.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
            }
        }
        if (date != null) {
            Column(
                horizontalAlignment = Alignment.End,
                verticalArrangement = Arrangement.spacedBy(2.dp),
                modifier = Modifier.align(Alignment.Top),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Icon(painter = painterResource(R.drawable.ic_fa_calendar), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(12.dp))
                    Text(text = date, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary, maxLines = 1)
                }
                if (dateCaption != null) {
                    Text(text = dateCaption, fontSize = 11.sp, color = colors.textTertiary, maxLines = 1, textAlign = TextAlign.End)
                }
            }
        }
    }
}
