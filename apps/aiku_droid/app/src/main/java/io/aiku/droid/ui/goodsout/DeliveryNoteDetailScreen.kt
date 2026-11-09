package io.aiku.droid.ui.goodsout

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsin.GoodsDetailHeader
import io.aiku.droid.ui.goodsin.GoodsDetailScaffold
import io.aiku.droid.ui.goodsin.GoodsDetailTab
import io.aiku.droid.ui.goodsin.GoodsLineRow
import io.aiku.droid.ui.goodsin.GoodsPanelAction
import io.aiku.droid.ui.goodsin.GoodsQuantityStepper
import io.aiku.droid.ui.goodsin.GoodsScanActionButton
import io.aiku.droid.ui.goodsin.GoodsScanPanel
import io.aiku.droid.ui.goodsin.GoodsSectionHeading
import io.aiku.droid.ui.goodsin.goodsActiveEdge
import io.aiku.droid.ui.goodsin.goodsHighlightedCount
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.PackingRoute
import io.aiku.droid.ui.navigation.PreviewWarehouse
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.ScanHardwareRoute
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class PickLineState { Picked, Picking, Waiting, OutOfStock }

private data class PickLineSample(
    val code: String,
    val subtitle: String,
    val required: Int,
    val picked: Int,
    val state: PickLineState,
    val pickLocation: String? = null,
    val locationStock: Int = 0,
)

private val pickLinesSample = listOf(
    PickLineSample("SKU-00412", "A-03-02 · Lavender essential oil 10ml", required = 6, picked = 6, state = PickLineState.Picked),
    PickLineSample(
        "SKU-00733",
        "Bath bomb – rose 180g",
        required = 6,
        picked = 3,
        state = PickLineState.Picking,
        pickLocation = "A-03-02",
        locationStock = 30,
    ),
    PickLineSample("SKU-01187", "A-07-01 · Soy wax candle – vanilla", required = 1, picked = 0, state = PickLineState.Waiting),
    PickLineSample("SKU-01402", "Eucalyptus essential oil 10ml", required = 1, picked = 0, state = PickLineState.OutOfStock),
)

private const val DELIVERY_NOTE_SUMMARY_SAMPLE = "[Customer] · UK shop · 14 items · 6.2 kg"
private const val DELIVERY_NOTE_PICKED_SAMPLE = 9
private const val DELIVERY_NOTE_ITEM_COUNT_SAMPLE = 14

