package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.foundation.rememberScrollState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.StoredItemPalletRow
import io.aiku.droid.data.api.StoredItemRow
import io.aiku.droid.data.api.StoredItemStateFilter
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DetailCard
import io.aiku.droid.ui.components.DetailLine
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.fulfilment.dotJoined
import io.aiku.droid.ui.fulfilment.filterChipLabel
import io.aiku.droid.ui.goodsin.HelperNote
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
private fun storedItemStateLabel(filter: StoredItemStateFilter): String = stringResource(
    when (filter) {
        StoredItemStateFilter.Active -> R.string.fulfilment_state_active
        StoredItemStateFilter.Discontinuing -> R.string.fulfilment_state_discontinuing
        StoredItemStateFilter.Discontinued -> R.string.fulfilment_out_state_discontinued
        StoredItemStateFilter.All -> R.string.fulfilment_filter_all
    },
)

@Composable
internal fun StoredItemsContent(items: StoredItemsViewModel) {
    val state by items.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { items.onStart() }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = items::signInAgain)
        return
    }

    val navigator = LocalAppNavigator.current
    val scrollState = rememberScrollState()
    val list = state.list
    val search = state.search.trim()
    val filters = StoredItemStateFilter.entries

    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = items::loadMore)

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.StoredItems) },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
    ) {
        FilterChips(
            options = filters.map { FilterOption(label = filterChipLabel(storedItemStateLabel(it), state.counts?.count(it.key))) },
            selectedIndex = filters.indexOf(state.state),
            onSelect = { index -> items.selectState(filters[index]) },
        )
        SearchField(value = state.search, onValueChange = items::search, placeholder = stringResource(R.string.fulfilment_out_search_stored_items))
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_out_stored_items_failed), onRetry = items::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, row ->
                    if (index > 0) {
                        RowDivider()
                    }
                    StoredItemRowItem(row = row, onOpen = { items.open(row) })
                }
            }
            list.isEmpty && search.isNotEmpty() -> HelperNote(text = stringResource(R.string.fulfilment_out_stored_items_no_match, search))
            list.isEmpty -> HelperNote(text = stringResource(R.string.fulfilment_out_stored_items_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.fulfilment_out_stored_items_more_failed), onRetry = items::loadMore)
        }
        HelperNote(text = stringResource(R.string.fulfilment_stored_items_footnote))
    }

    state.opened?.let { opened ->
        StoredItemSheet(
            sheet = opened,
            onRetry = items::retryDetail,
            onOpenPallet = { pallet ->
                items.close()
                navigator.open(PalletRoute(id = pallet.pallet.id, reference = pallet.pallet.reference))
            },
            onDismiss = items::close,
        )
    }
}

@Composable
private fun StoredItemRowItem(row: StoredItemRow, onOpen: () -> Unit) {
    ItemRow(
        code = row.reference,
        subtitle = dotJoined(
            row.customer?.name,
            row.name,
            pluralStringResource(R.plurals.fulfilment_pallet_count, row.numberPallets, row.numberPallets),
        ),
        chip = row.stateLabel.takeIf { it.isNotBlank() },
        chipTone = storedItemStateTone(row.state),
        done = row.quantity.toString(),
        showPhoto = false,
        onClick = onOpen,
    )
}

@Composable
private fun StoredItemSheet(sheet: StoredItemSheetState, onRetry: () -> Unit, onOpenPallet: (StoredItemPalletRow) -> Unit, onDismiss: () -> Unit) {
    val row = sheet.row
    val detail = sheet.detail
    val lines = listOfNotNull(
        (detail?.customer ?: row.customer)?.name?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_person, text = it) },
        (detail?.barcode ?: row.barcode)?.takeIf { it.isNotBlank() }?.let { DetailLine(icon = R.drawable.ic_ui_scan, text = stringResource(R.string.fulfilment_out_barcode, it)) },
        DetailLine(icon = R.drawable.ic_ui_sko, text = stringResource(R.string.fulfilment_out_quantity_in_warehouse, detail?.quantity ?: row.quantity)),
    )

    AikuBottomSheet(
        title = row.reference,
        subtitle = (detail?.name ?: row.name).takeIf { it.isNotBlank() },
        onDismiss = onDismiss,
    ) {
        DetailCard(title = (detail?.stateLabel ?: row.stateLabel).ifBlank { stringResource(R.string.fulfilment_stored_items) }, lines = lines)
        when {
            detail != null -> {
                SectionHeading(
                    title = stringResource(R.string.fulfilment_pallets),
                    caption = pluralStringResource(R.plurals.fulfilment_pallet_count, detail.pallets.size, detail.pallets.size),
                )
                if (detail.pallets.isEmpty()) {
                    HelperNote(text = stringResource(R.string.fulfilment_out_stored_item_no_pallets))
                } else {
                    ListGroup {
                        detail.pallets.forEachIndexed { index, pallet ->
                            if (index > 0) {
                                RowDivider()
                            }
                            ItemRow(
                                code = pallet.pallet.reference,
                                subtitle = dotJoined(
                                    pallet.location?.code?.takeIf { it.isNotBlank() } ?: stringResource(R.string.fulfilment_out_no_location),
                                    pallet.pallet.customerReference,
                                ),
                                chip = pallet.pallet.stateLabel?.takeIf { it.isNotBlank() },
                                chipTone = palletStateTone(pallet.pallet.state),
                                done = pallet.quantity.toString(),
                                showPhoto = false,
                                onClick = { onOpenPallet(pallet) },
                            )
                        }
                    }
                }
            }
            sheet.problem != null -> GoodsOutLoadProblem(problem = sheet.problem, title = stringResource(R.string.fulfilment_out_stored_item_failed, row.reference), onRetry = onRetry)
            else -> SkeletonListCard(rows = 2)
        }
    }
}
