package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
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
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SwipeAction
import io.aiku.droid.ui.components.SwipeDirection
import io.aiku.droid.ui.components.SwipeRow
import io.aiku.droid.ui.components.SwipeStatusBar
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class FastPickStatus { ToPick, Picked, NotPicked }

@Composable
fun FastPickingScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val lines = GoodsOutSamples.fastPickLines
    val statuses = remember {
        mutableStateMapOf<Int, FastPickStatus>().apply {
            lines.forEach { put(it.id, if (it.id == GoodsOutSamples.FAST_PICK_DONE_ID) FastPickStatus.Picked else FastPickStatus.ToPick) }
        }
    }
    var openId by remember { mutableStateOf<Int?>(null) }
    var openSide by remember { mutableStateOf<SwipeDirection?>(null) }
    var lineSheetSkuCode by remember { mutableStateOf<String?>(null) }
    val done = statuses.values.count { it != FastPickStatus.ToPick }
    val left = lines.size - done
    val undoLabel = stringResource(R.string.goodsout_undo)

    fun setStatus(line: SampleFastPickLine, status: FastPickStatus, toastText: String) {
        val previous = statuses[line.id] ?: FastPickStatus.ToPick
        statuses[line.id] = status
        openId = null
        openSide = null
        toasts.show(toastText, ToastKind.Success, actionLabel = undoLabel, onAction = { statuses[line.id] = previous })
    }

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = null) {
        AppScreen(
            header = {
                SwipeProgressHeader(
                    kicker = stringResource(R.string.goodsout_fast_picking_kicker),
                    title = reference,
                    done = done,
                    total = lines.size,
                )
            },
            activeTab = null,
            showBar = false,
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp),
            spacing = 12.dp,
            footer = {
                if (left == 0) {
                    AikuButton(
                        text = stringResource(R.string.goodsout_fast_all_handled),
                        onClick = { navigator.switchSection(DeliveryNotePackingRoute(reference = reference)) },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                } else {
                    AikuButton(
                        text = pluralStringResource(R.plurals.goodsout_items_left, left, left),
                        onClick = {},
                        enabled = false,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
                    )
                }
            },
        ) {
            SwipeTip(text = stringResource(R.string.goodsout_fast_picking_tip))
            ListGroup(modifier = Modifier.fakeData()) {
                lines.forEachIndexed { index, line ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val status = statuses[line.id] ?: FastPickStatus.ToPick
                    val pickedText = stringResource(R.string.goodsout_fast_picked_toast, line.skuCode, line.quantity)
                    val notPickedText = stringResource(R.string.goodsout_fast_not_picked_toast, line.skuCode)
                    val (statusText, statusColor, barColor) = when (status) {
                        FastPickStatus.ToPick -> Triple(stringResource(R.string.goodsout_fast_to_pick), colors.textTertiary, colors.borderStrong)
                        FastPickStatus.Picked -> Triple(stringResource(R.string.goodsout_fast_picked_of, line.quantity, line.quantity), colors.success, colors.success)
                        FastPickStatus.NotPicked -> Triple(stringResource(R.string.goodsout_fast_not_picked_status), colors.warn, colors.warn)
                    }
                    SwipeRow(
                        openDirection = if (openId == line.id) openSide else null,
                        onOpenDirectionChange = { direction ->
                            openId = if (direction == null) null else line.id
                            openSide = direction
                        },
                        rightAction = SwipeAction(
                            label = stringResource(R.string.goodsout_fast_picked_action, line.quantity),
                            icon = R.drawable.ic_ui_check,
                            container = colors.success,
                            content = Color.White,
                            onClick = { setStatus(line, FastPickStatus.Picked, pickedText) },
                        ),
                        rightTrayColor = colors.success,
                        leftActions = listOf(
                            SwipeAction(
                                label = stringResource(R.string.goodsout_fast_partial),
                                icon = R.drawable.ic_ui_filter,
                                container = colors.primary,
                                content = colors.onPrimary,
                                onClick = {
                                    openId = null
                                    lineSheetSkuCode = line.skuCode
                                },
                            ),
                            SwipeAction(
                                label = stringResource(R.string.goodsout_fast_not_picked),
                                icon = R.drawable.ic_ui_warning,
                                container = colors.warn,
                                content = Color.White,
                                onClick = { setStatus(line, FastPickStatus.NotPicked, notPickedText) },
                            ),
                        ),
                        leftTrayColor = colors.warn,
                        onFullSwipeRight = { setStatus(line, FastPickStatus.Picked, pickedText) },
                        onFullSwipeLeft = { setStatus(line, FastPickStatus.NotPicked, notPickedText) },
                        background = if (status == FastPickStatus.NotPicked) colors.warnSoft else colors.surface,
                        onDragStarted = { if (openId != line.id) openId = null },
                    ) {
                        SwipeStatusBar(color = barColor)
                        ProductPhoto(code = line.skuCode)
                        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text(
                                    text = line.location,
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = colors.link,
                                    modifier = Modifier.clip(ChipShape).background(colors.soft).padding(horizontal = 6.dp, vertical = 2.dp),
                                )
                                Text(text = line.skuCode, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                            }
                            Text(text = line.name, fontSize = 13.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            Text(text = statusText, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = statusColor)
                        }
                        Text(text = stringResource(R.string.goodsout_times_quantity, line.quantity), fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.text)
                        MoreForLineButton(
                            description = stringResource(R.string.goodsout_more_for, line.skuCode),
                            onClick = { lineSheetSkuCode = line.skuCode },
                        )
                    }
                }
            }
            Text(
                text = stringResource(R.string.goodsout_fast_picking_caption),
                fontSize = 12.sp,
                color = colors.textTertiary,
                modifier = Modifier.align(Alignment.CenterHorizontally),
            )
        }
    }
    lineSheetSkuCode?.let { skuCode ->
        LineActionsSheet(reference = reference, skuCode = skuCode, onDismiss = { lineSheetSkuCode = null })
    }
}

@Composable
internal fun MoreForLineButton(description: String, onClick: () -> Unit) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(ControlShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_ui_more_dots),
            contentDescription = null,
            tint = LocalAikuColors.current.textTertiary,
            modifier = Modifier.size(22.dp),
        )
    }
}
