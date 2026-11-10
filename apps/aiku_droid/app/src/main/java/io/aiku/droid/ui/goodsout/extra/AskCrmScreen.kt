package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

private val BubbleShape = RoundedCornerShape(12.dp)
private const val BUBBLE_WIDTH_FRACTION = 0.8f

@Composable
fun AskCrmScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AskCrmContent(reference = reference)
    }
}

@Composable
private fun AskCrmContent(reference: String) {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    val staffName = LocalStaff.current.displayName
    val messages = remember { mutableStateListOf(*GoodsOutExtraSamples.chatMessages.toTypedArray()) }
    var draft by remember { mutableStateOf("") }
    val justNow = stringResource(R.string.goodsout_extra_just_now)
    val taskAddedMessage = stringResource(R.string.goodsout_extra_task_added)
    val sendMessage: () -> Unit = {
        if (draft.isNotBlank()) {
            messages.add(SampleChatMessage(isMine = true, author = staffName, time = justNow, text = draft.trim()))
            draft = ""
        }
    }

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = stringResource(R.string.goodsout_extra_ask_crm),
                kicker = listOf(
                    stringResource(R.string.goodsout_extra_delivery_note_reference, reference),
                    stringResource(R.string.dn_state_waiting),
                ).joinToString(KICKER_SEPARATOR),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = if (can.pickAndPack) {
            {
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    PlainTextField(
                        value = draft,
                        onValueChange = { draft = it },
                        placeholder = stringResource(R.string.goodsout_extra_message_placeholder),
                        minHeight = 48.dp,
                        modifier = Modifier.weight(1f),
                    )
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier
                            .height(48.dp)
                            .clip(ControlShape)
                            .background(colors.primary)
                            .clickable(role = Role.Button, onClick = sendMessage)
                            .padding(horizontal = 16.dp),
                    ) {
                        Text(text = stringResource(R.string.goodsout_extra_send), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.onPrimary)
                    }
                }
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_add_task_for_note),
                    onClick = { toasts.show(taskAddedMessage, ToastKind.Success) },
                    style = ButtonStyle.Outline,
                    icon = R.drawable.ic_feedback_check,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        } else {
            null
        },
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(14.dp), modifier = Modifier.fillMaxWidth().fakeData()) {
            messages.forEach { message -> ChatBubble(message = message) }
            ListGroup {
                ExtraRow(
                    title = stringResource(R.string.goodsout_extra_task_title, GoodsOutExtraSamples.CHAT_TASK),
                    subtitle = stringResource(R.string.goodsout_extra_task_linked, GoodsOutExtraSamples.CHAT_TASK_ASSIGNEE),
                    chip = stringResource(R.string.goodsout_extra_task_open),
                    chipTone = Tone.Primary,
                    leading = { ExtraIconTile(icon = R.drawable.ic_feedback_check, tone = Tone.Primary) },
                )
            }
        }
    }
}

@Composable
private fun ChatBubble(message: SampleChatMessage) {
    val colors = LocalAikuColors.current
    val alignment = if (message.isMine) Alignment.End else Alignment.Start

    Box(
        contentAlignment = if (message.isMine) Alignment.CenterEnd else Alignment.CenterStart,
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(
            horizontalAlignment = alignment,
            verticalArrangement = Arrangement.spacedBy(3.dp),
            modifier = Modifier.fillMaxWidth(BUBBLE_WIDTH_FRACTION),
        ) {
            Text(
                text = listOf(message.author, message.time).joinToString(KICKER_SEPARATOR),
                fontSize = 11.sp,
                color = colors.textTertiary,
            )
            Text(
                text = message.text,
                fontSize = 14.sp,
                lineHeight = 20.sp,
                color = if (message.isMine) colors.onPrimary else colors.text,
                modifier = Modifier
                    .clip(BubbleShape)
                    .background(if (message.isMine) colors.primary else colors.surface)
                    .then(if (message.isMine) Modifier else Modifier.border(1.dp, colors.border, BubbleShape))
                    .padding(horizontal = 12.dp, vertical = 10.dp),
            )
        }
    }
}
