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
import io.aiku.droid.data.api.OvertimeRequest
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.navigation.MainTab
import java.time.ZoneId

private const val SECONDS_PER_MINUTE = 60L

private sealed interface OvertimeForm {
    data object New : OvertimeForm
    data class Edit(val request: OvertimeRequest) : OvertimeForm
}

@Composable
fun OvertimeScreen() {
    val controller = LocalOvertime.current
    val state by controller.state.collectAsStateWithLifecycle()
    val clockingState by LocalClocking.current.state.collectAsStateWithLifecycle()
    val scrollState = rememberScrollState()
    var form by remember { mutableStateOf<OvertimeForm?>(null) }

    LaunchedEffect(Unit) { controller.load() }
    LoadMoreOnScroll(scrollState = scrollState, list = state.list, onLoadMore = controller::loadMore)

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.clocking_tab_overtime),
                kicker = clockingKicker(stringResource(R.string.module_clocking)),
                tabs = clockingSectionTabs(active = ClockingSection.Overtime),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        AikuButton(
            text = stringResource(R.string.clocking_overtime_request),
            onClick = { form = OvertimeForm.New },
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        HrPagedList(
            list = state.list,
            emptyIcon = R.drawable.ic_ui_clock,
            emptyTitle = stringResource(R.string.clocking_overtime_empty_title),
            emptyBody = stringResource(R.string.clocking_overtime_empty_body),
            onRetry = controller::load,
            onLoadMore = controller::loadMore,
        ) { request ->
            OvertimeRow(request = request, zone = clockingState.zone, onEdit = { form = OvertimeForm.Edit(request) })
        }
    }

    form?.let { shown ->
        OvertimeRequestSheet(editing = (shown as? OvertimeForm.Edit)?.request, onDismiss = { form = null })
    }
}

@Composable
private fun OvertimeRow(request: OvertimeRequest, zone: ZoneId, onEdit: () -> Unit) {
    val noValue = stringResource(R.string.clocking_no_value)
    val recordedStart = clockingTime(request.recordedStartAt, zone)
    val recordedDuration = request.recordedDurationMinutes?.let { durationText(it * SECONDS_PER_MINUTE) }

    HrRequestRow {
        HrRequestTitle(title = clockingDateLabel(request.requestedDate, HR_DATE_PATTERN), statusLabel = request.statusLabel, status = request.status)
        HrRequestLine(label = stringResource(R.string.clocking_overtime_type), value = request.overtimeTypeName?.takeIf { it.isNotBlank() } ?: noValue)
        HrRequestLine(label = stringResource(R.string.clocking_overtime_from), value = clockingTime(request.requestedStartAt, zone) ?: noValue)
        HrRequestLine(
            label = stringResource(R.string.clocking_overtime_duration),
            value = request.requestedDurationMinutes?.let { durationText(it * SECONDS_PER_MINUTE) } ?: noValue,
        )
        if (recordedStart != null || recordedDuration != null) {
            HrRequestLine(
                label = stringResource(R.string.clocking_overtime_recorded),
                value = listOfNotNull(recordedStart, recordedDuration).joinToString(" · "),
            )
        }
        if (request.lieuRequestedMinutes > 0) {
            HrRequestLine(label = stringResource(R.string.clocking_overtime_lieu), value = durationText(request.lieuRequestedMinutes * SECONDS_PER_MINUTE))
        }
        HrRequestReason(reason = request.reason)
        if (request.isPending) {
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
