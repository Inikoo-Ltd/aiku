package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletFilter
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.fulfilment.inbound.PalletsUiState
import io.aiku.droid.ui.fulfilment.inbound.PalletsViewModel
import io.aiku.droid.ui.fulfilment.inbound.incidentTone
import io.aiku.droid.ui.fulfilment.inbound.kind
import io.aiku.droid.ui.fulfilment.inbound.palletChip
import io.aiku.droid.ui.fulfilment.inbound.quotedReference
import io.aiku.droid.ui.fulfilment.inbound.rememberPallets
import io.aiku.droid.ui.goodsin.goodsInDate
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

private val incidentChips: List<PalletIncidentState?> = listOf(null) + PalletIncidentState.entries

@Composable
fun PalletIncidentsScreen(container: AppContainer, warehouseId: Long) {
    val incidents = rememberPallets(container, warehouseId, PalletFilter.Incidents)
    val state by incidents.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { incidents.onStart() }

    RequireFulfilment(section = R.string.section_stored_pallets) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = incidents::signInAgain)
        } else {
            PalletIncidentsContent(state = state, incidents = incidents)
        }
    }
}

@Composable
private fun PalletIncidentsContent(state: PalletsUiState, incidents: PalletsViewModel) {
    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    val list = state.list

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = incidents::loadMore)

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.StoredPallets,
                title = stringResource(R.string.fulfilment_state_incidents),
                kicker = stringResource(R.string.section_stored_pallets),
                onBack = { navigator.back() },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        refresh = PullRefresh(isRefreshing = list.isRefreshing, onRefresh = incidents::refresh),
    ) {
        FilterChips(
            options = incidentChips.map { incidentState ->
                FilterOption(
                    label = filterChipLabel(
                        label = stringResource(incidentState?.kind()?.label ?: R.string.fulfilment_filter_all),
                        count = if (incidentState == null) state.counts?.count(PalletFilter.Incidents.key) else state.incidentCounts?.count(incidentState.key),
                    ),
                )
            },
            selectedIndex = incidentChips.indexOf(state.incidentState),
            onSelect = { index -> incidents.selectIncidentState(incidentChips[index]) },
        )
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_inbound_incidents_failed), onRetry = incidents::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, pallet ->
                    if (index > 0) {
                        RowDivider()
                    }
                    IncidentRow(pallet = pallet, onOpen = { navigator.open(PalletRoute(id = pallet.id, reference = pallet.reference)) })
                }
            }
            list.isEmpty -> FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_incidents_none))
            list.problem == null -> SkeletonListCard(rows = 3)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_inbound_incidents_more_failed), onRetry = incidents::loadMore)
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_incidents_footnote))
    }
}

@Composable
private fun IncidentRow(pallet: Pallet, onOpen: () -> Unit) {
    val zone = organisationZone()
    val incident = pallet.incident
    val returnReference = incident?.palletReturn?.reference?.takeIf { it.isNotBlank() }

    ItemRow(
        code = pallet.reference,
        subtitle = dotJoined(
            returnReference?.let { stringResource(R.string.fulfilment_inbound_incident_in_return, it) },
            quotedReference(incident?.message),
            incident?.reporter?.name,
            goodsInDate(incident?.date, zone),
            pallet.customer?.name,
        ),
        chip = palletChip(pallet),
        chipTone = incidentTone(incident?.state ?: pallet.state),
        showPhoto = false,
        onClick = onOpen,
    )
}
