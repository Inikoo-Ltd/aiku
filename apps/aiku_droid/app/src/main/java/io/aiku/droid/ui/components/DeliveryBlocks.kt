package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.compositeOver
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors

enum class StepStatus { Done, Current, Waiting, Upcoming }

data class Step(val label: String, val status: StepStatus)

enum class DeliveryNoteStage(@StringRes val label: Int) {
    ToDo(R.string.dn_state_to_do),
    Picking(R.string.dn_state_picking),
    Waiting(R.string.dn_state_waiting),
    Picked(R.string.dn_state_picked),
    Packing(R.string.dn_state_packing),
    Packed(R.string.dn_state_packed),
    Finalised(R.string.dn_state_finalised),
    Dispatched(R.string.dn_state_dispatched),
}

enum class StockDeliveryStage(@StringRes val label: Int) {
    Dispatched(R.string.sd_state_dispatched),
    Received(R.string.sd_state_received),
    Checked(R.string.sd_state_checked),
    BookingIn(R.string.sd_state_booking_in),
    BookedIn(R.string.sd_state_booked_in),
}

@Composable
fun deliveryNoteSteps(current: DeliveryNoteStage, isComplete: Boolean = false): List<Step> {
    val third = if (current == DeliveryNoteStage.Waiting) DeliveryNoteStage.Waiting else DeliveryNoteStage.Picked
    val stages = listOf(
        DeliveryNoteStage.ToDo,
        DeliveryNoteStage.Picking,
        third,
        DeliveryNoteStage.Packing,
        DeliveryNoteStage.Packed,
        DeliveryNoteStage.Finalised,
        DeliveryNoteStage.Dispatched,
    )
    val currentIndex = stages.indexOf(current)
    return stages.mapIndexed { index, stage ->
        val status = when {
            index < currentIndex || (isComplete && index == currentIndex) -> StepStatus.Done
            index == currentIndex && stage == DeliveryNoteStage.Waiting -> StepStatus.Waiting
            index == currentIndex -> StepStatus.Current
            else -> StepStatus.Upcoming
        }
        Step(label = stringResource(stage.label), status = status)
    }
}

@Composable
fun stockDeliverySteps(current: StockDeliveryStage, isComplete: Boolean = false): List<Step> =
    StockDeliveryStage.entries.map { stage ->
        val status = when {
            stage.ordinal < current.ordinal || (isComplete && stage == current) -> StepStatus.Done
            stage == current -> StepStatus.Current
            else -> StepStatus.Upcoming
        }
        Step(label = stringResource(stage.label), status = status)
    }

@Composable
fun StateStepper(steps: List<Step>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val pill = RoundedCornerShape(999.dp)

    Row(
        modifier = modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        steps.forEach { step ->
            val (container, content) = when (step.status) {
                StepStatus.Done -> colors.success to Color.White
                StepStatus.Current -> colors.primary to colors.onPrimary
                StepStatus.Waiting -> colors.warn to Color.White
                StepStatus.Upcoming -> colors.surface to colors.textTertiary
            }
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .height(28.dp)
                    .clip(pill)
                    .background(container)
                    .then(if (step.status == StepStatus.Upcoming) Modifier.border(1.dp, colors.border, pill) else Modifier)
                    .padding(horizontal = 10.dp),
            ) {
                Text(text = step.label, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = content, maxLines = 1)
            }
        }
    }
}

enum class NoteKind(val accent: Color, private val isBasic: Boolean) {
    ShippingLabel(Color(0xFF93C5FD), false),
    Customer(Color(0xFF599FF0), false),
    Warehouse(Color(0xFFFCD34D), false),
    FulfilmentCustomer(Color(0xFF009EFF), true),
    FulfilmentPublic(Color(0xFFE6007E), true),
    FulfilmentPrivate(Color(0xFF5000E6), true),
    Plain(Color(0xFFAAAAAA), false),
    ;

    val header: Color get() = if (isBasic) lerp(accent, Color.White, BASIC_NOTE_WHITE_MIX) else accent
}

private val NOTE_HEADER_INK = Color(0xFF1F2937)
private const val NOTE_BODY_ALPHA = 0x11 / 255f
private const val BASIC_NOTE_WHITE_MIX = 0.6f

@Composable
fun NoteCard(
    title: String,
    text: String?,
    @DrawableRes icon: Int,
    modifier: Modifier = Modifier,
    iconModifier: Modifier = Modifier,
    kind: NoteKind? = null,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val (headerBackground, headerContent) = when {
        kind != null -> kind.header to NOTE_HEADER_INK
        else -> colors.soft to colors.link
    }
    val bodyBackground = kind?.let { it.accent.copy(alpha = NOTE_BODY_ALPHA).compositeOver(colors.surface) } ?: colors.surface
    val borderColor = kind?.header ?: colors.border

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(bodyBackground)
            .border(1.dp, borderColor, CardShape)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.fillMaxWidth().background(headerBackground).padding(horizontal = 12.dp, vertical = 8.dp),
        ) {
            Text(text = title, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = headerContent, modifier = Modifier.weight(1f))
            Icon(painter = painterResource(icon), contentDescription = null, tint = headerContent, modifier = iconModifier.size(16.dp))
        }
        if (text.isNullOrBlank()) {
            Text(
                text = stringResource(R.string.note_empty),
                fontSize = 14.sp,
                fontStyle = FontStyle.Italic,
                color = colors.faint,
                modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
            )
        } else {
            Text(text = text, fontSize = 14.sp, lineHeight = 20.sp, color = colors.textSecondary, modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp))
        }
    }
}

