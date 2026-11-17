package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletFilter
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.fulfilment.inbound.PalletsUiState
import io.aiku.droid.ui.fulfilment.inbound.PalletsViewModel
import io.aiku.droid.ui.fulfilment.inbound.chip
import io.aiku.droid.ui.fulfilment.inbound.palletChip
import io.aiku.droid.ui.fulfilment.inbound.palletSubtitle
import io.aiku.droid.ui.fulfilment.inbound.palletTone
import io.aiku.droid.ui.fulfilment.inbound.rememberPallets
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.inventory.scanProblemText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PalletIncidentsRoute
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
fun StoredPalletsScreen(container: AppContainer, warehouseId: Long) {
    val pallets = rememberPallets(container, warehouseId, PalletFilter.Storing)
    val state by pallets.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { pallets.onStart() }

    RequireFulfilment(section = R.string.section_stored_pallets) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = pallets::signInAgain)
        } else {
            StoredPalletsContent(state = state, pallets = pallets)
        }
    }
}

@Composable
private fun StoredPalletsContent(state: PalletsUiState, pallets: PalletsViewModel) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val scrollState = rememberScrollState()
    val list = state.list
    val search = state.search.trim()
    val filters = PalletFilter.entries
    val outcome = state.scanOutcome
    val noMatchText = (outcome as? StockScanOutcome.NoMatch)?.let { stringResource(R.string.fulfilment_inbound_scan_no_pallet, it.code) }
    val failedText = (outcome as? StockScanOutcome.Failed)?.let { scanProblemText(it.code, it.problem) }

    ScanEffect { scan -> pallets.lookUpScan(scan.code) }
    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = pallets::loadMore)
    LaunchedEffect(outcome) {
        when (outcome) {
            is StockScanOutcome.Open -> navigator.open(outcome.route)
            is StockScanOutcome.NoMatch -> noMatchText?.let { toasts.show(it, ToastKind.Info) }
            is StockScanOutcome.Failed -> failedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        pallets.scanOutcomeHandled()
    }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.StoredPallets) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        FilterChips(
            options = filters.map { FilterOption(label = filterChipLabel(label = stringResource(it.chip().label), count = state.counts?.count(it.key))) },
            selectedIndex = filters.indexOf(state.filter),
            onSelect = { index -> pallets.selectFilter(filters[index]) },
        )
        SearchScanField(
            placeholder = stringResource(R.string.fulfilment_inbound_search_pallets),
            value = state.search,
            onValueChange = pallets::search,
            onScan = { navigator.open(ScanRoute) },
        )
        if (state.filter == PalletFilter.Incidents) {
            ListGroup {
                FulfilmentRow(
                    title = stringResource(R.string.fulfilment_inbound_incident_reports),
                    subtitle = stringResource(R.string.fulfilment_inbound_incident_reports_hint),
                    leading = { IconTile(icon = R.drawable.ic_ui_warning, tone = Tone.Danger) },
                    onClick = { navigator.open(PalletIncidentsRoute) },
                )
            }
        }
        state.lookingUpCode?.let { code -> InlineAlert(title = stringResource(R.string.scan_looking_up, code), tone = AlertTone.Info) }
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_inbound_pallets_failed), onRetry = pallets::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, pallet ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = pallet.reference,
                        subtitle = palletSubtitle(pallet, showCustomer = true),
                        chip = palletChip(pallet),
                        chipTone = palletTone(pallet),
                        showPhoto = false,
                        onClick = { navigator.open(PalletRoute(id = pallet.id, reference = pallet.reference)) },
                    )
                }
            }
            list.isEmpty && search.isNotEmpty() -> FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_pallets_no_match, search))
            list.isEmpty -> FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_pallets_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_inbound_pallets_more_failed), onRetry = pallets::loadMore)
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_pallets_footnote))
    }
}