@Composable
fun DeliveryNoteDetailScreen(reference: String) {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    var pickingQuantity by remember { mutableIntStateOf(3) }

    RequireAbility(isAllowed = can.viewGoodsOut, title = reference, activeTab = AppTab.GoodsOut) {
        GoodsDetailScaffold(
            header = {
                GoodsDetailHeader(
                    kicker = stringResource(R.string.goodsout_delivery_note),
                    kickerIcon = R.drawable.ic_goodsout_delivery_note,
                    title = reference,
                    tabs = listOf(
                        GoodsDetailTab(label = stringResource(R.string.goodsout_items), icon = R.drawable.ic_goodsin_items, isActive = true),
                    ),
                )
            },
            activeTab = AppTab.GoodsOut,
            bottomBar = if (can.pickAndPack) {
                {
                    GoodsScanActionButton(text = stringResource(R.string.goodsout_scan), onClick = { navigator.open(ScanRoute) })
                    AikuButton(
                        text = stringResource(R.string.goodsout_go_to_packing),
                        onClick = { navigator.open(PackingRoute(reference)) },
                        icon = R.drawable.ic_goodsout_packed,
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
            } else {
                null
            },
        ) {
            DeliveryNoteStateCard(canSupervise = can.superviseGoodsOut)
            if (can.pickAndPack) {
                GoodsScanPanel(
                    title = stringResource(R.string.goodsout_scan_to_pick),
                    status = stringResource(R.string.goodsout_scanner_ready),
                    primaryAction = GoodsPanelAction(stringResource(R.string.goodsout_guided_picking), R.drawable.ic_goodsin_items) {
                        navigator.open(ScanHardwareRoute)
                    },
                    secondaryAction = GoodsPanelAction(stringResource(R.string.goodsout_use_camera), R.drawable.ic_goodsin_camera) {
                        navigator.open(ScanCameraRoute)
                    },
                    modifier = Modifier.fakeData(),
                )
            }
            DeliveryNoteItems(
                canPick = can.pickAndPack,
                pickingQuantity = pickingQuantity,
                onPickingQuantityChange = { pickingQuantity = it },
            )
        }
    }
}

@Composable
private fun DeliveryNoteStateCard(canSupervise: Boolean) {
    val colors = LocalAikuColors.current
    val staffName = LocalStaff.current.displayName

    AikuCard(modifier = Modifier.fakeData(), spacing = 14.dp) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .size(40.dp)
                    .clip(ControlShape)
                    .background(colors.background),
            ) {
                Icon(
                    painter = painterResource(R.drawable.ic_goodsout_handling),
                    contentDescription = null,
                    tint = colors.textQuaternary,
                    modifier = Modifier.size(20.dp),
                )
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = stringResource(R.string.goodsout_state_handling),
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                )
                Text(text = DELIVERY_NOTE_SUMMARY_SAMPLE, fontSize = 13.sp, color = colors.textTertiary)
            }
        }
        PickerRow(
            name = stringResource(R.string.goodsout_picker_you, staffName),
            initials = staffInitials(staffName),
            canSupervise = canSupervise,
        )
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = stringResource(R.string.goodsout_picked),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.textSecondary,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = goodsHighlightedCount(
                        text = pluralStringResource(
                            R.plurals.goodsout_picked_of_items,
                            DELIVERY_NOTE_ITEM_COUNT_SAMPLE,
                            DELIVERY_NOTE_PICKED_SAMPLE,
                            DELIVERY_NOTE_ITEM_COUNT_SAMPLE,
                        ),
                        count = DELIVERY_NOTE_PICKED_SAMPLE,
                    ),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                )
            }
            ProgressBar(fraction = DELIVERY_NOTE_PICKED_SAMPLE.toFloat() / DELIVERY_NOTE_ITEM_COUNT_SAMPLE, height = 8.dp)
        }
    }
}

private fun staffInitials(name: String): String = name
    .split(" ")
    .filter { it.isNotBlank() }
    .take(2)
    .joinToString(separator = "") { it.first().uppercase() }

@Composable
private fun PickerRow(name: String, initials: String, canSupervise: Boolean) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, ControlShape)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(32.dp)
                .clip(CircleShape)
                .background(colors.navActive),
        ) {
            Text(text = initials, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.navAccent)
        }
        Column(modifier = Modifier.weight(1f)) {
            Text(text = stringResource(R.string.goodsout_picker), fontSize = 12.sp, color = colors.textTertiary)
            Text(
                text = name,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        if (canSupervise) {
            PickerActionButton(
                icon = R.drawable.ic_goodsout_swap,
                description = stringResource(R.string.goodsout_change_picker),
                border = colors.borderStrong,
                tint = colors.textSecondary,
            )
            PickerActionButton(
                icon = R.drawable.ic_goodsout_remove_person,
                description = stringResource(R.string.goodsout_remove_picker),
                border = colors.dangerBorder,
                tint = colors.danger,
            )
        }
    }
}

@Composable
private fun PickerActionButton(@DrawableRes icon: Int, description: String, border: Color, tint: Color) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(ControlShape)
            .border(1.dp, border, ControlShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = {})
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = tint, modifier = Modifier.size(18.dp))
    }
}

