package io.aiku.droid.ui.goodsin

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.StockDeliveryDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class GoodsInBacklogStage(@StringRes val title: Int, @StringRes val actionLabel: Int?) {
    Received(R.string.goodsin_backlog_received, R.string.goodsin_action_check),
    Checked(R.string.goodsin_backlog_checked, R.string.goodsin_action_place),
    Dispatched(R.string.goodsin_backlog_dispatched, null),
}

private data class GoodsInBacklogDelivery(val reference: String, val summary: String)

private data class GoodsInBacklogGroup(val stage: GoodsInBacklogStage, val deliveries: List<GoodsInBacklogDelivery>)

private val goodsInBacklogSample = listOf(
    GoodsInBacklogGroup(
        stage = GoodsInBacklogStage.Received,
        deliveries = listOf(
            GoodsInBacklogDelivery("SD-1093", "6 items · Goods-in bay 2 · 08:40"),
            GoodsInBacklogDelivery("SD-1088", "3 items · Goods-in bay 1 · yesterday"),
        ),
    ),
    GoodsInBacklogGroup(
        stage = GoodsInBacklogStage.Checked,
        deliveries = listOf(GoodsInBacklogDelivery("SD-1091", "14 items · put away to Area A, B")),
    ),
    GoodsInBacklogGroup(
        stage = GoodsInBacklogStage.Dispatched,
        deliveries = listOf(
            GoodsInBacklogDelivery("SD-1096", "22 items · expected 11 Nov"),
            GoodsInBacklogDelivery("SD-1097", "8 items · expected 12 Nov"),
            GoodsInBacklogDelivery("SD-1099", "40 items · expected 14 Nov"),
        ),
    ),
)

@Composable
fun GoodsInBacklogScreen() {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val title = stringResource(R.string.goodsin_title)

    RequireAbility(isAllowed = can.viewGoodsIn, title = title, activeTab = AppTab.GoodsIn) {
        AppScreen(
            header = { AppHeader(title = title, below = { GoodsInSectionTabs(active = GoodsInSection.Backlog) }) },
            activeTab = AppTab.GoodsIn,
            spacing = 14.dp,
        ) {
            Text(
                text = stringResource(R.string.goodsin_backlog_intro),
                fontSize = 13.sp,
                color = colors.textTertiary,
                modifier = Modifier.padding(horizontal = 4.dp),
            )
            goodsInBacklogSample.forEach { group ->
                GoodsInBacklogSection(
                    group = group,
                    showActions = can.bookIn,
                    onOpen = { reference -> navigator.open(StockDeliveryDetailRoute(reference)) },
                )
            }
        }
    }
}

@Composable
private fun GoodsInBacklogSection(group: GoodsInBacklogGroup, showActions: Boolean, onOpen: (String) -> Unit) {
    val colors = LocalAikuColors.current
    val dotColor = when (group.stage) {
        GoodsInBacklogStage.Received -> colors.success
        GoodsInBacklogStage.Checked -> colors.primary
        GoodsInBacklogStage.Dispatched -> colors.faint
    }
    val actionLabel = group.stage.actionLabel?.takeIf { showActions }?.let { stringResource(it) }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData()) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 4.dp),
        ) {
            Box(
                modifier = Modifier
                    .size(10.dp)
                    .clip(CircleShape)
                    .background(dotColor),
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text(
                text = stringResource(group.stage.title),
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            Text(text = group.deliveries.size.toString(), fontSize = 13.sp, color = colors.textTertiary)
        }
        ListGroup {
            group.deliveries.forEachIndexed { index, delivery ->
                if (index > 0) {
                    RowDivider()
                }
                GoodsQueueRow(
                    title = delivery.reference,
                    subtitle = delivery.summary,
                    actionLabel = actionLabel,
                    onClick = { onOpen(delivery.reference) },
                )
            }
        }
    }
}

@Preview(widthDp = 390, heightDp = 1000)
@Composable
private fun GoodsInBacklogScreenPreview() {
    AikuDroidTheme {
        GoodsInBacklogScreen()
    }
}
