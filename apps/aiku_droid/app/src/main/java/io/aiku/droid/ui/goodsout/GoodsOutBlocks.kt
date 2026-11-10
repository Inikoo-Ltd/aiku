package io.aiku.droid.ui.goodsout

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.QuickAction
import io.aiku.droid.ui.components.QuickActions
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateTimeline
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.TimelineEntry
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.goodsout.extra.DeliveryNoteMoreSheet
import io.aiku.droid.ui.navigation.AskCrmRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.LocalAikuColors

private val IconTileShape = RoundedCornerShape(10.dp)

@Composable
internal fun DeliveryNoteHeader(reference: String, kicker: String) {
    var showMore by remember { mutableStateOf(false) }

    AppHeader(
        isSample = true,
        title = reference,
        kicker = kicker,
        actions = {
            HeaderIconButton(
                icon = R.drawable.ic_ui_more,
                description = stringResource(R.string.more_actions),
                onClick = { showMore = true },
                filled = true,
            )
        },
    )
    if (showMore) {
        DeliveryNoteMoreSheet(reference = reference, onDismiss = { showMore = false })
    }
}

@Composable
internal fun DeliveryNoteQuickActions(reference: String, modifier: Modifier = Modifier) {
    val navigator = LocalAppNavigator.current
    var showPrint by remember { mutableStateOf(false) }

    QuickActions(
        actions = listOf(
            QuickAction(label = stringResource(R.string.goodsout_quick_packing_list), icon = R.drawable.ic_ui_print, onClick = { showPrint = true }),
            QuickAction(label = stringResource(R.string.goodsout_quick_task), icon = R.drawable.ic_ui_check, onClick = { navigator.open(AskCrmRoute(reference = reference)) }),
            QuickAction(label = stringResource(R.string.goodsout_quick_ask_crm), icon = R.drawable.ic_ui_chat, onClick = { navigator.open(AskCrmRoute(reference = reference)) }),
        ),
        modifier = modifier,
    )
    if (showPrint) {
        PrintDeliveryNoteSheet(reference = reference, onDismiss = { showPrint = false })
    }
}

@Composable
internal fun FooterHint(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        fontSize = 12.sp,
        color = LocalAikuColors.current.textQuaternary,
        textAlign = TextAlign.Center,
        modifier = modifier.fillMaxWidth(),
    )
}

@Composable
internal fun GoodsOutHeading(title: String, modifier: Modifier = Modifier, trailing: String? = null, isTrailingSample: Boolean = false) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp),
    ) {
        Text(
            text = title.uppercase(),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.78.sp,
            color = colors.textTertiary,
            modifier = Modifier.weight(1f),
        )
        if (trailing != null) {
            Text(
                text = trailing,
                fontSize = 12.sp,
                color = colors.textTertiary,
                modifier = if (isTrailingSample) Modifier.fakeData(cornerRadius = 4.dp).padding(horizontal = 4.dp, vertical = 2.dp) else Modifier,
            )
        }
    }
}

@Composable
internal fun GoodsOutFootNote(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        fontSize = 12.sp,
        lineHeight = 18.sp,
        color = LocalAikuColors.current.textQuaternary,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
internal fun IconTile40(@DrawableRes icon: Int, tone: Tone, modifier: Modifier = Modifier) {
    val toneColors = LocalAikuColors.current.tone(tone)

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier.size(40.dp).clip(IconTileShape).background(toneColors.container),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = toneColors.content, modifier = Modifier.size(20.dp))
    }
}

@Composable
internal fun IconInfoRow(
    @DrawableRes icon: Int,
    title: String,
    subtitle: String,
    modifier: Modifier = Modifier,
    tone: Tone = Tone.Primary,
    chip: String? = null,
    chipTone: Tone = Tone.Success,
    actionLabel: String? = null,
    actionColor: Color? = null,
    onAction: (() -> Unit)? = null,
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
        IconTile40(icon = icon, tone = tone)
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
            if (subtitle.isNotBlank()) {
                Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
            }
        }
        if (actionLabel != null) {
            Text(
                text = actionLabel,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = actionColor ?: colors.link,
                modifier = if (onAction != null) {
                    Modifier
                        .heightIn(min = 44.dp)
                        .clip(ChipShape)
                        .clickable(role = Role.Button, onClick = onAction)
                        .padding(horizontal = 6.dp, vertical = 12.dp)
                } else {
                    Modifier
                },
            )
        }
    }
}

@Composable
internal fun ScanHintCard(
    title: String,
    caption: String,
    value: String,
    detail: String,
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.scan)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(18.dp))
            Text(text = title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        }
        Text(text = caption, fontSize = 13.sp, color = colors.onScan)
        Text(text = value, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        Text(text = detail, fontSize = 13.sp, color = colors.onScan)
    }
}

@Composable
internal fun SummaryCard(title: String, body: String, modifier: Modifier = Modifier, bodyModifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(16.dp),
    ) {
        Text(text = title, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
        Text(text = body, fontSize = 14.sp, color = colors.textTertiary, modifier = bodyModifier)
    }
}

@Composable
internal fun lineChip(line: SampleLine): String? = when (line.status) {
    LineStatus.Picked -> stringResource(R.string.goodsout_line_picked)
    LineStatus.Packed -> stringResource(R.string.goodsout_line_packed)
    LineStatus.Waiting, LineStatus.NotPicked -> line.reason
    LineStatus.ToHandle, LineStatus.Partial -> null
}

