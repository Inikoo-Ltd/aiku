package io.aiku.droid.ui.locations

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.LocationTab
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.StockScanOutcome
import io.aiku.droid.ui.inventory.countLabel
import io.aiku.droid.ui.inventory.scanProblemText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationsScreen(locations: LocationsViewModel) {
    val state by locations.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { locations.onStart() }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = locations::signInAgain)
        } else {
            LocationsContent(state = state, locations = locations)
        }
    }
}

@Composable
private fun LocationsContent(state: LocationsUiState, locations: LocationsViewModel) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val scanFeedback = LocalScanController.current.feedback
    val scrollState = rememberScrollState()
    var showFilter by remember { mutableStateOf(false) }
    val filter = state.filter
    val list = state.list
    val search = filter.search.trim()
    val tabLabels = mapOf(
        LocationTab.All to stringResource(R.string.locations_tab_all),
        LocationTab.Empty to stringResource(R.string.locations_tab_empty),
        LocationTab.PartiallyEmpty to stringResource(R.string.locations_tab_partially_empty),
    )
    val tabs = LocationTab.entries.map { tab -> FilterOption(label = countLabel(tabLabels.getValue(tab), state.countOf(tab))) }
    val outcome = state.scanOutcome
    val noMatchText = (outcome as? StockScanOutcome.NoMatch)?.let { stringResource(R.string.locations_scan_no_location, it.code) }
    val failedText = (outcome as? StockScanOutcome.Failed)?.let { scanProblemText(it.code, it.problem) }

    ScanEffect { scan -> locations.lookUpScan(scan.code) }
    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = locations::loadMore)
    LaunchedEffect(outcome) {
        when (outcome) {
            is StockScanOutcome.Open -> navigator.open(outcome.route)
            is StockScanOutcome.NoMatch -> {
                scanFeedback.rejected()
                noMatchText?.let { toasts.show(it, ToastKind.Info) }
            }
            is StockScanOutcome.Failed -> failedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        locations.scanOutcomeHandled()
    }

    AppScreen(
        header = {
            ModuleHeader(
                WarehouseSection.Locations,
                actions = {
                    HeaderIconButton(
                        icon = R.drawable.ic_ui_search,
                        description = stringResource(R.string.filter_open),
                        onClick = {
                            locations.loadAreas()
                            showFilter = true
                        },
                        filled = true,
                        badgeCount = filter.activeFilterCount,
                    )
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        refresh = PullRefresh(isRefreshing = list.isRefreshing, onRefresh = locations::refresh),
    ) {
        FilterChips(
            options = tabs,
            selectedIndex = filter.tab.ordinal,
            onSelect = { index -> locations.selectTab(LocationTab.entries[index]) },
        )
        if (filter.isFiltered) {
            ClearFiltersRow(count = filter.activeFilterCount, isSorted = filter.sort != null, onClear = locations::clearFilters)
        }
        SearchScanField(
            placeholder = stringResource(R.string.locations_search),
            value = filter.search,
            onValueChange = locations::search,
            onScan = { navigator.open(ScanRoute) },
        )
        state.lookingUpCode?.let { code -> InlineAlert(title = stringResource(R.string.scan_looking_up, code), tone = AlertTone.Info) }
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.locations_list_failed), onRetry = locations::retry)
        }
        when {
            list.rows.isNotEmpty() -> LocationsList(
                locations = list.rows,
                onOpen = { location -> navigator.open(LocationRoute(id = location.id, code = location.code)) },
            )
            list.isEmpty && search.isNotEmpty() -> LocationsFootnote(text = stringResource(R.string.locations_no_match, search))
            list.isEmpty -> LocationsFootnote(text = stringResource(R.string.locations_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.locations_list_more_failed), onRetry = locations::loadMore)
        }
        LocationsFootnote(text = stringResource(R.string.locations_scan_hint))
    }
    if (showFilter) {
        LocationFilterSheet(
            filter = filter,
            areas = state.areas,
            totalFor = state::totalFor,
            onDraftChange = locations::previewTotal,
            onApply = { applied ->
                showFilter = false
                locations.applyFilter(applied)
            },
            onDismiss = { showFilter = false },
        )
    }
}

@Composable
internal fun ClearFiltersRow(count: Int, isSorted: Boolean, onClear: () -> Unit, detail: String? = null) {
    val colors = LocalAikuColors.current
    val summary = listOfNotNull(
        detail,
        count.takeIf { it > 0 && detail == null }?.let { pluralStringResource(R.plurals.filter_count, it, it) },
        if (isSorted) stringResource(R.string.locations_sorted) else null,
    ).joinToString(LIST_SEPARATOR)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth().padding(start = 4.dp),
    ) {
        Text(text = summary, fontSize = 13.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
        Text(
            text = stringResource(R.string.locations_filters_clear),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.link,
            modifier = Modifier
                .heightIn(min = 44.dp)
                .clip(ChipShape)
                .clickable(role = Role.Button, onClick = onClear)
                .padding(horizontal = 8.dp, vertical = 12.dp),
        )
    }
}
