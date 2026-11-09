package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.LocalAikuColors

val CardShape = RoundedCornerShape(12.dp)
val ControlShape = RoundedCornerShape(8.dp)
val ChipShape = RoundedCornerShape(6.dp)

enum class Tone { Neutral, Primary, Success, Warn, Orange, Danger, Lime }

data class ToneColors(val content: Color, val container: Color)

fun AikuColors.tone(tone: Tone): ToneColors = when (tone) {
    Tone.Neutral -> ToneColors(textSecondary, background)
    Tone.Primary -> ToneColors(link, soft)
    Tone.Success -> ToneColors(success, successSoft)
    Tone.Warn -> ToneColors(warn, warnSoft)
    Tone.Orange -> ToneColors(orange, orangeSoft)
    Tone.Danger -> ToneColors(danger, dangerSoft)
    Tone.Lime -> ToneColors(lime, limeSoft)
}

@Composable
fun AikuCard(
    modifier: Modifier = Modifier,
    padding: Dp = 16.dp,
    spacing: Dp = 12.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(padding),
        verticalArrangement = Arrangement.spacedBy(spacing),
        content = content,
    )
}

@Composable
fun SectionTitle(
    text: String,
    modifier: Modifier = Modifier,
    @DrawableRes icon: Int? = null,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp),
    ) {
        if (icon != null) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(18.dp))
            Box(modifier = Modifier.size(8.dp))
        }
        Text(text = text, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
        if (actionLabel != null && onAction != null) {
            Text(
                text = actionLabel,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.textSecondary,
                modifier = Modifier.clip(ChipShape).clickable(role = Role.Button, onClick = onAction).padding(horizontal = 6.dp, vertical = 8.dp),
            )
        }
    }
}

@Composable
fun GroupLabel(text: String, modifier: Modifier = Modifier, @DrawableRes icon: Int? = null) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, modifier = modifier.padding(horizontal = 4.dp)) {
        if (icon != null) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(16.dp))
            Box(modifier = Modifier.size(6.dp))
        }
        Text(text = text.uppercase(), fontSize = 12.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.7.sp, color = colors.textTertiary)
    }
}

@Composable
fun StatusBadge(text: String, tone: Tone, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current.tone(tone)

    Text(
        text = text,
        fontSize = 11.sp,
        fontWeight = FontWeight.SemiBold,
        color = colors.content,
        maxLines = 1,
        modifier = modifier.clip(ChipShape).background(colors.container).padding(horizontal = 6.dp, vertical = 2.dp),
    )
}

@Composable
fun IconTile(
    @DrawableRes icon: Int,
    tone: Tone,
    modifier: Modifier = Modifier,
    size: Dp = 40.dp,
    iconSize: Dp = 20.dp,
) {
    val colors = LocalAikuColors.current.tone(tone)

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier.size(size).clip(ControlShape).background(colors.container),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.content, modifier = Modifier.size(iconSize))
    }
}

@Composable
fun ListGroup(modifier: Modifier = Modifier, dashed: Boolean = false, content: @Composable ColumnScope.() -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .then(if (dashed) Modifier else Modifier.border(1.dp, colors.border, CardShape)),
        content = content,
    )
}

@Composable
fun RowDivider() {
    HorizontalDivider(thickness = 1.dp, color = LocalAikuColors.current.divider)
}

@Composable
fun ListRow(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    leading: (@Composable () -> Unit)? = null,
    badges: @Composable RowScope.() -> Unit = {},
    trailingValue: String? = null,
    trailingCaption: String? = null,
    showChevron: Boolean = true,
    titleSize: Int = 15,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        leading?.invoke()
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = title,
                    fontSize = titleSize.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                badges()
            }
            if (subtitle != null) {
                Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
            }
        }
        if (trailingValue != null) {
            Column(horizontalAlignment = Alignment.End) {
                Text(text = trailingValue, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
                if (trailingCaption != null) {
                    Text(text = trailingCaption, fontSize = 11.sp, color = colors.textTertiary)
                }
            }
        }
        if (showChevron && onClick != null) {
            Icon(painter = painterResource(R.drawable.ic_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
        }
    }
}

data class Stat(val label: String, val value: String, val caption: String? = null, val tone: Tone? = null)

@Composable
fun StatGrid(stats: List<Stat>, modifier: Modifier = Modifier, columns: Int = 3) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        stats.chunked(columns).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
                row.forEach { stat ->
                    Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        Text(text = stat.label, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1)
                        Text(
                            text = stat.value,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = stat.tone?.let { colors.tone(it).content } ?: colors.text,
                        )
                        if (stat.caption != null) {
                            Text(text = stat.caption, fontSize = 11.sp, color = colors.textTertiary)
                        }
                    }
                }
                repeat(columns - row.size) { Box(modifier = Modifier.weight(1f)) }
            }
        }
    }
}

@Composable
fun StatTile(
    label: String,
    value: String,
    modifier: Modifier = Modifier,
    @DrawableRes icon: Int? = null,
    caption: String? = null,
    captionTone: Tone = Tone.Danger,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = modifier
            .clip(ControlShape)
            .border(1.dp, colors.border, ControlShape)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            if (icon != null) {
                Icon(painter = painterResource(icon), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(16.dp))
            }
            Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary, maxLines = 1)
        }
        Row(verticalAlignment = Alignment.Bottom, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(text = value, fontSize = 26.sp, fontWeight = FontWeight.Bold, color = colors.text)
            if (caption != null) {
                Text(
                    text = caption,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.tone(captionTone).content,
                    modifier = Modifier.padding(bottom = 4.dp),
                )
            }
        }
    }
}

@Composable
fun ProgressBar(fraction: Float, modifier: Modifier = Modifier, height: Dp = 6.dp, tone: Tone = Tone.Primary) {
    val colors = LocalAikuColors.current
    val fill = if (tone == Tone.Primary) colors.primary else colors.tone(tone).content

    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(height)
            .clip(RoundedCornerShape(999.dp))
            .background(colors.softBorder),
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth(fraction.coerceIn(0f, 1f))
                .fillMaxHeight()
                .background(fill),
        )
    }
}

@Composable
fun KeyValueRow(label: String, value: String, modifier: Modifier = Modifier, valueTone: Tone? = null) {
    val colors = LocalAikuColors.current

    Row(modifier = modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(text = label, fontSize = 13.sp, color = colors.textTertiary, modifier = Modifier.weight(1f))
        Text(
            text = value,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = valueTone?.let { colors.tone(it).content } ?: colors.text,
        )
    }
}
