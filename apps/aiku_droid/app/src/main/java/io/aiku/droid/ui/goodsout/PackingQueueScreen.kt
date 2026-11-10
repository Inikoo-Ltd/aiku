package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SwipeAction
import io.aiku.droid.ui.components.SwipeDirection
import io.aiku.droid.ui.components.SwipeRow
import io.aiku.droid.ui.components.SwipeStatusBar
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AddShipmentRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class PackQueueStatus { ToPack, Packed, Waiting }

@Composable
fun PackingQueueScreen() {
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val notes = GoodsOutSamples.packQueue
    val statuses = remember { mutableStateMapOf<Int, PackQueueStatus>().apply { notes.forEach { put(it.id, PackQueueStatus.ToPack) } } }
    var openId by remember { mutableStateOf<Int?>(null) }
    var openSide by remember { mutableStateOf<SwipeDirection?>(null) }
    val done = statuses.values.count { it != PackQueueStatus.ToPack }
    val packedNotes = notes.filter { statuses[it.id] == PackQueueStatus.Packed }
    val undoLabel = stringResource(R.string.goodsout_undo)
    val suggestedLabel = stringResource(R.string.goodsout_pack_queue_suggested)

    fun setStatus(note: SamplePackQueueNote, status: PackQueueStatus, toastText: String) {
        val previous = statuses[note.id] ?: PackQueueStatus.ToPack
        statuses[note.id] = status
        openId = null
        openSide = null
        toasts.show(toastText, ToastKind.Success, actionLabel = undoLabel, onAction = { statuses[note.id] = previous })
    }

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = null) {
        AppScreen(
            header = {
                SwipeProgressHeader(
                    kicker = stringResource(R.string.goodsout_pack_queue_kicker),
                    title = stringResource(R.string.goodsout_pack_queue_title),
                    done = done,
                    total = notes.size,
                )
            },
            activeTab = null,
            showBar = false,
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp),
            spacing = 12.dp,
            footer = {
                val firstPacked = packedNotes.firstOrNull()
                if (firstPacked != null) {
                    AikuButton(
                        text = pluralStringResource(R.plurals.goodsout_create_shipments, packedNotes.size, packedNotes.size),
                        onClick = { navigator.open(AddShipmentRoute(reference = firstPacked.reference)) },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                } else {
                    AikuButton(
                        text = stringResource(R.string.goodsout_pack_queue_swipe_hint),
                        onClick = {},
                        enabled = false,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            },
        ) {
            SwipeTip(text = stringResource(R.string.goodsout_pack_queue_tip))
            ListGroup(modifier = Modifier.fakeData()) {
                notes.forEachIndexed { index, note ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val status = statuses[note.id] ?: PackQueueStatus.ToPack
                    val packedText = stringResource(R.string.goodsout_pack_queue_packed_toast, note.reference, note.box)
                    val waitingText = stringResource(R.string.goodsout_pack_queue_waiting_toast, note.reference)
                    val (statusText, statusColor, barColor) = when (status) {
                        PackQueueStatus.ToPack -> Triple(stringResource(R.string.goodsout_pack_queue_ready), colors.textTertiary, colors.borderStrong)
                        PackQueueStatus.Packed -> Triple(stringResource(R.string.goodsout_pack_queue_packed_status, note.box, note.weight), colors.success, colors.success)
                        PackQueueStatus.Waiting -> Triple(stringResource(R.string.goodsout_pack_queue_waiting_status), colors.danger, colors.danger)
                    }
                    SwipeRow(
                        openDirection = if (openId == note.id) openSide else null,
                        onOpenDirectionChange = { direction ->
                            openId = if (direction == null) null else note.id
                            openSide = direction
                        },
                        rightAction = SwipeAction(
                            label = stringResource(R.string.goodsout_pack_queue_packed_action, note.box),
                            icon = R.drawable.ic_ui_packed,
                            container = colors.success,
                            content = Color.White,
                            onClick = { setStatus(note, PackQueueStatus.Packed, packedText) },
                        ),
                        rightTrayColor = colors.success,
                        leftActions = listOf(
                            SwipeAction(
                                label = stringResource(R.string.goodsout_pack_queue_other_box),
                                icon = R.drawable.ic_ui_box,
                                container = colors.primary,
                                content = colors.onPrimary,
                                onClick = {
                                    openId = null
                                    navigator.open(DeliveryNotePackingRoute(reference = note.reference))
                                },
                            ),
                            SwipeAction(
                                label = stringResource(R.string.goodsout_pack_queue_waiting),
                                icon = R.drawable.ic_ui_handling,
                                container = colors.danger,
                                content = Color.White,
                                onClick = { setStatus(note, PackQueueStatus.Waiting, waitingText) },
                            ),
                        ),
                        leftTrayColor = colors.danger,
                        onFullSwipeRight = { setStatus(note, PackQueueStatus.Packed, packedText) },
                        onFullSwipeLeft = { setStatus(note, PackQueueStatus.Waiting, waitingText) },
                        background = if (status == PackQueueStatus.Waiting) colors.dangerSoft else colors.surface,
                        minHeight = 76.dp,
                        onDragStarted = { if (openId != note.id) openId = null },
                    ) {
                        SwipeStatusBar(color = barColor)
                        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Text(text = note.reference, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                                Text(text = note.items, fontSize = 12.sp, color = colors.textTertiary)
                            }
                            Text(
                                text = buildAnnotatedString {
                                    append(suggestedLabel)
                                    append(" ")
                                    withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(note.box) }
                                    append(" · ")
                                    append(note.weight)
                                },
                                fontSize = 13.sp,
                                color = colors.textSecondary,
                            )
                            Text(text = statusText, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = statusColor)
                        }
                        MoreForLineButton(
                            description = stringResource(R.string.goodsout_pack_queue_open, note.reference),
                            onClick = { navigator.open(DeliveryNotePackingRoute(reference = note.reference)) },
                        )
                    }
                }
            }
            Text(
                text = stringResource(R.string.goodsout_pack_queue_caption),
                fontSize = 12.sp,
                color = colors.textTertiary,
                modifier = Modifier.align(Alignment.CenterHorizontally),
            )
        }
    }
}
