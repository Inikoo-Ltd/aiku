package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.PreviewWarehouse
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class ReceiveLineState { Checked, Short, Receiving, Waiting }

private data class ReceiveLineSample(
    val code: String,
    val description: String,
    val expected: Int,
    val received: Int?,
    val state: ReceiveLineState,
)

private val receiveLinesSample = listOf(
    ReceiveLineSample("SKU-00733", "Bath bomb – rose 180g", expected = 48, received = 48, state = ReceiveLineState.Checked),
    ReceiveLineSample("SKU-00412", "Lavender essential oil 10ml", expected = 120, received = 108, state = ReceiveLineState.Short),
    ReceiveLineSample("SKU-01187", "Soy wax candle – vanilla", expected = 72, received = 72, state = ReceiveLineState.Receiving),
    ReceiveLineSample("SKU-00953", "Incense sticks – sandalwood", expected = 200, received = null, state = ReceiveLineState.Waiting),
    ReceiveLineSample("SKU-01402", "Eucalyptus essential oil 10ml", expected = 60, received = null, state = ReceiveLineState.Waiting),
)

private const val STOCK_DELIVERY_SUPPLIER_SAMPLE = "[Supplier name]"
private const val STOCK_DELIVERY_RECEIVED_SAMPLE = "Today 08:40"
private const val STOCK_DELIVERY_CHECKED_SAMPLE = 2
private const val STOCK_DELIVERY_ITEM_COUNT_SAMPLE = 6
private const val STOCK_DELIVERY_STATE_SAMPLE = 1

