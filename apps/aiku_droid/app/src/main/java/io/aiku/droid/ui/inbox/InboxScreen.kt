package io.aiku.droid.ui.inbox

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun InboxScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    var isAllRead by remember { mutableStateOf(false) }
    val unread = if (isAllRead) 0 else InboxSamples.today.count { it.isUnread } + InboxSamples.earlier.count { it.isUnread }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.tab_inbox),
                kicker = pluralStringResource(R.plurals.inbox_unread, unread, unread),
                isSample = true,
            )
        },
        activeTab = MainTab.Inbox,
        spacing = 10.dp,
        contentPadding = androidx.compose.foundation.layout.PaddingValues(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 16.dp),
    ) {
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
                    .clickable(role = Role.Button) { isAllRead = true }
                    .padding(horizontal = 4.dp, vertical = 12.dp),
            )
        }
        GroupLabel(text = stringResource(R.string.inbox_today))
        NotificationGroup(notifications = InboxSamples.today, isAllRead = isAllRead, onOpen = { navigator.open(it) })
        GroupLabel(text = stringResource(R.string.inbox_earlier))
        NotificationGroup(notifications = InboxSamples.earlier, isAllRead = isAllRead, onOpen = { navigator.open(it) })
    }
}

@Composable
private fun NotificationGroup(notifications: List<InboxNotification>, isAllRead: Boolean, onOpen: (Any) -> Unit) {
    ListGroup(modifier = Modifier.fakeData()) {
        notifications.forEachIndexed { index, notification ->
            if (index > 0) {
                RowDivider()
            }
            NotificationRow(notification = notification, isUnread = notification.isUnread && !isAllRead, onClick = { onOpen(notification.route) })
        }
    }
}

@Composable
private fun NotificationRow(notification: InboxNotification, isUnread: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val tone = colors.tone(notification.tone)
    val unreadDescription = stringResource(R.string.inbox_unread_dot)

    Row(
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(if (isUnread) colors.soft else colors.surface)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        NotificationIcon(icon = notification.icon, container = tone.container, content = tone.content)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = buildAnnotatedString {
                    append(notification.before)
                    withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(notification.reference) }
                    append(notification.after)
                },
                fontSize = 14.sp,
                color = colors.text,
            )
            Text(text = notification.time, fontSize = 12.sp, color = colors.textTertiary)
        }
        if (isUnread) {
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
private fun NotificationIcon(@DrawableRes icon: Int, container: androidx.compose.ui.graphics.Color, content: androidx.compose.ui.graphics.Color) {
    Box(contentAlignment = Alignment.Center, modifier = Modifier.size(36.dp).clip(CircleShape).background(container)) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(18.dp))
    }
}

internal data class InboxNotification(
    val before: String,
    val reference: String,
    val after: String,
    val time: String,
    @DrawableRes val icon: Int,
    val tone: Tone,
    val route: Any,
    val isUnread: Boolean,
)
