package io.aiku.droid.ui.inbox

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshDefaults
import androidx.compose.material3.pulltorefresh.pullToRefresh
import androidx.compose.material3.pulltorefresh.rememberPullToRefreshState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.InboxNotification
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.LastUpdatedNote
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.mywork.LocalWorkBadges
import io.aiku.droid.ui.mywork.elapsedSince
import io.aiku.droid.ui.mywork.elapsedText
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant
import java.time.ZoneId

@Composable
fun InboxScreen(inbox: InboxViewModel) {
    val state by inbox.state.collectAsStateWithLifecycle()
    val badges = LocalWorkBadges.current
    val opener = LocalRecordOpener.current
    val warehouseIds = LocalWarehouses.current.map { it.id }
    val toasts = LocalToasts.current
    val markAllFailed = stringResource(R.string.inbox_mark_all_failed)
    var details by remember { mutableStateOf<InboxNotification?>(null) }

    LifecycleEventEffect(Lifecycle.Event.ON_START) { inbox.onStart() }

    LaunchedEffect(state.unread) {
        state.unread?.let(badges::showInboxUnread)
    }

    LaunchedEffect(state.markAllProblem) {
        if (state.markAllProblem != null) {
            toasts.show(markAllFailed, ToastKind.Error)
            inbox.markAllProblemShown()
        }
    }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = inbox::signInAgain)
        return
    }

    InboxContent(
        state = state,
        actions = InboxActions(
            onRefresh = inbox::refresh,
            onRetry = inbox::retry,
            onLoadMore = inbox::loadMore,
            onMarkAllRead = inbox::markAllRead,
            onOpen = { notification ->
                inbox.markRead(notification)
                val opening = notification.target?.openingIn(warehouseIds)
                if (opening != null) {
                    opener.open(opening)
                } else {
                    details = notification
                }
            },
        ),
    )

    details?.let { notification ->
        NotificationDetailsSheet(notification = notification, onDismiss = { details = null })
    }
}

private class InboxActions(
    val onRefresh: () -> Unit,
    val onRetry: () -> Unit,
    val onLoadMore: () -> Unit,
    val onMarkAllRead: () -> Unit,
    val onOpen: (InboxNotification) -> Unit,
)

@Composable
private fun InboxContent(state: InboxUiState, actions: InboxActions) {
    val colors = LocalAikuColors.current
    val refreshState = rememberPullToRefreshState()
    val scrollState = rememberScrollState()
    var headerHeight by remember { mutableIntStateOf(0) }
    val now = remember(state.notifications) { Instant.now() }
    val zone = remember { ZoneId.systemDefault() }
    val sections = remember(state.notifications, now) { inboxSections(state.notifications, now.atZone(zone).toLocalDate(), zone) }

    LoadMoreOnScroll(scrollState = scrollState, itemCount = state.notifications.size, canLoadMore = state.canLoadMore, onLoadMore = actions.onLoadMore)

    Box(modifier = Modifier.fillMaxSize().pullToRefresh(isRefreshing = state.isRefreshing, state = refreshState, onRefresh = actions.onRefresh)) {
        AppScreen(
            header = {
                AppHeader(
                    title = stringResource(R.string.tab_inbox),
                    kicker = if (state.hasLoaded) pluralStringResource(R.plurals.inbox_unread, state.unreadCount, state.unreadCount) else null,
                    modifier = Modifier.onSizeChanged { headerHeight = it.height },
                )
            },
            activeTab = MainTab.Inbox,
            spacing = 10.dp,
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 16.dp),
            scrollState = scrollState,
        ) {
            state.firstPageProblem?.let { problem ->
                GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.inbox_failed), onRetry = actions.onRetry)
            }
            state.lastUpdated?.let { updatedAt -> LastUpdatedNote(updatedAt = updatedAt) }
            when {
                state.notifications.isNotEmpty() -> {
                    MarkAllReadLink(onClick = actions.onMarkAllRead)
                    if (sections.today.isNotEmpty()) {
                        GroupLabel(text = stringResource(R.string.inbox_today))
                        NotificationGroup(notifications = sections.today, now = now, onOpen = actions.onOpen)
                    }
                    if (sections.earlier.isNotEmpty()) {
                        GroupLabel(text = stringResource(R.string.inbox_earlier))
                        NotificationGroup(notifications = sections.earlier, now = now, onOpen = actions.onOpen)
                    }
                    state.nextPageProblem?.let { problem ->
                        GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.inbox_failed), onRetry = actions.onRetry)
                    }
                    if (state.isLoadingMore) {
                        LoadingMore()
                    }
                }
                state.isEmpty -> EmptyState(
                    icon = R.drawable.ic_ui_inbox,
                    title = stringResource(R.string.inbox_empty_title),
                    body = stringResource(R.string.inbox_empty_body),
                )
                state.problem == null -> SkeletonListCard(rows = 4)
            }
        }
        PullToRefreshDefaults.Indicator(
            state = refreshState,
            isRefreshing = state.isRefreshing,
            containerColor = colors.surface,
            color = colors.primary,
            modifier = Modifier.align(Alignment.TopCenter).offset { IntOffset(0, headerHeight) },
        )
    }
}

