package io.aiku.droid.ui.clocking

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.graphics.toColorInt
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Leave
import io.aiku.droid.data.api.LeavePage
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

internal fun leaveTypeColor(hex: String?): Color? = hex?.let { runCatching { Color(it.toColorInt()) }.getOrNull() }

@Composable
fun LeaveScreen() {
    val controller = LocalLeave.current
    val state by controller.state.collectAsStateWithLifecycle()
    val scrollState = rememberScrollState()
    var isRequesting by remember { mutableStateOf(false) }
    var editing by remember { mutableStateOf<Leave?>(null) }
    val overview = state.overview

    LaunchedEffect(Unit) { controller.load() }
    LoadMoreOnScroll(scrollState = scrollState, list = state.list, onLoadMore = controller::loadMore)

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_tab_leave),
                kicker = clockingKicker(stringResource(R.string.module_clocking)),
                tabs = clockingSectionTabs(active = ClockingSection.Leave),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        if (overview?.balance != null) {
            LeaveBalanceCards(overview = overview)
        }
        AikuButton(
            text = stringResource(R.string.clocking_leave_request),
            onClick = { isRequesting = true },
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        HrPagedList(
            list = state.list,
            emptyIcon = R.drawable.ic_ui_clipboard,
            emptyTitle = stringResource(R.string.clocking_leave_empty_title),
            emptyBody = stringResource(R.string.clocking_leave_empty_body),
            onRetry = controller::load,
            onLoadMore = controller::loadMore,
        ) { leave ->
            LeaveRow(
                leave = leave,
                canEdit = leave.isPending && overview?.typeOf(leave)?.isMedical == true,
                onEdit = { editing = leave },
                source = controller,
            )
        }
    }

    if (isRequesting) {
        LeaveRequestSheet(onDismiss = { isRequesting = false })
    }
    editing?.let { leave -> LeaveCertificateSheet(leave = leave, onDismiss = { editing = null }) }
}

@Composable
private fun LeaveBalanceCards(overview: LeavePage) {
    val locale = LocalConfiguration.current.locales[0]
    val annualDays = overview.balance?.annualDays ?: 0.0

    LeaveBalanceCard(
        label = stringResource(R.string.clocking_leave_annual),
        value = formatDays(overview.annualRemainingAfterSubmission, locale),
        caption = pluralStringResource(
            R.plurals.clocking_leave_submitted,
            dayQuantity(annualDays),
            formatDays(overview.annualSubmittedDays, locale),
            formatDays(annualDays, locale),
        ),
    )
    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
        LeaveBalanceCard(
            label = stringResource(R.string.clocking_leave_medical),
            value = formatDays(overview.medicalRequestCount, locale),
            caption = stringResource(R.string.clocking_leave_days_this_month),
            modifier = Modifier.weight(1f),
        )
        LeaveBalanceCard(
            label = stringResource(R.string.clocking_leave_unpaid),
            value = formatDays(overview.unpaidRequestCount, locale),
            caption = stringResource(R.string.clocking_leave_days_this_month),
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
private fun LeaveBalanceCard(label: String, value: String, caption: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    AikuCard(modifier = modifier, padding = 14.dp, spacing = 2.dp) {
        Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)
        Text(text = value, fontSize = 26.sp, fontWeight = FontWeight.Bold, color = colors.text)
        Text(text = caption, fontSize = 12.sp, color = colors.textTertiary)
    }
}

@Composable
private fun LeaveRow(leave: Leave, canEdit: Boolean, onEdit: () -> Unit, source: AttachmentSource) {
    val colors = LocalAikuColors.current
    val dot = leaveTypeColor(leave.typeColorHex) ?: colors.faint

    HrRequestRow {
        HrRequestTitle(
            title = leave.typeLabel.ifBlank { leave.type },
            statusLabel = leave.statusLabel,
            status = leave.status,
            leading = { Box(modifier = Modifier.size(10.dp).clip(CircleShape).background(dot)) },
        )
        HrRequestLine(label = stringResource(R.string.clocking_leave_dates), value = hrDateRange(leave.startDate, leave.endDate))
        HrRequestLine(
            label = stringResource(R.string.clocking_leave_duration),
            value = stringResource(if (leave.isHalfDayDuration) R.string.clocking_leave_half_day else R.string.clocking_leave_full_day),
        )
        HrRequestLine(
            label = stringResource(R.string.clocking_leave_approval),
            value = stringResource(R.string.clocking_leave_approval_progress, leave.approvalCompletedSteps, leave.approvalTotalSteps),
        )
        HrRequestReason(reason = leave.reason, refusal = leave.rejectionReason?.takeIf { leave.isRejected })
        if (leave.attachments.isNotEmpty()) {
            HrAttachmentList(attachments = leave.attachments, source = source)
        }
        if (canEdit) {
            AikuButton(
                text = stringResource(R.string.clocking_edit),
                onClick = onEdit,
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_ui_edit,
                height = 44.dp,
            )
        }
    }
}