@Composable
fun StockDeliveryDetailScreen(reference: String) {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    var receivingQuantity by remember { mutableIntStateOf(72) }

    RequireAbility(isAllowed = can.viewGoodsIn, title = reference, activeTab = AppTab.GoodsIn) {
        GoodsDetailScaffold(
            header = { StockDeliveryHeader(reference = reference) },
            activeTab = AppTab.GoodsIn,
            bottomBar = if (can.bookIn) {
                {
                    GoodsScanActionButton(text = stringResource(R.string.goodsin_scan), onClick = { navigator.open(ScanCameraRoute) })
                    AikuButton(
                        text = stringResource(R.string.goodsin_confirm_next),
                        onClick = {},
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
            } else {
                null
            },
        ) {
            StockDeliveryStateCard()
            if (can.bookIn) {
                GoodsScanPanel(
                    title = stringResource(R.string.goodsin_scan_to_receive),
                    status = stringResource(R.string.goodsin_scanner_ready),
                    primaryAction = GoodsPanelAction(stringResource(R.string.goodsin_use_camera), R.drawable.ic_goodsin_camera) {
                        navigator.open(ScanCameraRoute)
                    },
                    secondaryAction = GoodsPanelAction(stringResource(R.string.goodsin_type_code), R.drawable.ic_goodsin_keyboard) {
                        navigator.open(ScanRoute)
                    },
                    modifier = Modifier.fakeData(),
                )
            }
            Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fakeData()) {
                GoodsSectionHeading(title = stringResource(R.string.goodsin_items), icon = R.drawable.ic_goodsin_items) {
                    Text(
                        text = goodsHighlightedCount(
                            text = pluralStringResource(R.plurals.goodsin_items_checked, STOCK_DELIVERY_CHECKED_SAMPLE, STOCK_DELIVERY_CHECKED_SAMPLE, STOCK_DELIVERY_ITEM_COUNT_SAMPLE),
                            count = STOCK_DELIVERY_CHECKED_SAMPLE,
                        ),
                        fontSize = 13.sp,
                        color = colors.textTertiary,
                    )
                }
                ListGroup {
                    receiveLinesSample.forEachIndexed { index, line ->
                        if (index > 0) {
                            RowDivider()
                        }
                        if (line.state == ReceiveLineState.Receiving && can.bookIn) {
                            ReceivingLine(
                                line = line,
                                quantity = receivingQuantity,
                                onQuantityChange = { receivingQuantity = it.coerceAtLeast(0) },
                            )
                        } else {
                            ReceiveLineRow(line = line)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun StockDeliveryHeader(reference: String) {
    GoodsDetailHeader(
        kicker = stringResource(R.string.goodsin_supplier_delivery),
        kickerIcon = R.drawable.ic_goodsin_stock_delivery,
        title = reference,
        tabs = listOf(
            GoodsDetailTab(label = stringResource(R.string.goodsin_supplier_delivery), icon = R.drawable.ic_goodsin_info),
            GoodsDetailTab(label = stringResource(R.string.goodsin_items), icon = R.drawable.ic_goodsin_items, isActive = true),
        ),
        trailingTabs = listOf(
            GoodsDetailTab(label = stringResource(R.string.goodsin_tab_attachments), icon = R.drawable.ic_goodsin_attachment, showLabel = false),
            GoodsDetailTab(label = stringResource(R.string.goodsin_tab_history), icon = R.drawable.ic_goodsin_history, showLabel = false),
            GoodsDetailTab(label = stringResource(R.string.goodsin_tab_data), icon = R.drawable.ic_goodsin_data, showLabel = false),
        ),
    )
}

@Composable
private fun StockDeliveryStateCard() {
    val steps = listOf(
        R.string.goodsin_state_dispatched,
        R.string.goodsin_state_received,
        R.string.goodsin_state_checked,
        R.string.goodsin_state_placed,
    ).map { stringResource(it) }

    AikuCard(modifier = Modifier.fakeData(), spacing = 14.dp) {
        GoodsStateStepper(steps = steps, currentIndex = STOCK_DELIVERY_STATE_SAMPLE)
        Column {
            RowDivider()
            Row(horizontalArrangement = Arrangement.spacedBy(16.dp), modifier = Modifier.padding(top = 12.dp)) {
                StockDeliveryFact(
                    label = stringResource(R.string.goodsin_supplier),
                    value = STOCK_DELIVERY_SUPPLIER_SAMPLE,
                    modifier = Modifier.weight(1f),
                )
                StockDeliveryFact(
                    label = stringResource(R.string.goodsin_received_at),
                    value = STOCK_DELIVERY_RECEIVED_SAMPLE,
                    modifier = Modifier.weight(1f),
                )
            }
        }
    }
}

@Composable
private fun StockDeliveryFact(label: String, value: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(2.dp), modifier = modifier) {
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary)
        Text(
            text = value,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.text,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun ReceiveLineRow(line: ReceiveLineSample) {
    val colors = LocalAikuColors.current
    val receivedText = line.received?.toString() ?: stringResource(R.string.goodsin_quantity_missing)
    val quantity = stringResource(R.string.goodsin_quantity_of, receivedText, line.expected)
    val isResolved = line.state == ReceiveLineState.Checked || line.state == ReceiveLineState.Short

    GoodsLineRow(
        code = line.code,
        description = when (line.state) {
            ReceiveLineState.Short -> stringResource(R.string.goodsin_short_by, line.expected - (line.received ?: 0))
            else -> line.description
        },
        quantity = quantity,
        leading = { ReceiveStatusCircle(state = line.state) },
        descriptionColor = if (line.state == ReceiveLineState.Short) colors.warn else colors.textTertiary,
        quantityColor = if (isResolved) colors.text else colors.textTertiary,
        isQuantityStrong = isResolved,
        background = when (line.state) {
            ReceiveLineState.Checked -> colors.successSoft
            ReceiveLineState.Short -> colors.warnSoft
            else -> colors.surface
        },
    )
}

@Composable
private fun ReceiveStatusCircle(state: ReceiveLineState) {
    val colors = LocalAikuColors.current
    val circle = Modifier
        .size(28.dp)
        .clip(CircleShape)

    when (state) {
        ReceiveLineState.Checked -> Box(contentAlignment = Alignment.Center, modifier = circle.background(colors.success)) {
            Icon(
                painter = painterResource(R.drawable.ic_goodsin_check),
                contentDescription = null,
                tint = colors.surface,
                modifier = Modifier.size(14.dp),
            )
        }
        ReceiveLineState.Short -> Box(contentAlignment = Alignment.Center, modifier = circle.background(colors.warn)) {
            Icon(
                painter = painterResource(R.drawable.ic_goodsin_exclamation),
                contentDescription = null,
                tint = colors.surface,
                modifier = Modifier.size(14.dp),
            )
        }
        ReceiveLineState.Receiving, ReceiveLineState.Waiting -> Box(
            modifier = circle.border(1.5.dp, colors.borderStrong, CircleShape),
        )
    }
}

@Composable
private fun ReceivingLine(line: ReceiveLineSample, quantity: Int, onQuantityChange: (Int) -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .goodsActiveEdge(colors.primary)
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = line.code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(
                text = stringResource(R.string.goodsin_expected, line.description, line.expected),
                fontSize = 13.sp,
                color = colors.textTertiary,
            )
        }
        GoodsQuantityStepper(
            quantity = quantity,
            onQuantityChange = onQuantityChange,
            decreaseDescription = stringResource(R.string.goodsin_decrease_received),
            increaseDescription = stringResource(R.string.goodsin_increase_received),
        )
    }
}

@Preview(widthDp = 390, heightDp = 1480)
@Composable
private fun StockDeliveryDetailScreenPreview() {
    AikuDroidTheme {
        StockDeliveryDetailScreen(reference = "SD-1093")
    }
}

@Preview(widthDp = 390, heightDp = 1100)
@Composable
private fun StockDeliveryDetailViewOnlyPreview() {
    AikuDroidTheme {
        CompositionLocalProvider(LocalWarehouse provides PreviewWarehouse.copy(can = PreviewWarehouse.can.copy(bookIn = false))) {
            StockDeliveryDetailScreen(reference = "SD-1093")
        }
    }
}
