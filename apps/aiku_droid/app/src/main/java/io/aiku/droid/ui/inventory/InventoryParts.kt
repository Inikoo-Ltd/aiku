package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private val TileShape = RoundedCornerShape(10.dp)

internal data class InventoryStat(val value: String, val label: String, val tone: Tone? = null, val onClick: (() -> Unit)? = null)

@Composable
internal fun InventoryStatGrid(stats: List<InventoryStat>, columns: Int, modifier: Modifier = Modifier) {
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        stats.chunked(columns).forEach { rowStats ->
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min),
            ) {
                rowStats.forEach { stat ->
                    InventoryStatTile(stat = stat, modifier = Modifier.weight(1f).fillMaxHeight())
                }
                repeat(columns - rowStats.size) { Spacer(modifier = Modifier.weight(1f)) }
            }
        }
    }
}

@Composable
private fun InventoryStatTile(stat: InventoryStat, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val tone = stat.tone
    val onClick = stat.onClick
    val valueColor = if (tone == null || tone == Tone.Neutral) colors.text else colors.tone(tone).content

    Column(
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(TileShape)
            .background(colors.surface)
            .border(1.dp, colors.border, TileShape)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(12.dp),
    ) {
        Text(text = stat.value, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = valueColor)
        Text(text = stat.label, fontSize = 12.sp, color = colors.textTertiary)
    }
}

@Composable
internal fun InventorySectionHeading(
    text: String,
    modifier: Modifier = Modifier,
    trailingText: String? = null,
    trailingModifier: Modifier = Modifier,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
        Text(
            text = text.uppercase(),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.78.sp,
            color = colors.textTertiary,
            modifier = Modifier.weight(1f),
        )
        if (trailingText != null) {
            Text(text = trailingText, fontSize = 12.sp, color = colors.textTertiary, modifier = trailingModifier)
        }
        if (actionLabel != null && onAction != null) {
            Text(
                text = actionLabel,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.link,
                modifier = Modifier
                    .clip(ChipShape)
                    .clickable(role = Role.Button, onClick = onAction)
                    .padding(horizontal = 8.dp, vertical = 13.dp),
            )
        }
    }
}

@Composable
internal fun IconItemRow(
    @DrawableRes icon: Int,
    title: String,
    subtitle: String,
    modifier: Modifier = Modifier,
    chip: String? = null,
    chipTone: Tone = Tone.Neutral,
    trailing: String? = null,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 60.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier.size(40.dp).clip(TileShape).background(colors.soft),
        ) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = title,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (chip != null) {
                    StatusBadge(text = chip, tone = chipTone)
                }
            }
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
        if (trailing != null) {
            Text(text = trailing, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
        }
    }
}

@Composable
internal fun BoardFootnote(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        fontSize = 12.sp,
        lineHeight = 18.sp,
        color = LocalAikuColors.current.textQuaternary,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
internal fun StockActionButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    @DrawableRes icon: Int? = null,
    isDanger: Boolean = false,
) {
    val colors = LocalAikuColors.current
    val content = if (isDanger) colors.danger else colors.textSecondary

    Row(
        horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .heightIn(min = 52.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, if (isDanger) colors.dangerBorder else colors.borderStrong, ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 6.dp),
    ) {
        if (icon != null) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(20.dp))
        }
        Text(text = text, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = content, textAlign = TextAlign.Center)
    }
}

@Composable
internal fun SheetGroupLabel(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text.uppercase(),
        fontSize = 12.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 0.6.sp,
        color = LocalAikuColors.current.textTertiary,
        modifier = modifier,
    )
}

@Composable
internal fun SheetQuantityRow(
    value: Int,
    onValueChange: (Int) -> Unit,
    modifier: Modifier = Modifier,
    min: Int = 1,
    max: Int = Int.MAX_VALUE,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .fillMaxWidth()
            .clip(TileShape)
            .border(1.dp, colors.border, TileShape)
            .padding(start = 14.dp, end = 4.dp, top = 4.dp, bottom = 4.dp),
    ) {
        Text(text = stringResource(R.string.inventory_quantity), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text, modifier = Modifier.weight(1f))
        QuantityStepButton(
            symbol = "−",
            description = stringResource(R.string.stepper_decrease),
            isEnabled = value > min,
            onClick = { onValueChange(value - 1) },
        )
        Text(
            text = value.toString(),
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
            textAlign = TextAlign.Center,
            modifier = Modifier.width(56.dp),
        )
        QuantityStepButton(
            symbol = "+",
            description = stringResource(R.string.stepper_increase),
            isEnabled = value < max,
            onClick = { onValueChange(value + 1) },
        )
    }
}

@Composable
private fun QuantityStepButton(symbol: String, description: String, isEnabled: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(ControlShape)
            .clickable(enabled = isEnabled, role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Text(text = symbol, fontSize = 22.sp, color = if (isEnabled) colors.textSecondary else colors.faint)
    }
}

@Composable
internal fun SheetTextField(value: String, onValueChange: (String) -> Unit, label: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            modifier = Modifier.fillMaxWidth().semantics { contentDescription = label },
            decorationBox = { inner ->
                Box(
                    contentAlignment = Alignment.CenterStart,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(46.dp)
                        .clip(ControlShape)
                        .background(colors.surface)
                        .border(1.dp, colors.borderStrong, ControlShape)
                        .padding(horizontal = 12.dp),
                ) {
                    inner()
                }
            },
        )
    }
}

@Composable
internal fun SheetDangerNote(text: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Text(
        text = text,
        fontSize = 14.sp,
        color = colors.dangerInk,
        modifier = modifier
            .fillMaxWidth()
            .clip(TileShape)
            .background(colors.dangerSoft)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    )
}

@Composable
internal fun SheetButtons(
    confirmLabel: String,
    onConfirm: () -> Unit,
    onCancel: () -> Unit,
    modifier: Modifier = Modifier,
    confirmStyle: ButtonStyle = ButtonStyle.Primary,
) {
    Column(modifier = modifier.fillMaxWidth().padding(top = 4.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        AikuButton(text = confirmLabel, onClick = onConfirm, style = confirmStyle, height = 52.dp, modifier = Modifier.fillMaxWidth())
        AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onCancel, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
    }
}

internal fun skoSubtitle(sko: SampleSko): String = listOfNotNull(sko.name, sko.detail).joinToString(" · ")

@Composable
internal fun locationChip(location: SampleSkoLocation): String {
    val kind = stringResource(location.kind.label)
    val channel = location.channel ?: return kind
    return stringResource(R.string.inventory_pair, kind, stringResource(channel.label))
}

@Composable
internal fun countChipOptions(counts: List<SampleCount>): List<FilterOption> = counts.map { sample ->
    val label = stringResource(sample.label)
    val count = sample.count
    FilterOption(label = if (count != null) stringResource(R.string.inventory_chip_with_count, label, count) else label)
}
