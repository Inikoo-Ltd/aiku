package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private val TileShape = RoundedCornerShape(10.dp)
private val SegmentsShape = RoundedCornerShape(10.dp)
private val SegmentShape = RoundedCornerShape(7.dp)

internal const val KICKER_SEPARATOR = " · "

@Composable
internal fun deliveryNoteKicker(vararg parts: String): String =
    (listOf(stringResource(R.string.goodsout_extra_delivery_note)) + parts).joinToString(KICKER_SEPARATOR)

internal fun progressTone(done: Int, required: Int): Tone? = when {
    done >= required -> Tone.Success
    done > 0 -> Tone.Primary
    else -> null
}

@Composable
internal fun DeliveryNoteMoreButton(onClick: () -> Unit) {
    HeaderIconButton(
        icon = R.drawable.ic_ui_more,
        description = stringResource(R.string.more_actions),
        onClick = onClick,
        filled = true,
    )
}

@Composable
internal fun ExtraIconTile(@DrawableRes icon: Int, tone: Tone) {
    val toneColors = LocalAikuColors.current.tone(tone)

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier.size(40.dp).clip(TileShape).background(toneColors.container),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = toneColors.content, modifier = Modifier.size(20.dp))
    }
}

@Composable
internal fun ExtraRow(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    leading: (@Composable () -> Unit)? = null,
    chip: String? = null,
    chipTone: Tone = Tone.Neutral,
    trailing: (@Composable () -> Unit)? = null,
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
        leading?.invoke()
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = title,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (chip != null) {
                    StatusBadge(text = chip, tone = chipTone)
                }
            }
            if (!subtitle.isNullOrEmpty()) {
                Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
            }
        }
        trailing?.invoke()
    }
}

@Composable
internal fun QuantityText(done: Int, required: Int, tone: Tone?) {
    val colors = LocalAikuColors.current
    val doneColor = tone?.let { colors.tone(it).content } ?: colors.text

    Text(
        text = buildAnnotatedString {
            withStyle(SpanStyle(color = doneColor)) { append(done.toString()) }
            withStyle(SpanStyle(fontSize = 13.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)) { append(" / $required") }
        },
        fontSize = 16.sp,
        fontWeight = FontWeight.Bold,
    )
}

@Composable
internal fun RowLink(text: String, onClick: (() -> Unit)? = null) {
    val colors = LocalAikuColors.current

    Text(
        text = text,
        fontSize = 14.sp,
        fontWeight = FontWeight.SemiBold,
        color = colors.link,
        maxLines = 1,
        modifier = if (onClick != null) {
            Modifier
                .clip(ControlShape)
                .clickable(role = Role.Button, onClick = onClick)
                .heightIn(min = 44.dp)
                .padding(horizontal = 4.dp, vertical = 12.dp)
        } else {
            Modifier
        },
    )
}

@Composable
internal fun SectionHeading(text: String, modifier: Modifier = Modifier, trailing: String? = null) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.Bottom, modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
        Text(
            text = text.uppercase(),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.78.sp,
            color = colors.textTertiary,
            modifier = Modifier.weight(1f),
        )
        if (trailing != null) {
            Text(text = trailing, fontSize = 12.sp, color = colors.textTertiary)
        }
    }
}

@Composable
internal fun SheetGroupLabel(text: String) {
    Text(
        text = text.uppercase(),
        fontSize = 12.sp,
        fontWeight = FontWeight.Bold,
        letterSpacing = 0.6.sp,
        color = LocalAikuColors.current.textTertiary,
    )
}

@Composable
internal fun HintText(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        fontSize = 12.sp,
        lineHeight = 18.sp,
        color = LocalAikuColors.current.textQuaternary,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
internal fun ScanTargetCard(
    title: String,
    caption: String,
    target: String,
    modifier: Modifier = Modifier,
    detail: String? = null,
    onScan: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.scan)
            .then(if (onScan != null) Modifier.clickable(role = Role.Button, onClickLabel = title, onClick = onScan) else Modifier)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(20.dp))
            Text(text = title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        }
        Text(text = caption, fontSize = 13.sp, color = colors.onScan)
        Text(text = target, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        if (detail != null) {
            Text(text = detail, fontSize = 13.sp, color = colors.onScan)
        }
    }
}

@Composable
internal fun SegmentTabs(
    options: List<String>,
    selectedIndex: Int,
    onSelect: (Int) -> Unit,
    modifier: Modifier = Modifier,
    fontSize: TextUnit = 13.sp,
    segmentHeight: Dp = 36.dp,
) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(SegmentsShape)
            .background(colors.border)
            .padding(horizontal = 4.dp),
    ) {
        options.forEachIndexed { index, option ->
            val isSelected = index == selectedIndex
            Box(
                modifier = Modifier
                    .weight(1f)
                    .height(segmentHeight + 8.dp)
                    .selectable(selected = isSelected, role = Role.Tab, onClick = { onSelect(index) })
                    .padding(vertical = 4.dp),
            ) {
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier
                        .fillMaxSize()
                        .clip(SegmentShape)
                        .background(if (isSelected) colors.surface else Color.Transparent),
                ) {
                    Text(
                        text = option,
                        fontSize = fontSize,
                        fontWeight = FontWeight.SemiBold,
                        color = if (isSelected) colors.link else colors.textTertiary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
    }
}

@Composable
internal fun FieldLabel(text: String) {
    Text(text = text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = LocalAikuColors.current.text)
}

@Composable
internal fun PlainTextField(
    value: String,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
    placeholder: String? = null,
    isMultiLine: Boolean = false,
    minHeight: Dp = 46.dp,
) {
    val colors = LocalAikuColors.current

    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        singleLine = !isMultiLine,
        minLines = if (isMultiLine) 2 else 1,
        textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, color = colors.text),
        cursorBrush = SolidColor(colors.primary),
        modifier = modifier.fillMaxWidth(),
        decorationBox = { inner ->
            Box(
                contentAlignment = if (isMultiLine) Alignment.TopStart else Alignment.CenterStart,
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = minHeight)
                    .clip(ControlShape)
                    .background(colors.surface)
                    .border(1.dp, colors.borderStrong, ControlShape)
                    .padding(horizontal = 12.dp, vertical = 10.dp),
            ) {
                if (value.isEmpty() && placeholder != null) {
                    Text(text = placeholder, fontSize = 15.sp, color = colors.textQuaternary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
                inner()
            }
        },
    )
}

@Composable
internal fun SheetButtons(content: @Composable ColumnScope.() -> Unit) {
    Column(
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth().padding(top = 4.dp),
        content = content,
    )
}

@Composable
internal fun FooterCaption(text: String) {
    Text(
        text = text,
        fontSize = 12.sp,
        color = LocalAikuColors.current.textQuaternary,
        modifier = Modifier.fillMaxWidth(),
        textAlign = TextAlign.Center,
    )
}

@Composable
internal fun StatColumn(value: String, label: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = modifier) {
        Text(text = value, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = colors.text)
        Spacer(modifier = Modifier.height(2.dp))
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}