internal fun lineChipTone(status: LineStatus): Tone = when (status) {
    LineStatus.Picked, LineStatus.Packed -> Tone.Success
    LineStatus.Waiting -> Tone.Warn
    LineStatus.NotPicked -> Tone.Danger
    LineStatus.ToHandle, LineStatus.Partial -> Tone.Neutral
}

internal fun lineQuantityTone(status: LineStatus): Tone? = when (status) {
    LineStatus.Picked, LineStatus.Packed -> Tone.Success
    LineStatus.Partial -> Tone.Primary
    LineStatus.Waiting -> Tone.Warn
    LineStatus.NotPicked -> Tone.Danger
    LineStatus.ToHandle -> null
}

@Composable
internal fun DeliveryNoteLines(lines: List<SampleLine>, modifier: Modifier = Modifier, onLineClick: ((SampleLine) -> Unit)? = null) {
    ListGroup(modifier = modifier.fakeData()) {
        lines.forEachIndexed { index, line ->
            if (index > 0) {
                RowDivider()
            }
            ItemRow(
                code = line.skuCode,
                subtitle = line.subtitle,
                chip = lineChip(line),
                chipTone = lineChipTone(line.status),
                done = line.done.toString(),
                required = line.required.toString(),
                quantityTone = lineQuantityTone(line.status),
                onClick = onLineClick?.let { open -> { open(line) } },
            )
        }
    }
}

@Composable
internal fun SampleTimeline(entries: List<SampleTimelineEntry>, modifier: Modifier = Modifier) {
    StateTimeline(
        entries = entries.map { TimelineEntry(label = stringResource(it.label), date = it.date) },
        modifier = modifier.fakeData(),
    )
}

@Composable
internal fun SampleDetailCard(
    title: String,
    lines: List<SampleDetailLine>,
    modifier: Modifier = Modifier,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
) {
    DetailCard(
        title = title,
        lines = lines.map { DetailLine(icon = it.icon, text = it.text) },
        modifier = modifier.fakeData(),
        actionLabel = actionLabel,
        onAction = onAction,
    )
}

@Composable
internal fun SampleInfoGroup(
    rows: List<SampleChoice>,
    @DrawableRes icon: Int,
    modifier: Modifier = Modifier,
    tone: Tone = Tone.Primary,
    chip: String? = null,
) {
    ListGroup(modifier = modifier.fakeData()) {
        rows.forEachIndexed { index, row ->
            if (index > 0) {
                RowDivider()
            }
            IconInfoRow(icon = icon, title = row.title, subtitle = row.subtitle, tone = tone, chip = chip)
        }
    }
}

@Composable
internal fun RadioCardRow(
    title: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clip(shape)
            .background(if (selected) colors.soft else colors.surface)
            .border(if (selected) 2.dp else 1.dp, if (selected) colors.primary else colors.borderStrong, shape)
            .clickable(role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Box(
            modifier = Modifier
                .size(20.dp)
                .clip(CircleShape)
                .background(colors.surface)
                .border(if (selected) 6.dp else 2.dp, if (selected) colors.primary else colors.borderStrong, CircleShape),
        )
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            if (!subtitle.isNullOrBlank()) {
                Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
            }
        }
    }
}

@Composable
internal fun BadgeChoiceRow(
    title: String,
    subtitle: String,
    badge: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    isBadgeDanger: Boolean = false,
    enabled: Boolean = true,
) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .alpha(if (enabled) 1f else 0.55f)
            .heightIn(min = 56.dp)
            .clip(shape)
            .background(if (selected) colors.soft else colors.surface)
            .border(if (selected) 2.dp else 1.dp, if (selected) colors.primary else colors.border, shape)
            .clickable(enabled = enabled, role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            Text(text = subtitle, fontSize = 12.sp, color = colors.textTertiary)
        }
        if (selected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        } else {
            Text(
                text = badge,
                fontSize = 12.sp,
                color = if (isBadgeDanger) colors.danger else colors.textTertiary,
                modifier = Modifier
                    .border(1.dp, if (isBadgeDanger) colors.dangerBorder else colors.borderStrong, ChipShape)
                    .padding(horizontal = 6.dp, vertical = 2.dp),
            )
        }
    }
}

@Composable
internal fun SwipeProgressHeader(kicker: String, title: String, done: Int, total: Int) {
    val colors = LocalAikuColors.current
    val fraction = if (total > 0) done.toFloat() / total else 0f
    val pill = RoundedCornerShape(999.dp)

    AppHeader(
        isSample = true,
        title = title,
        kicker = kicker,
        actions = {
            Text(
                text = stringResource(R.string.goodsout_progress_of, done, total),
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.onNav,
                modifier = Modifier.fakeData(cornerRadius = 6.dp).padding(horizontal = 6.dp, vertical = 4.dp),
            )
        },
        below = {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(6.dp)
                    .fakeData(cornerRadius = 3.dp)
                    .clip(pill)
                    .background(Color.White.copy(alpha = 0.2f)),
            ) {
                Box(modifier = Modifier.fillMaxWidth(fraction.coerceIn(0f, 1f)).fillMaxHeight().background(colors.navAccent))
            }
        },
    )
}

@Composable
internal fun SwipeTip(text: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(colors.soft)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_swipe), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
        Text(text = text, fontSize = 13.sp, lineHeight = 18.sp, color = colors.link, modifier = Modifier.weight(1f))
    }
}
