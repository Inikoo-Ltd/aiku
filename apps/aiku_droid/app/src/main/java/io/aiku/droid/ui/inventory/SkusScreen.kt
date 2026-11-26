package io.aiku.droid.ui.inventory

import androidx.annotation.StringRes
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.OrgStockRow
import io.aiku.droid.data.api.SkoBucket
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.SearchWithFilter
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.goodsout.LoadMoreOnScroll
import io.aiku.droid.ui.goodsout.LoadingMore
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.locations.ClearFiltersRow
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlin.math.abs

internal const val ACTIVE_STATE = "active"

internal fun skoStateTone(state: String): Tone = when (state) {
    ACTIVE_STATE -> Tone.Success
    "discontinuing" -> Tone.Warn
    "discontinued", "abnormality" -> Tone.Danger
    else -> Tone.Neutral
}

internal val skoChipBuckets = listOf(SkoBucket.Current, SkoBucket.Discontinued, SkoBucket.Abnormality, SkoBucket.All)

@StringRes
internal fun skoListTitle(bucket: SkoBucket): Int = when (bucket) {
    SkoBucket.BelowMinimum -> R.string.inventory_title_below_minimum
    SkoBucket.Negative -> R.string.inventory_title_negative
    else -> R.string.section_skos
}

data class SkoLocationLine(val code: String, val quantity: String?, val isNegative: Boolean)

internal fun OrgStockRow.locationLine(): SkoLocationLine? {
    val place = location ?: return null
    val quantity = locationQuantity
    return SkoLocationLine(code = place.code, quantity = quantity?.let { stockText(abs(it), locationQuantityLabel?.trimStart('-')) }, isNegative = quantity != null && quantity < 0)
}

internal fun OrgStockRow.listRoute(): Any {
    val place = location
    val linkId = locationOrgStockId
    return if (place != null && linkId != null) {
        LocationSkuRoute(locationId = place.id, locationOrgStockId = linkId, locationCode = place.code, skoCode = code)
    } else {
        SkuRoute(id = id, code = code)
    }
}

@Composable
fun SkusScreen(skos: SkosViewModel) {
    val state by skos.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { skos.onStart() }

    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = skos::signInAgain)
        } else {
            SkusContent(state = state, skos = skos)
        }
    }
}

