package io.aiku.droid.ui.notifications

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
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
import io.aiku.droid.ui.navigation.DeliveryNoteDetailRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationDetailRoute
import io.aiku.droid.ui.navigation.SkuDetailRoute
import io.aiku.droid.ui.navigation.StockDeliveryDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private data class SampleNotification(
    val textBefore: String,
    val reference: String,
    val textAfter: String,
    val time: String,
    @DrawableRes val icon: Int,
    val tone: Tone,
    val route: Any,
    val isUnread: Boolean = false,
)

private val SampleTodayNotifications = listOf(
    SampleNotification(
        textBefore = "",
        reference = "DN-24820",
        textAfter = " was assigned to you",
        time = "5 min ago",
        icon = R.drawable.ic_dashboard_delivery_note,
        tone = Tone.Primary,
        route = DeliveryNoteDetailRoute("DN-24820"),
        isUnread = true,
    ),
    SampleNotification(
        textBefore = "",
        reference = "SD-1093",
        textAfter = " arrived at Goods-in bay 2",
        time = "1 h ago",
        icon = R.drawable.ic_section_goods_in,
        tone = Tone.Success,
        route = StockDeliveryDetailRoute("SD-1093"),
        isUnread = true,
    ),
    SampleNotification(
        textBefore = "Stock audit requested for ",
        reference = "A-12-03",
        textAfter = "",
        time = "2 h ago",
        icon = R.drawable.ic_notifications_audit,
        tone = Tone.Warn,
        route = LocationDetailRoute("A-12-03"),
        isUnread = true,
    ),
)

private val SampleEarlierNotifications = listOf(
    SampleNotification(
        textBefore = "",
        reference = "SKU-01402",
        textAfter = " is out of stock",
        time = "Yesterday",
        icon = R.drawable.ic_alert_triangle,
        tone = Tone.Danger,
        route = SkuDetailRoute("SKU-01402"),
    ),
    SampleNotification(
        textBefore = "",
        reference = "DN-24790",
        textAfter = " dispatched",
        time = "Yesterday",
        icon = R.drawable.ic_dashboard_dispatched,
        tone = Tone.Neutral,
        route = DeliveryNoteDetailRoute("DN-24790"),
    ),
)

@Composable
fun NotificationsScreen() {
    val colors = LocalAikuColors.current

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.notifications_title),
                kicker = null,
                showMenu = false,
            )
        },
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 16.dp),
        spacing = 10.dp,
    ) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .heightIn(min = 44.dp)
                    .clip(ChipShape)
                    .clickable(role = Role.Button) {}
                    .padding(horizontal = 6.dp),
            ) {
                Text(
                    text = stringResource(R.string.notifications_mark_all_read),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.text,
                )
            }
        }
        GroupLabel(text = stringResource(R.string.notifications_today))
        NotificationGroup(SampleTodayNotifications)
        GroupLabel(text = stringResource(R.string.notifications_earlier), modifier = Modifier.padding(top = 8.dp))
        NotificationGroup(SampleEarlierNotifications)
    }
}

@Composable
private fun NotificationGroup(notifications: List<SampleNotification>) {
    val navigator = LocalAppNavigator.current

    ListGroup(modifier = Modifier.fakeData()) {
        notifications.forEachIndexed { index, notification ->
            if (index > 0) {
                RowDivider()
            }
            NotificationRow(notification = notification, onClick = { navigator.open(notification.route) })
        }
    }
}

@Composable
private fun NotificationRow(notification: SampleNotification, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val toneColors = colors.tone(notification.tone)
    val unreadLabel = stringResource(R.string.notifications_unread)

    Row(
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .then(if (notification.isUnread) Modifier.background(colors.soft) else Modifier)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(36.dp)
                .clip(CircleShape)
                .background(toneColors.container),
        ) {
            Icon(
                painter = painterResource(notification.icon),
                contentDescription = null,
                tint = toneColors.content,
                modifier = Modifier.size(18.dp),
            )
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = buildAnnotatedString {
                    append(notification.textBefore)
                    withStyle(SpanStyle(fontWeight = FontWeight.Bold)) {
                        append(notification.reference)
                    }
                    append(notification.textAfter)
                },
                fontSize = 14.sp,
                lineHeight = 20.sp,
                color = colors.text,
            )
            Text(text = notification.time, fontSize = 12.sp, lineHeight = 16.sp, color = colors.textTertiary)
        }
        if (notification.isUnread) {
            Box(
                modifier = Modifier
                    .padding(top = 4.dp)
                    .size(10.dp)
                    .clip(CircleShape)
                    .background(colors.primary)
                    .semantics { contentDescription = unreadLabel },
            )
        }
    }
}

@Preview(heightDp = 900)
@Composable
private fun NotificationsScreenPreview() {
    AikuDroidTheme {
        NotificationsScreen()
    }
}