data class TimelineEntry(val label: String, val date: String?)

private val TIMELINE_STEP_MIN_WIDTH = 88.dp
private val TIMELINE_SCROLL_STEP_WIDTH = 112.dp

@Composable
fun StateTimeline(entries: List<TimelineEntry>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    BoxWithConstraints(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape),
    ) {
        val shared = maxWidth / entries.size.coerceAtLeast(1)
        val stepWidth = if (shared >= TIMELINE_STEP_MIN_WIDTH) shared else TIMELINE_SCROLL_STEP_WIDTH
        Column(
            verticalArrangement = Arrangement.spacedBy(4.dp),
            modifier = Modifier
                .horizontalScroll(rememberScrollState())
                .padding(vertical = 10.dp),
        ) {
            Row(modifier = Modifier.height(IntrinsicSize.Max)) {
                entries.forEach { entry ->
                    Box(
                        contentAlignment = Alignment.BottomCenter,
                        modifier = Modifier.width(stepWidth).fillMaxHeight().padding(horizontal = 6.dp),
                    ) {
                        Text(
                            text = entry.label,
                            fontSize = 11.sp,
                            lineHeight = 14.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = if (entry.date != null) colors.text else colors.faint,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
            }
            Row {
                entries.forEachIndexed { index, entry ->
                    Box(contentAlignment = Alignment.Center, modifier = Modifier.width(stepWidth).height(12.dp)) {
                        Row(modifier = Modifier.fillMaxWidth()) {
                            Box(modifier = Modifier.weight(1f).height(2.dp).background(if (index > 0) colors.border else Color.Transparent))
                            Spacer(modifier = Modifier.width(12.dp))
                            Box(modifier = Modifier.weight(1f).height(2.dp).background(if (index < entries.lastIndex) colors.border else Color.Transparent))
                        }
                        Box(modifier = Modifier.size(12.dp).clip(CircleShape).background(if (entry.date != null) colors.success else colors.borderStrong))
                    }
                }
            }
            Row {
                entries.forEach { entry ->
                    Text(
                        text = entry.date ?: "–",
                        fontSize = 11.sp,
                        color = colors.textTertiary,
                        textAlign = TextAlign.Center,
                        maxLines = 1,
                        modifier = Modifier.width(stepWidth),
                    )
                }
            }
        }
    }
}

data class DetailLineAction(@DrawableRes val icon: Int, val description: String, val onClick: () -> Unit)

data class DetailLine(@DrawableRes val icon: Int, val text: String, val action: DetailLineAction? = null)

@Composable
fun DetailCard(
    title: String,
    lines: List<DetailLine>,
    modifier: Modifier = Modifier,
    actionLabel: String? = null,
    actionModifier: Modifier = Modifier,
    onAction: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Text(text = title, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = colors.text)
        lines.forEach { line ->
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                modifier = Modifier.fillMaxWidth().heightIn(min = 28.dp),
            ) {
                Icon(painter = painterResource(line.icon), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(16.dp))
                Text(text = line.text, fontSize = 14.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
                line.action?.let { action ->
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier
                            .size(48.dp)
                            .clip(ChipShape)
                            .clickable(role = Role.Button, onClickLabel = action.description, onClick = action.onClick)
                            .semantics { contentDescription = action.description },
                    ) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier.size(34.dp).clip(ChipShape).border(1.dp, colors.borderStrong, ChipShape),
                        ) {
                            Icon(painter = painterResource(action.icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }
        }
        if (actionLabel != null && onAction != null) {
            Text(
                text = actionLabel,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.link,
                modifier = actionModifier.clip(ChipShape).clickable(role = Role.Button, onClick = onAction).padding(vertical = 6.dp),
            )
        }
    }
}

@Composable
fun PlatformChip(name: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val pill = RoundedCornerShape(999.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier
            .height(28.dp)
            .clip(pill)
            .background(colors.surface)
            .border(1.dp, colors.borderStrong, pill)
            .padding(start = 4.dp, end = 10.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(20.dp).clip(CircleShape).background(colors.background)) {
            Text(text = name.take(1).uppercase(), fontSize = 9.sp, color = colors.textTertiary)
        }
        Text(text = name, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.textSecondary, maxLines = 1)
    }
}

data class QuickAction(val label: String, @DrawableRes val icon: Int, val onClick: () -> Unit, val modifier: Modifier = Modifier)

@Composable
fun QuickActions(actions: List<QuickAction>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(modifier = modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        actions.forEach { action ->
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
                modifier = Modifier
                    .weight(1f)
                    .then(action.modifier)
                    .heightIn(min = 60.dp)
                    .clip(shape)
                    .background(colors.surface)
                    .border(1.dp, colors.borderStrong, shape)
                    .clickable(role = Role.Button, onClick = action.onClick)
                    .padding(vertical = 8.dp),
            ) {
                Icon(painter = painterResource(action.icon), contentDescription = null, tint = colors.textSecondary, modifier = Modifier.size(20.dp))
                Text(text = action.label, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary, maxLines = 1)
            }
        }
    }
}