@Composable
private fun DeliveryNoteItems(canPick: Boolean, pickingQuantity: Int, onPickingQuantityChange: (Int) -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fakeData()) {
        GoodsSectionHeading(title = stringResource(R.string.goodsout_items), icon = R.drawable.ic_goodsin_items) {
            Text(text = stringResource(R.string.goodsout_pick_route), fontSize = 12.sp, color = colors.textTertiary)
        }
        ListGroup {
            pickLinesSample.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                if (line.state == PickLineState.Picking && canPick) {
                    PickingLine(
                        line = line,
                        quantity = pickingQuantity,
                        onQuantityChange = { onPickingQuantityChange(it.coerceIn(0, line.required)) },
                    )
                } else {
                    PickLineRow(line = line)
                }
            }
        }
    }
}

@Composable
private fun PickLineRow(line: PickLineSample) {
    val colors = LocalAikuColors.current
    val isOutOfStock = line.state == PickLineState.OutOfStock
    val isPicked = line.state == PickLineState.Picked

    GoodsLineRow(
        code = line.code,
        description = if (isOutOfStock) stringResource(R.string.goodsout_out_of_stock) else line.subtitle,
        quantity = stringResource(R.string.goodsout_quantity_of, line.picked, line.required),
        leading = { PickStateIcon(state = line.state) },
        descriptionColor = if (isOutOfStock) colors.dangerInk else colors.textTertiary,
        quantityColor = when {
            isPicked -> colors.text
            isOutOfStock -> colors.dangerInk
            else -> colors.textTertiary
        },
        isQuantityStrong = isPicked,
        background = when (line.state) {
            PickLineState.Picked -> colors.successSoft
            PickLineState.OutOfStock -> colors.dangerSoft
            else -> colors.surface
        },
    )
}

@Composable
private fun PickStateIcon(state: PickLineState) {
    val colors = LocalAikuColors.current
    val (icon, tint) = when (state) {
        PickLineState.Picked -> R.drawable.ic_goodsout_packed to colors.success
        PickLineState.Picking -> R.drawable.ic_goodsout_handling to colors.textQuaternary
        PickLineState.Waiting -> R.drawable.ic_goodsout_queued to colors.textQuaternary
        PickLineState.OutOfStock -> R.drawable.ic_goodsout_out_of_stock to colors.danger
    }

    Icon(painter = painterResource(icon), contentDescription = null, tint = tint, modifier = Modifier.size(20.dp))
}

@Composable
private fun PickingLine(line: PickLineSample, quantity: Int, onQuantityChange: (Int) -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .goodsActiveEdge(colors.primary)
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            PickStateIcon(state = line.state)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = line.code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = line.subtitle, fontSize = 13.sp, color = colors.textTertiary)
            }
        }
        if (line.pickLocation != null) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(ControlShape)
                    .background(colors.soft)
                    .padding(horizontal = 10.dp, vertical = 8.dp),
            ) {
                Icon(
                    painter = painterResource(R.drawable.ic_goodsout_location),
                    contentDescription = null,
                    tint = colors.link,
                    modifier = Modifier.size(16.dp),
                )
                Text(
                    text = stringResource(R.string.goodsout_pick_from, line.pickLocation, line.locationStock),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.link,
                )
            }
        }
        GoodsQuantityStepper(
            quantity = quantity,
            onQuantityChange = onQuantityChange,
            decreaseDescription = stringResource(R.string.goodsout_decrease_picked),
            increaseDescription = stringResource(R.string.goodsout_increase_picked),
            total = line.required,
        )
    }
}

@Preview(widthDp = 390, heightDp = 1480)
@Composable
private fun DeliveryNoteDetailScreenPreview() {
    AikuDroidTheme {
        DeliveryNoteDetailScreen(reference = "DN-24817")
    }
}

@Preview(widthDp = 390, heightDp = 1100)
@Composable
private fun DeliveryNoteDetailViewOnlyPreview() {
    AikuDroidTheme {
        val viewOnly = PreviewWarehouse.can.copy(pickAndPack = false, superviseGoodsOut = false)
        CompositionLocalProvider(LocalWarehouse provides PreviewWarehouse.copy(can = viewOnly)) {
            DeliveryNoteDetailScreen(reference = "DN-24817")
        }
    }
}
