package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.StringRes
import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.PickingSessionState
import io.aiku.droid.data.api.StaffRef
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import kotlin.math.roundToInt

private val sessionFilters = listOf(
    null,
    PickingSessionState.InProcess,
    PickingSessionState.Handling,
    PickingSessionState.HandlingBlocked,
    PickingSessionState.PickingFinished,
    PickingSessionState.PackingFinished,
)

@StringRes
internal fun PickingSessionState.label(): Int = when (this) {
    PickingSessionState.InProcess -> R.string.goodsout_extra_session_in_process
    PickingSessionState.Handling -> R.string.goodsout_extra_session_picking
    PickingSessionState.HandlingBlocked -> R.string.goodsout_extra_session_waiting
    PickingSessionState.PickingFinished -> R.string.goodsout_extra_session_picking_finished
    PickingSessionState.PackingFinished -> R.string.goodsout_extra_session_packing_finished
}

internal fun sessionTone(state: PickingSessionState?): Tone = when (state) {
    PickingSessionState.InProcess, null -> Tone.Neutral
    PickingSessionState.Handling -> Tone.Primary
    PickingSessionState.HandlingBlocked -> Tone.Warn
    PickingSessionState.PickingFinished, PickingSessionState.PackingFinished -> Tone.Success
}

internal val PickingSessionState?.isPacking: Boolean
    get() = this == PickingSessionState.PickingFinished || this == PickingSessionState.PackingFinished

internal fun percent(value: Double): Int = value.roundToInt().coerceIn(0, 100)

@Composable
internal fun sessionPersonName(person: StaffRef?): String? {
    val staff = LocalStaff.current
    val name = person?.name?.takeIf { it.isNotBlank() } ?: return null
    return if (person.id == staff.userId) stringResource(R.string.goodsout_extra_picker_you, name) else name
}

@Composable
internal fun sessionStateLabel(state: String, label: String): String =
    label.ifBlank { PickingSessionState.ofKey(state)?.let { stringResource(it.label()) } ?: state }

@Composable
fun PickingSessionsScreen(sessions: PickingSessionsViewModel) {
    val state by sessions.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { sessions.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = sessions::signInAgain)
        } else {
            PickingSessionsContent(state = state, sessions = sessions)
        }
    }
}

@Composable
private fun PickingSessionsContent(state: PickingSessionsUiState, sessions: PickingSessionsViewModel) {
    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    val list = state.list
    val options = sessionFilters.map { filter ->
        val label = stringResource(filter?.label() ?: R.string.goodsout_extra_sessions_all)
        val count = filter?.let { state.counts[it.key] }
        FilterOption(label = if (count == null) label else stringResource(R.string.goodsout_state_count, label, count))
    }

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.sessions.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = sessions::loadMore)

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.PickingSessions) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        FilterChips(options = options, selectedIndex = sessionFilters.indexOf(state.state), onSelect = { index -> sessions.selectState(sessionFilters[index]) })
        list.firstPageProblem?.let { problem ->
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_extra_sessions_failed), onRetry = sessions::retry)
        }
        when {
            list.sessions.isNotEmpty() -> ListGroup {
                list.sessions.forEachIndexed { index, session ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ExtraRow(
                        title = session.reference,
                        subtitle = sessionSubtitle(session),
                        chip = sessionStateLabel(session.state, session.stateLabel),
                        chipTone = sessionTone(session.sessionState),
                        onClick = { navigator.open(PickingSessionRoute(id = session.id, reference = session.reference)) },
                    )
                }
            }
            list.hasLoaded && list.loadingPage == null -> EmptyState(
                icon = R.drawable.ic_ui_trolley,
                title = stringResource(R.string.goodsout_extra_sessions_empty_title),
                body = stringResource(R.string.goodsout_extra_sessions_empty_body),
            )
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_extra_sessions_more_failed), onRetry = sessions::loadMore)
        }
        HintText(text = stringResource(R.string.goodsout_extra_sessions_hint))
    }
}

@Composable
private fun sessionSubtitle(session: PickingSessionRow): String = listOfNotNull(
    sessionPersonName(session.picker),
    pluralStringResource(R.plurals.goodsout_extra_delivery_notes, session.numberDeliveryNotes, session.numberDeliveryNotes),
    pluralStringResource(R.plurals.goodsout_extra_items, session.numberItems, session.numberItems),
    pluralStringResource(R.plurals.goodsout_extra_trolleys, session.numberTrolleys, session.numberTrolleys),
    if (session.sessionState.isPacking) {
        stringResource(R.string.goodsout_extra_packing_percent, percent(session.packingPercentage))
    } else {
        stringResource(R.string.goodsout_extra_percent_picked, percent(session.pickingPercentage))
    },
).joinToString(KICKER_SEPARATOR)