@Composable
private fun MarkAllReadLink(onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
        Text(
            text = stringResource(R.string.inbox_mark_all_read),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.text,
            textDecoration = TextDecoration.Underline,
            modifier = Modifier
                .heightIn(min = 44.dp)
                .clip(ChipShape)
                .clickable(role = Role.Button, onClick = onClick)
                .padding(horizontal = 4.dp, vertical = 12.dp),
        )
    }
}

@Composable
private fun NotificationGroup(notifications: List<InboxNotification>, now: Instant, onOpen: (InboxNotification) -> Unit) {
    ListGroup {
        notifications.forEachIndexed { index, notification ->
            if (index > 0) {
                RowDivider()
            }
            NotificationRow(notification = notification, now = now, onClick = { onOpen(notification) })
        }
    }
}

@Composable
private fun NotificationRow(notification: InboxNotification, now: Instant, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val look = inboxLook(notification.kind, notification.event)
    val tone = colors.inboxIconColors(look.tone, notification.isUnread)
    val unreadDescription = stringResource(R.string.inbox_unread_dot)
    val headline = notification.title.ifBlank { notification.body }
    val detail = notification.body.takeIf { notification.title.isNotBlank() && it.isNotBlank() }

    Row(
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.inboxRowBackground(notification.isUnread))
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        NotificationIcon(icon = look.icon, container = tone.container, content = tone.content)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = withBoldReference(headline, notification.target?.reference), fontSize = 14.sp, color = colors.text)
            if (detail != null) {
                Text(text = detail, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textSecondary, maxLines = 2, overflow = TextOverflow.Ellipsis)
            }
            elapsedSince(notification.createdAt, now)?.let { elapsed ->
                Text(text = elapsedText(elapsed), fontSize = 12.sp, color = colors.textTertiary)
            }
        }
        if (notification.isUnread) {
            Box(
                modifier = Modifier
                    .padding(top = 4.dp)
                    .size(10.dp)
                    .clip(CircleShape)
                    .background(colors.primary)
                    .semantics { contentDescription = unreadDescription },
            )
        }
    }
}

@Composable
private fun NotificationIcon(@DrawableRes icon: Int, container: Color, content: Color) {
    Box(contentAlignment = Alignment.Center, modifier = Modifier.size(36.dp).clip(CircleShape).background(container)) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(18.dp))
    }
}

@Composable
private fun NotificationDetailsSheet(notification: InboxNotification, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val now = remember(notification) { Instant.now() }
    val look = inboxLook(notification.kind, notification.event)

    AikuBottomSheet(
        title = notification.title.ifBlank { stringResource(R.string.tab_inbox) },
        subtitle = elapsedSince(notification.createdAt, now)?.let { elapsedText(it) },
        icon = look.icon,
        onDismiss = onDismiss,
    ) {
        if (notification.body.isNotBlank()) {
            Text(text = notification.body, fontSize = 15.sp, lineHeight = 22.sp, color = colors.text)
        }
    }
}

private fun withBoldReference(text: String, reference: String?): AnnotatedString = buildAnnotatedString {
    val start = reference?.takeIf { it.isNotBlank() }?.let { text.indexOf(it) } ?: -1
    if (reference == null || start < 0) {
        append(text)
        return@buildAnnotatedString
    }
    append(text.substring(0, start))
    withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(reference) }
    append(text.substring(start + reference.length))
}
