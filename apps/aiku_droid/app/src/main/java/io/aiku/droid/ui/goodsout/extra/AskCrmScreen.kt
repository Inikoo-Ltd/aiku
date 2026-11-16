package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.Image
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.scrollBy
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.runtime.withFrameNanos
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.LifecycleStartEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.ChatContextKind
import io.aiku.droid.data.api.ChatMessage
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.EmptyTone
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.rememberRemoteImage
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.dateTimeText
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.goodsout.problemText
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.flow.first

private val BubbleShape = RoundedCornerShape(12.dp)
private val MessageSpacing = 14.dp
private val ChatImageMaxHeight = 220.dp
private const val BUBBLE_WIDTH_FRACTION = 0.8f
private const val LOAD_OLDER_DISTANCE_PX = 48

@Composable
fun AskCrmScreen(chat: StaffChatViewModel, kind: ChatContextKind, reference: String) {
    val state by chat.state.collectAsStateWithLifecycle()

    LifecycleStartEffect(chat) {
        chat.onVisible()
        onStopOrDispose { chat.onHidden() }
    }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = chat::signInAgain)
        } else {
            AskCrmContent(chat = chat, state = state, kind = kind, reference = reference)
        }
    }
}

@Composable
private fun AskCrmContent(chat: StaffChatViewModel, state: StaffChatUiState, kind: ChatContextKind, reference: String) {
    val scrollState = rememberScrollState()
    val conversation = state.conversation
    val kicker = conversation?.contextLabel?.takeIf { it.isNotBlank() } ?: stringResource(
        when (kind) {
            ChatContextKind.DeliveryNote -> R.string.goodsout_extra_delivery_note_reference
            ChatContextKind.PickingSession -> R.string.goodsout_extra_session_kicker
        },
        reference,
    )

    ChatScrolling(scrollState = scrollState, state = state, onLoadOlder = chat::loadOlder)

    AppScreen(
        header = { AppHeader(title = stringResource(R.string.goodsout_extra_ask_crm), kicker = kicker) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        footer = conversation?.let { { ChatComposer(state = state, onDraftChange = chat::updateDraft, onSend = chat::send) } },
    ) {
        state.problem?.let { problem ->
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_extra_chat_failed), onRetry = chat::retry)
        }
        if (conversation == null) {
            if (state.problem == null) {
                SkeletonListCard(rows = 3)
            }
            return@AppScreen
        }
        conversation.participants.map { it.name }.filter { it.isNotBlank() }.takeIf { it.isNotEmpty() }?.let { names ->
            HintText(text = stringResource(R.string.goodsout_extra_chat_participants, names.joinToString(", ")))
        }
        if (state.isLoadingOlder) {
            LoadingMore()
        }
        if (state.messages.isEmpty()) {
            EmptyState(
                icon = R.drawable.ic_ui_chat,
                title = stringResource(R.string.goodsout_extra_chat_empty_title),
                body = stringResource(R.string.goodsout_extra_chat_empty_body),
                tone = EmptyTone.Info,
            )
        } else {
            ChatMessages(messages = state.messages, scrollState = scrollState)
        }
    }
}

@Composable
private fun ChatScrolling(scrollState: ScrollState, state: StaffChatUiState, onLoadOlder: () -> Unit) {
    val lastId = state.messages.lastOrNull()?.id
    val currentOnLoadOlder by rememberUpdatedState(onLoadOlder)
    var isAtLatest by remember { mutableStateOf(false) }

    LaunchedEffect(lastId) {
        if (lastId == null) {
            return@LaunchedEffect
        }
        withFrameNanos { }
        if (isAtLatest) scrollState.animateScrollTo(scrollState.maxValue) else scrollState.scrollTo(scrollState.maxValue)
        isAtLatest = true
    }
    LaunchedEffect(isAtLatest, state.hasOlder, state.isLoadingOlder, state.messages.firstOrNull()?.id) {
        if (isAtLatest && state.hasOlder && !state.isLoadingOlder) {
            snapshotFlow { scrollState.value <= LOAD_OLDER_DISTANCE_PX }.first { it }
            currentOnLoadOlder()
        }
    }
}

@Composable
private fun ChatMessages(messages: List<ChatMessage>, scrollState: ScrollState) {
    val heights = remember { mutableStateMapOf<Long, Int>() }
    val spacing = with(LocalDensity.current) { MessageSpacing.roundToPx() }
    val firstId = messages.first().id
    var shownFirstId by remember { mutableLongStateOf(firstId) }
    val currentMessages by rememberUpdatedState(messages)

    LaunchedEffect(firstId) {
        val previousFirst = shownFirstId
        shownFirstId = firstId
        if (firstId >= previousFirst) {
            return@LaunchedEffect
        }
        val added = currentMessages.filter { it.id < previousFirst }.map { it.id }
        val measured = snapshotFlow { added.map { heights[it] } }.first { sizes -> sizes.all { it != null } }
        scrollState.scrollBy(measured.sumOf { it ?: 0 }.toFloat() + spacing * added.size)
    }

    Column(verticalArrangement = Arrangement.spacedBy(MessageSpacing), modifier = Modifier.fillMaxWidth()) {
        messages.forEach { message ->
            ChatBubble(message = message, modifier = Modifier.onSizeChanged { heights[message.id] = it.height })
        }
    }
}

@Composable
private fun ChatComposer(state: StaffChatUiState, onDraftChange: (String) -> Unit, onSend: () -> Unit) {
    val problemTexts = GoodsOutProblem.entries.associateWith { problemText(it) }

    state.sendRefusal?.let { refusal ->
        InlineAlert(title = refusal.message.ifBlank { problemTexts.getValue(refusal.problem) }, tone = AlertTone.Danger)
    }
    Row(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.Bottom,
        modifier = Modifier.fillMaxWidth(),
    ) {
        PlainTextField(
            value = state.draft,
            onValueChange = onDraftChange,
            placeholder = stringResource(R.string.goodsout_extra_message_placeholder),
            isMultiLine = true,
            minHeight = 48.dp,
            modifier = Modifier.weight(1f),
        )
        AikuButton(
            text = stringResource(R.string.goodsout_extra_send),
            onClick = onSend,
            isLoading = state.isSending,
            height = 48.dp,
        )
    }
}

@Composable
private fun ChatBubble(message: ChatMessage, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val zone = organisationZone()
    val alignment = if (message.isMine) Alignment.End else Alignment.Start
    val image = rememberRemoteImage(message.imageUrl?.takeIf { !message.isMine })
    val body = message.body?.takeIf { it.isNotBlank() }

    Box(
        contentAlignment = if (message.isMine) Alignment.CenterEnd else Alignment.CenterStart,
        modifier = modifier.fillMaxWidth(),
    ) {
        Column(
            horizontalAlignment = alignment,
            verticalArrangement = Arrangement.spacedBy(3.dp),
            modifier = Modifier.fillMaxWidth(BUBBLE_WIDTH_FRACTION),
        ) {
            Text(
                text = listOfNotNull(message.user?.name?.takeIf { it.isNotBlank() }, dateTimeText(message.createdAt, zone)).joinToString(KICKER_SEPARATOR),
                fontSize = 11.sp,
                color = colors.textTertiary,
            )
            if (image != null) {
                Image(
                    bitmap = image,
                    contentDescription = stringResource(R.string.goodsout_extra_chat_image),
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.heightIn(max = ChatImageMaxHeight).clip(BubbleShape).border(1.dp, colors.border, BubbleShape),
                )
            }
            if (body != null) {
                Text(
                    text = body,
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
}
