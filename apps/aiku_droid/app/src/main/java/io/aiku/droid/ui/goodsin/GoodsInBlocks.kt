package io.aiku.droid.ui.goodsin

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.ReturnState
import io.aiku.droid.data.api.StockDeliveryState
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.rememberTabIndication
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.inventory.dayText
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

private const val DATE_PATTERN = "d MMM yyyy"
private const val ISO_DATE_LENGTH = 10

internal val goodsInActionLooks: Map<String, ButtonLook> = mapOf(
    GoodsInActionKey.RECEIVE to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
    GoodsInActionKey.START to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
    GoodsInActionKey.PUT_BACK to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_next),
    GoodsInActionKey.PLACE to ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_location),
    GoodsInActionKey.CHECK to ButtonLook(ButtonStyle.Soft, R.drawable.ic_ui_check_double),
    GoodsInActionKey.SET_AS_RETURNED to ButtonLook(ButtonStyle.Finish, R.drawable.ic_ui_check),
    GoodsInActionKey.UNRECEIVE to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
    GoodsInActionKey.CANCEL to ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_x_circle),
    GoodsInActionKey.UNDO to ButtonLook(ButtonStyle.Caution, R.drawable.ic_ui_undo),
)

internal fun goodsInActionLook(key: String): ButtonLook = goodsInActionLooks[key] ?: ButtonLook(ButtonStyle.Outline)

internal enum class ActionTone { Neutral, Primary, Caution, Danger }

internal data class DeliveryField(@StringRes val label: Int, val value: String)

internal fun deliveryStateTone(state: String): Tone = when (state) {
    StockDeliveryState.RECEIVED, StockDeliveryState.CHECKED -> Tone.Primary
    StockDeliveryState.BOOKING_IN -> Tone.Lime
    StockDeliveryState.BOOKED_IN, StockDeliveryState.PLACED -> Tone.Success
    StockDeliveryState.NOT_RECEIVED, StockDeliveryState.CANCELLED -> Tone.Danger
    else -> Tone.Neutral
}

internal fun returnStateTone(state: String): Tone = when (state) {
    ReturnState.RETURNING -> Tone.Primary
    ReturnState.RETURNED, ReturnState.DONE -> Tone.Success
    ReturnState.CANCELLED -> Tone.Danger
    else -> Tone.Neutral
}

internal fun goodsInDate(value: String?, zone: ZoneId): String? {
    val text = value?.takeIf { it.isNotBlank() } ?: return null
    return dayText(text, zone)
        ?: runCatching { LocalDate.parse(text.take(ISO_DATE_LENGTH)).format(DateTimeFormatter.ofPattern(DATE_PATTERN, Locale.getDefault())) }.getOrNull()
}

@Composable
internal fun GoodsInEvents(
    event: GoodsInEvent?,
    onHandled: () -> Unit,
    onSheetProblem: ((String) -> Unit)? = null,
    doneText: (GoodsInEvent.Done) -> String? = { null },
    onDone: (GoodsInEvent.Done) -> Unit = {},
) {
    val toasts = LocalToasts.current
    val problemTexts = GoodsOutProblem.entries.associateWith { problemText(it) }

    LaunchedEffect(event) {
        when (event) {
            is GoodsInEvent.Done -> {
                val kind = when {
                    event.wasRaised -> ToastKind.Warning
                    event.isNothingChanged -> ToastKind.Info
                    else -> ToastKind.Success
                }
                (event.message ?: doneText(event))?.let { toasts.show(it, kind) }
                onDone(event)
            }
            is GoodsInEvent.Refused -> {
                val text = event.message.ifBlank { problemTexts.getValue(event.problem) }
                if (onSheetProblem != null) onSheetProblem(text) else toasts.show(text, ToastKind.Error)
            }
            null -> return@LaunchedEffect
        }
        onHandled()
    }
}

@Composable
internal fun SectionHeading(title: String, modifier: Modifier = Modifier, caption: String? = null) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.Bottom, modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
        Text(
            text = title.uppercase(),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.8.sp,
            color = colors.textTertiary,
            modifier = Modifier.weight(1f),
        )
        if (caption != null) {
            Text(text = caption, fontSize = 12.sp, color = colors.textTertiary)
        }
    }
}

@Composable
internal fun HelperNote(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        fontSize = 12.sp,
        lineHeight = 18.sp,
        color = LocalAikuColors.current.textQuaternary,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
internal fun FieldGridCard(title: String, fields: List<DeliveryField>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(width = 1.dp, color = colors.border, shape = CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Text(text = title, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = colors.text)
        fields.chunked(2).forEach { pair ->
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
                pair.forEach { field ->
                    Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(1.dp)) {
                        Text(text = stringResource(field.label), fontSize = 11.sp, color = colors.textTertiary)
                        Text(text = field.value, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
                    }
                }
                if (pair.size == 1) {
                    Spacer(modifier = Modifier.weight(1f))
                }
            }
        }
    }
}

@Composable
internal fun ActionLinkRow(
    @DrawableRes icon: Int,
    title: String,
    subtitle: String,
    modifier: Modifier = Modifier,
    tone: ActionTone = ActionTone.Neutral,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val (container, content) = when (tone) {
        ActionTone.Neutral -> colors.background to colors.textSecondary
        ActionTone.Primary -> colors.soft to colors.link
        ActionTone.Caution -> colors.cautionSoft to colors.cautionInk
        ActionTone.Danger -> colors.dangerSoft to colors.danger
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 60.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(40.dp).clip(RoundedCornerShape(10.dp)).background(container)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(20.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
        }
    }
}

@Composable
internal fun ViewTabs(options: List<Pair<DeliveryView, String>>, active: DeliveryView?, onSelect: (DeliveryView) -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val tabShape = RoundedCornerShape(7.dp)

    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(colors.border)
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 2.dp),
    ) {
        options.forEach { (view, label) ->
            val isActive = view == active
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .height(44.dp)
                    .selectable(
                        selected = isActive,
                        interactionSource = remember { MutableInteractionSource() },
                        indication = rememberTabIndication(cornerRadius = 9.dp),
                        role = Role.Tab,
                        onClick = { if (!isActive) onSelect(view) },
                    )
                    .padding(horizontal = 2.dp, vertical = 4.dp)
                    .clip(tabShape)
                    .background(if (isActive) colors.surface else Color.Transparent)
                    .padding(horizontal = 12.dp),
            ) {
                Text(
                    text = label,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isActive) colors.link else colors.textTertiary,
                    maxLines = 1,
                )
            }
        }
    }
}

@Composable
internal fun ScanPromptCard(title: String, body: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.scan)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(20.dp))
            Text(text = title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        }
        Text(text = body, fontSize = 13.sp, color = colors.onScan)
    }
}

@Composable
internal fun PlaceItemCard(
    code: String,
    subtitle: String,
    modifier: Modifier = Modifier,
    imageUrl: String? = null,
    chip: String? = null,
    chipTone: Tone = Tone.Warn,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(width = 1.dp, color = colors.border, shape = CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        ProductPhoto(code = code, size = 72.dp, imageUrl = imageUrl)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = code, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
        }
        if (chip != null) {
            StatusBadge(text = chip, tone = chipTone)
        }
    }
}
