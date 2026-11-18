package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.focusHalo
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
        Text(text = stat.value, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = valueColor, maxLines = 1)
        Text(text = stat.label, fontSize = 12.sp, color = colors.textTertiary)
    }
}

@Composable
internal fun InventorySectionHeading(
    text: String,
    modifier: Modifier = Modifier,
    trailingText: String? = null,
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
            Text(text = trailingText, fontSize = 12.sp, color = colors.textTertiary, modifier = Modifier.padding(horizontal = 4.dp))
        }
        if (actionLabel != null && onAction != null) {
            Text(
                text = actionLabel,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.link,
                modifier = Modifier
                    .heightIn(min = 44.dp)
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
    val border = if (isDanger) colors.dangerButtonBorder else colors.borderStrong

    Row(
        horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .heightIn(min = 52.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(if (isDanger) 1.5.dp else 1.dp, border, ControlShape)
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
internal fun SheetTextField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    modifier: Modifier = Modifier,
    isNumber: Boolean = false,
    isError: Boolean = false,
    helper: String? = null,
) {
    val colors = LocalAikuColors.current
    val fieldInteraction = remember { MutableInteractionSource() }
    val isFieldFocused by fieldInteraction.collectIsFocusedAsState()
    val fieldFocusProgress = animateFocusProgress(isFieldFocused)
    val idleBorder = if (isError) colors.danger else lerp(colors.borderStrong, colors.focus, fieldFocusProgress)

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            interactionSource = fieldInteraction,
            keyboardOptions = if (isNumber) KeyboardOptions(keyboardType = KeyboardType.Decimal) else KeyboardOptions.Default,
            modifier = Modifier.fillMaxWidth().semantics { contentDescription = label },
            decorationBox = { inner ->
                Box(
                    contentAlignment = Alignment.CenterStart,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(46.dp)
                        .focusHalo(progress = fieldFocusProgress, color = colors.focus, cornerRadius = 8.dp)
                        .clip(ControlShape)
                        .background(colors.surface)
                        .border(1.dp, idleBorder, ControlShape)
                        .padding(horizontal = 12.dp),
                ) {
                    inner()
                }
            },
        )
        if (helper != null) {
            Text(text = helper, fontSize = 12.sp, color = if (isError) colors.danger else colors.textTertiary)
        }
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
    confirmLook: ButtonLook = ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
    isWorking: Boolean = false,
    showConfirm: Boolean = true,
) {
    Column(modifier = modifier.fillMaxWidth().padding(top = 4.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        if (showConfirm) {
            AikuButton(text = confirmLabel, onClick = onConfirm, style = confirmLook.style, icon = confirmLook.icon, isLoading = isWorking, height = 52.dp, modifier = Modifier.fillMaxWidth())
        }
        AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onCancel, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
    }
}
