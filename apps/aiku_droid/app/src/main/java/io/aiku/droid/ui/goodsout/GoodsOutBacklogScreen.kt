package io.aiku.droid.ui.goodsout

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
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
import io.aiku.droid.ui.goodsin.GoodsQueueRow
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.DeliveryNoteDetailRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class GoodsOutStage(@StringRes val label: Int, @DrawableRes val icon: Int) {
    Unassigned(R.string.goodsout_state_unassigned, R.drawable.ic_goodsout_queued),
    Queued(R.string.goodsout_state_queued, R.drawable.ic_goodsout_queued),
    Handling(R.string.goodsout_state_handling, R.drawable.ic_goodsout_handling),
    HandlingBlocked(R.string.goodsout_state_handling_blocked, R.drawable.ic_goodsout_handling),
    Packed(R.string.goodsout_state_packed, R.drawable.ic_goodsout_packed),
    Finalised(R.string.goodsout_state_finalised_waiting, R.drawable.ic_goodsout_packed),
}

private data class GoodsOutStageCount(val stage: GoodsOutStage, val count: Int)

private data class GoodsOutQueueSample(val reference: String, val summary: String, val isStarted: Boolean)

private val goodsOutPipelineSample = listOf(
    GoodsOutStageCount(GoodsOutStage.Unassigned, 12),
    GoodsOutStageCount(GoodsOutStage.Queued, 36),
    GoodsOutStageCount(GoodsOutStage.Handling, 16),
    GoodsOutStageCount(GoodsOutStage.HandlingBlocked, 2),
    GoodsOutStageCount(GoodsOutStage.Packed, 23),
    GoodsOutStageCount(GoodsOutStage.Finalised, 9),
)

private val goodsOutQueueSample = listOf(
    GoodsOutQueueSample("DN-24817", "Handling · 9 of 14 picked", isStarted = true),
    GoodsOutQueueSample("DN-24820", "In Queue · 4 items", isStarted = false),
)

@Composable
fun GoodsOutBacklogScreen() {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val title = stringResource(R.string.goodsout_title)

    RequireAbility(isAllowed = can.viewGoodsOut, title = title, activeTab = AppTab.GoodsOut) {
        AppScreen(
            header = { AppHeader(title = title, below = { GoodsOutSectionTabs(active = GoodsOutSection.Backlog) }) },
            activeTab = AppTab.GoodsOut,
            spacing = 14.dp,
        ) {
            ListGroup(modifier = Modifier.fakeData()) {
                goodsOutPipelineSample.forEachIndexed { index, stageCount ->
                    if (index > 0) {
                        RowDivider()
                    }
                    GoodsOutStageRow(stageCount = stageCount, onClick = { navigator.open(DeliveryNotesRoute) })
                }
            }
            if (can.pickAndPack) {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData()) {
                    Text(
                        text = stringResource(R.string.goodsout_my_queue),
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = colors.text,
                        modifier = Modifier.padding(horizontal = 4.dp),
                    )
                    ListGroup {
                        goodsOutQueueSample.forEachIndexed { index, note ->
                            if (index > 0) {
                                RowDivider()
                            }
                            GoodsQueueRow(
                                title = note.reference,
                                subtitle = note.summary,
                                actionLabel = stringResource(if (note.isStarted) R.string.goodsout_action_continue else R.string.goodsout_action_start),
                                onClick = { navigator.open(DeliveryNoteDetailRoute(note.reference)) },
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun GoodsOutStageRow(stageCount: GoodsOutStageCount, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val isBlocked = stageCount.stage == GoodsOutStage.HandlingBlocked
    val iconTint = when (stageCount.stage) {
        GoodsOutStage.Queued -> colors.lime
        GoodsOutStage.HandlingBlocked -> colors.danger
        else -> colors.textQuaternary
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 52.dp)
            .background(if (isBlocked) colors.dangerSoft else Color.Transparent)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp),
    ) {
        Icon(
            painter = painterResource(stageCount.stage.icon),
            contentDescription = null,
            tint = iconTint,
            modifier = Modifier.size(20.dp),
        )
        Text(
            text = stringResource(stageCount.stage.label),
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (isBlocked) colors.dangerInk else colors.text,
            modifier = Modifier.weight(1f),
        )
        Text(
            text = stageCount.count.toString(),
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            color = if (isBlocked) colors.danger else colors.text,
        )
    }
}

@Preview(widthDp = 390, heightDp = 1000)
@Composable
private fun GoodsOutBacklogScreenPreview() {
    AikuDroidTheme {
        GoodsOutBacklogScreen()
    }
}
