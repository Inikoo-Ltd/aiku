package io.aiku.droid.ui.clocking

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.ClockingAdjustment
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun ClockingAdjustmentsScreen() {
    val controller = LocalClockingAdjustments.current
    val clocking = LocalClocking.current
    val state by controller.state.collectAsStateWithLifecycle()
    val toasts = LocalToasts.current
    val scrollState = rememberScrollState()
    var isRequesting by remember { mutableStateOf(false) }
    var cancelling by remember { mutableStateOf<ClockingAdjustment?>(null) }
    val cancelledMessage = stringResource(R.string.clocking_adjustments_cancelled)
    val refusedMessage = stringResource(R.string.clocking_form_refused_fallback)
    val offlineMessage = stringResource(R.string.clocking_form_failed_offline)
    val serverMessage = stringResource(R.string.clocking_form_failed_server)

    LaunchedEffect(Unit) { controller.load() }
    LoadMoreOnScroll(scrollState = scrollState, list = state.list, onLoadMore = controller::loadMore)
    LaunchedEffect(state.cancellation) {
        when (val cancellation = state.cancellation) {
            HrSubmission.Saved -> toasts.show(cancelledMessage, ToastKind.Success)
            is HrSubmission.Rejected -> toasts.show(
                cancellation.fieldErrors.values.firstOrNull() ?: cancellation.message.ifBlank { refusedMessage },
                ToastKind.Error,
            )
            is HrSubmission.Failed -> toasts.show(
                if (cancellation.problem == ClockingProblem.Offline) offlineMessage else serverMessage,
                if (cancellation.problem == ClockingProblem.Offline) ToastKind.Offline else ToastKind.Error,
            )
            else -> return@LaunchedEffect
        }
        controller.resetCancellation()
    }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_tab_adjustment),
                kicker = clockingKicker(stringResource(R.string.module_clocking)),
                tabs = clockingSectionTabs(active = ClockingSection.Adjustments),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        HrSectionIntro(text = stringResource(R.string.clocking_adjustments_intro))
        AikuButton(
            text = stringResource(R.string.clocking_adjustment_request),
            onClick = { isRequesting = true },
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        HrPagedList(
            list = state.list,
            emptyIcon = R.drawable.ic_ui_clock,
            emptyTitle = stringResource(R.string.clocking_adjustments_empty_title),
            emptyBody = stringResource(R.string.clocking_adjustments_empty_body),
            onRetry = controller::load,
            onLoadMore = controller::loadMore,
        ) { adjustment ->
            ClockingAdjustmentRow(
                adjustment = adjustment,
                isCancelling = state.cancellingId == adjustment.id,
                onCancel = { cancelling = adjustment },
                source = controller,
            )
        }
    }

    if (isRequesting) {
        ClockingAdjustmentSheet(onDismiss = { isRequesting = false }, onSaved = clocking::refresh)
    }
    cancelling?.let { adjustment ->
        ConfirmDialog(
            title = stringResource(R.string.clocking_adjustments_cancel_title),
            body = stringResource(R.string.clocking_adjustments_cancel_body, clockingDateLabel(adjustment.date, HR_DATE_PATTERN)),
            confirmLabel = stringResource(R.string.clocking_adjustments_cancel),
            dismissLabel = stringResource(R.string.clocking_adjustments_keep),
            isDestructive = true,
            onConfirm = {
                cancelling = null
                controller.cancelAdjustment(adjustment.id)
            },
            onDismiss = { cancelling = null },
        )
    }
}

@Composable
private fun ClockingAdjustmentRow(adjustment: ClockingAdjustment, isCancelling: Boolean, onCancel: () -> Unit, source: AttachmentSource) {
    val noValue = stringResource(R.string.clocking_no_value)
    val recorded = if (adjustment.originalStartTimeLocal == null && adjustment.originalEndTimeLocal == null) {
        stringResource(R.string.clocking_adjustments_no_clockings)
    } else {
        stringResource(R.string.clocking_range_arrow, adjustment.originalStartTimeLocal ?: noValue, adjustment.originalEndTimeLocal ?: noValue)
    }
    val requested = stringResource(R.string.clocking_range_arrow, adjustment.requestedStartTimeLocal ?: noValue, adjustment.requestedEndTimeLocal ?: noValue)

    HrRequestRow {
        HrRequestTitle(title = clockingDateLabel(adjustment.date, HR_DATE_PATTERN), statusLabel = adjustment.statusLabel, status = adjustment.status)
        HrRequestLine(label = stringResource(R.string.clocking_adjustments_recorded), value = recorded)
        HrRequestLine(label = stringResource(R.string.clocking_adjustments_requested), value = requested)
        HrRequestReason(reason = adjustment.reason, refusal = adjustment.approvalComment?.takeIf { adjustment.isRejected })
        if (adjustment.attachments.isNotEmpty()) {
            HrAttachmentList(attachments = adjustment.attachments, source = source)
        }
        if (adjustment.isPending) {
            AikuButton(
                text = stringResource(R.string.clocking_adjustments_cancel),
                onClick = onCancel,
                style = ButtonStyle.Danger,
                isLoading = isCancelling,
                height = 44.dp,
            )
        }
    }
}
