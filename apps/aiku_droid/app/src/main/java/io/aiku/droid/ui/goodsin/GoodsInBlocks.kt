package io.aiku.droid.ui.goodsin

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
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
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.rememberTabIndication
import io.aiku.droid.ui.navigation.BookedInRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.StockDeliveryCheckingRoute
import io.aiku.droid.ui.navigation.StockDeliveryChecklistRoute
import io.aiku.droid.ui.theme.LocalAikuColors

internal enum class DeliveryView(@StringRes val label: Int) {
    Pending(R.string.goodsin_tab_pending),
    Done(R.string.goodsin_tab_done),
    Checklist(R.string.goodsin_tab_checklist),
}

internal enum class ActionTone { Neutral, Primary, Danger }

internal data class CheckItemLook(val chip: String?, val tone: Tone?, val subtitle: String)

internal data class Difference(val chip: String, val tone: Tone, val description: String)

private fun DeliveryView.routeFor(reference: String): Any = when (this) {
    DeliveryView.Pending -> StockDeliveryCheckingRoute(reference)
    DeliveryView.Done -> BookedInRoute(reference)
    DeliveryView.Checklist -> StockDeliveryChecklistRoute(reference)
}

@Composable
internal fun StockDeliveryHeader(reference: String) {
    AppHeader(isSample = true, title = reference, kicker = stringResource(R.string.goodsin_kicker_stock_delivery, GoodsInSamples.SUPPLIER))
}

@Composable
internal fun SectionHeading(title: String, modifier: Modifier = Modifier, caption: String? = null, isCaptionSample: Boolean = false) {
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
            Text(
                text = caption,
                fontSize = 12.sp,
                color = colors.textTertiary,
                modifier = if (isCaptionSample) Modifier.fakeData(cornerRadius = 4.dp).padding(horizontal = 4.dp, vertical = 2.dp) else Modifier,
            )
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
internal fun OrdererCard(modifier: Modifier = Modifier) {
    DetailCard(
        title = stringResource(R.string.goodsin_orderer),
        lines = listOf(
            DetailLine(icon = R.drawable.ic_ui_person, text = GoodsInSamples.ORDERER),
            DetailLine(icon = R.drawable.ic_ui_document, text = stringResource(R.string.goodsin_purchase_orders, GoodsInSamples.PURCHASE_ORDERS)),
            DetailLine(icon = R.drawable.ic_ui_clock, text = stringResource(R.string.goodsin_estimated_receiving, GoodsInSamples.ESTIMATED_RECEIVING)),
        ),
        modifier = modifier.fakeData(),
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
    trailing: String? = null,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val (container, content) = when (tone) {
        ActionTone.Neutral -> colors.background to colors.textSecondary
        ActionTone.Primary -> colors.soft to colors.link
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
        if (trailing != null) {
            Text(text = trailing, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
        }
    }
}

@Composable
internal fun DeliveryViewTabs(active: DeliveryView, reference: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val tabShape = RoundedCornerShape(7.dp)

    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(colors.border)
            .padding(horizontal = 2.dp),
    ) {
        DeliveryView.entries.forEach { view ->
            val isActive = view == active
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .weight(1f)
                    .height(44.dp)
                    .selectable(
                        selected = isActive,
                        interactionSource = remember { MutableInteractionSource() },
                        indication = rememberTabIndication(cornerRadius = 9.dp),
                        role = Role.Tab,
                        onClick = { if (!isActive) navigator.switchSection(view.routeFor(reference)) },
                    )
                    .padding(horizontal = 2.dp, vertical = 4.dp)
                    .clip(tabShape)
                    .background(if (isActive) colors.surface else Color.Transparent),
            ) {
                Text(
                    text = stringResource(view.label),
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
internal fun ScanPromptCard(
    title: String,
    body: String,
    code: String,
    detail: String,
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.scan)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(20.dp))
            Text(text = title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        }
        Text(text = body, fontSize = 13.sp, color = colors.onScan)
        Text(text = code, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        Text(text = detail, fontSize = 13.sp, color = colors.onScan)
    }
}

@Composable
internal fun PlaceItemCard(
    code: String,
    subtitle: String,
    modifier: Modifier = Modifier,
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
        ProductPhoto(code = code, size = 72.dp)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = code, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
        }
        if (chip != null) {
            StatusBadge(text = chip, tone = chipTone)
        }
    }
}

@Composable
internal fun CountStepper(
    value: Int,
    onValueChange: (Int) -> Unit,
    modifier: Modifier = Modifier,
    min: Int = 0,
    max: Int = Int.MAX_VALUE,
    valueMinWidth: Dp = 56.dp,
) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = modifier) {
        StepSquare(symbol = "−", description = stringResource(R.string.stepper_decrease), isEnabled = value > min) { onValueChange(value - 1) }
        Text(
            text = value.toString(),
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
            textAlign = TextAlign.Center,
            modifier = Modifier.widthIn(min = valueMinWidth),
        )
        StepSquare(symbol = "+", description = stringResource(R.string.stepper_increase), isEnabled = value < max) { onValueChange(value + 1) }
    }
}

@Composable
private fun StepSquare(symbol: String, description: String, isEnabled: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(width = 1.dp, color = colors.borderStrong, shape = ControlShape)
            .clickable(enabled = isEnabled, role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Text(text = symbol, fontSize = 20.sp, color = if (isEnabled) colors.text else colors.faint)
    }
}

@Composable
internal fun checkItemLook(item: CheckItem): CheckItemLook {
    val checked = item.checked
    val deliveredLine = stringResource(R.string.goodsin_item_delivered, item.name, item.delivered)
    return when {
        checked == null -> CheckItemLook(chip = null, tone = null, subtitle = deliveredLine)
        checked == 0 -> CheckItemLook(chip = stringResource(R.string.goodsin_not_received), tone = Tone.Danger, subtitle = stringResource(R.string.goodsin_item_checked_as_zero, item.name))
        checked < item.delivered -> CheckItemLook(chip = stringResource(R.string.goodsin_chip_checked_under), tone = Tone.Warn, subtitle = deliveredLine)
        checked > item.delivered -> CheckItemLook(chip = stringResource(R.string.goodsin_chip_checked_over), tone = Tone.Orange, subtitle = deliveredLine)
        else -> CheckItemLook(chip = stringResource(R.string.sd_state_checked), tone = Tone.Success, subtitle = deliveredLine)
    }
}

@Composable
internal fun differenceOf(checked: Int, delivered: Int): Difference {
    val gap = checked - delivered
    return when {
        checked == 0 -> Difference(
            chip = stringResource(R.string.goodsin_chip_not_received_by, delivered),
            tone = Tone.Danger,
            description = pluralStringResource(R.plurals.goodsin_difference_under, delivered, delivered),
        )
        gap < 0 -> Difference(
            chip = stringResource(R.string.goodsin_chip_under, -gap),
            tone = Tone.Warn,
            description = pluralStringResource(R.plurals.goodsin_difference_under, -gap, -gap),
        )
        gap > 0 -> Difference(
            chip = stringResource(R.string.goodsin_chip_over, gap),
            tone = Tone.Orange,
            description = pluralStringResource(R.plurals.goodsin_difference_over, gap, gap),
        )
        else -> Difference(
            chip = stringResource(R.string.goodsin_chip_matches),
            tone = Tone.Success,
            description = stringResource(R.string.goodsin_difference_none),
        )
    }
}