@Composable
private fun SkusContent(state: SkosUiState, skos: SkosViewModel) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val scanFeedback = LocalScanController.current.feedback
    val scrollState = rememberScrollState()
    var showFilter by remember { mutableStateOf(false) }
    val filter = state.filter
    val list = state.list
    val search = filter.search.trim()
    val bucketLabels = mapOf(
        SkoBucket.Current to stringResource(R.string.inventory_chip_current),
        SkoBucket.Discontinued to stringResource(R.string.inventory_chip_discontinued),
        SkoBucket.Abnormality to stringResource(R.string.inventory_chip_abnormalities),
        SkoBucket.All to stringResource(R.string.inventory_all),
    )
    val tabs = skoChipBuckets.map { bucket -> FilterOption(label = countLabel(bucketLabels.getValue(bucket), state.counts?.count(bucket))) }
    val outcome = state.scanOutcome
    val noMatchText = (outcome as? StockScanOutcome.NoMatch)?.let { stringResource(R.string.inventory_scan_no_sko, it.code) }
    val failedText = (outcome as? StockScanOutcome.Failed)?.let { scanProblemText(it.code, it.problem) }
    val familyCode = filter.familyId?.let { id -> state.families?.firstOrNull { it.id == id }?.code }

    ScanEffect { scan -> skos.lookUpScan(scan.code) }
    LoadMoreOnScroll(scrollState = scrollState, itemCount = list.rows.size, canLoadMore = list.canLoadMoreOnScroll, onLoadMore = skos::loadMore)
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
        skos.scanOutcomeHandled()
    }

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.Skus,
                title = stringResource(skoListTitle(filter.bucket)),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        scrollState = scrollState,
        refresh = PullRefresh(isRefreshing = list.isRefreshing, onRefresh = skos::refresh),
    ) {
        FilterChips(
            options = tabs,
            selectedIndex = skoChipBuckets.indexOf(filter.bucket),
            onSelect = { index -> skos.selectBucket(skoChipBuckets[index]) },
        )
        if (filter.isFiltered) {
            ClearFiltersRow(count = filter.activeFilterCount, isSorted = filter.sort != null, onClear = skos::clearFilters, detail = familyCode)
        }
        SearchWithFilter(
            filterCount = filter.activeFilterCount,
            onFilter = {
                skos.loadFamilies()
                showFilter = true
            },
            buttonSize = 46.dp,
        ) { modifier ->
            SearchScanField(
                placeholder = stringResource(R.string.inventory_skos_search),
                value = filter.search,
                onValueChange = skos::search,
                onScan = { navigator.open(ScanRoute) },
                modifier = modifier,
            )
        }
        state.lookingUpCode?.let { code -> InlineAlert(title = stringResource(R.string.scan_looking_up, code), tone = AlertTone.Info) }
        list.firstPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_skos_failed), onRetry = skos::retry)
        }
        when {
            list.rows.isNotEmpty() -> ListGroup {
                list.rows.forEachIndexed { index, sko ->
                    if (index > 0) {
                        RowDivider()
                    }
                    SkoListRow(sko = sko, isAbnormality = filter.bucket == SkoBucket.Abnormality, onOpen = { navigator.open(sko.listRoute()) })
                }
            }
            list.isEmpty && search.isNotEmpty() -> BoardFootnote(text = stringResource(R.string.inventory_skos_no_match, search))
            list.isEmpty -> BoardFootnote(text = stringResource(R.string.inventory_skos_none))
            list.problem == null -> SkeletonListCard(rows = 4)
        }
        if (list.isLoadingMore) {
            LoadingMore()
        }
        list.nextPageProblem?.let { problem ->
            StockLoadProblem(problem = problem, title = stringResource(R.string.inventory_skos_more_failed), onRetry = skos::loadMore)
        }
        BoardFootnote(
            text = stringResource(if (filter.bucket == SkoBucket.Abnormality) R.string.inventory_abnormalities_footnote else R.string.inventory_skos_footnote),
        )
    }

    if (showFilter) {
        SkuFilterSheet(
            filter = filter,
            families = state.families,
            options = state.filterOptions,
            totalFor = state::totalFor,
            onDraftChange = skos::previewTotal,
            onApply = { applied ->
                showFilter = false
                skos.applyFilter(applied)
            },
            onDismiss = { showFilter = false },
        )
    }
}

@Composable
private fun SkoListRow(sko: OrgStockRow, isAbnormality: Boolean, onOpen: () -> Unit) {
    val zone = organisationZone()
    val locationLine = sko.locationLine()
    val subtitle = if (isAbnormality) {
        listOfNotNull(
            sko.name.takeIf { it.isNotBlank() },
            dayText(sko.lastSeenAt, zone)?.let { stringResource(R.string.inventory_last_seen, it) },
        ).joinToString(LIST_SEPARATOR)
    } else if (locationLine != null) {
        sko.name
    } else {
        listOfNotNull(
            sko.name.takeIf { it.isNotBlank() },
            pluralStringResource(R.plurals.inventory_location_count, sko.numberLocations, sko.numberLocations),
        ).joinToString(LIST_SEPARATOR)
    }

    ItemRow(
        code = sko.code,
        subtitle = subtitle,
        chip = sko.stateLabel.takeIf { sko.state != ACTIVE_STATE && it.isNotBlank() },
        chipTone = skoStateTone(sko.state),
        done = if (isAbnormality) null else stockText(sko.quantityAvailable, sko.quantityAvailableLabel),
        imageUrl = sko.imageUrl,
        extra = if (locationLine == null) {
            null
        } else {
            { SkoLocationText(locationLine) }
        },
        onClick = onOpen,
    )
}

@Composable
private fun SkoLocationText(line: SkoLocationLine) {
    val colors = LocalAikuColors.current
    val quantity = line.quantity?.let { shown -> if (line.isNegative) stringResource(R.string.inventory_delta_decrease, shown) else shown }

    Text(
        text = buildAnnotatedString {
            append(line.code)
            if (quantity != null) {
                append(LIST_SEPARATOR)
                withStyle(SpanStyle(color = if (line.isNegative) colors.tone(Tone.Danger).content else colors.text, fontWeight = FontWeight.Bold)) { append(quantity) }
            }
        },
        fontSize = 13.sp,
        fontWeight = FontWeight.Medium,
        color = colors.textSecondary,
        maxLines = 1,
        overflow = TextOverflow.Ellipsis,
    )
}
