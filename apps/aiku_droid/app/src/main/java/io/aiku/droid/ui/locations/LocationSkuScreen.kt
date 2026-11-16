package io.aiku.droid.ui.locations

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
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
import io.aiku.droid.data.api.LocationOrgStock
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.LocationWrite
import io.aiku.droid.data.api.PickingChannel
import io.aiku.droid.data.api.StockLevel
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.inventory.SheetTextField
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.canEditLocations
import io.aiku.droid.ui.inventory.canEditStocks
import io.aiku.droid.ui.inventory.refusalText
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LocationSkuScreen(location: LocationViewModel, locationOrgStockId: Long, locationCode: String, skoCode: String) {
    val state by location.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { location.onStart() }

    RequireAbility(ability = Ability.ViewLocations, title = stringResource(R.string.module_locations), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = location::signInAgain)
        } else {
            LocationSkuContent(state = state, location = location, locationOrgStockId = locationOrgStockId, locationCode = locationCode, skoCode = skoCode)
        }
    }
}

@Composable
private fun LocationSkuContent(state: LocationUiState, location: LocationViewModel, locationOrgStockId: Long, locationCode: String, skoCode: String) {
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val warehouse = LocalWarehouse.current
    val finder by location.finder.state.collectAsStateWithLifecycle()
    val detail = state.location
    val sko = detail?.orgStock(locationOrgStockId)
    var openSheet by remember { mutableStateOf<LocationAction?>(null) }
    val canEditStocks = detail?.can.canEditStocks(warehouse.can)
    val canEditLocations = detail?.can.canEditLocations(warehouse.can)
    var draft by remember(sko?.locationOrgStockId) { mutableStateOf(sko?.let(SkoSettingsDraft::of)) }
    var showInvalid by remember { mutableStateOf(false) }
    val event = state.event
    val savedText = stringResource(R.string.locations_sko_saved)
    val auditedText = stringResource(R.string.locations_audit_saved, detail?.code ?: locationCode)
    val movedText = stringResource(R.string.locations_move_done, finder.picked?.code ?: "")
    val refusedText = (event as? StockEvent.Refused)?.let { refusalText(it) }

    LaunchedEffect(event) {
        when (event) {
            is StockEvent.Done -> {
                when (event.action) {
                    is SettingsSaved -> {
                        toasts.show(savedText, ToastKind.Success)
                        navigator.back()
                    }
                    is LocationWrite.Audit -> toasts.show(auditedText, ToastKind.Success)
                    else -> toasts.show(movedText, ToastKind.Success)
                }
                openSheet = null
            }
            is StockEvent.Refused -> refusedText?.let { toasts.show(it, ToastKind.Error) }
            null -> return@LaunchedEffect
        }
        location.eventHandled()
    }

    val footer: (@Composable ColumnScope.() -> Unit)? = if (sko != null && (canEditStocks || canEditLocations)) {
        {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                if (canEditStocks) {
                    AikuButton(
                        text = stringResource(R.string.locations_action_audit),
                        onClick = { openSheet = LocationAction.Audit },
                        modifier = Modifier.weight(1f),
                        style = ButtonStyle.Outline,
                        height = 52.dp,
                    )
                }
                if (canEditLocations) {
                    AikuButton(
                        text = stringResource(R.string.locations_sko_move),
                        onClick = { openSheet = LocationAction.MoveSome },
                        modifier = Modifier.weight(1f),
                        style = ButtonStyle.Outline,
                        height = 52.dp,
                    )
                }
                if (canEditStocks) {
                    AikuButton(
                        text = stringResource(R.string.locations_sko_save),
                        onClick = {
                            val update = draft?.update(sko)
                            when {
                                update == null -> showInvalid = true
                                update.isEmpty -> navigator.back()
                                else -> location.update(sko.locationOrgStockId, update)
                            }
                        },
                        isLoading = state.working != null && state.working !is LocationWrite,
                        modifier = Modifier.weight(1f),
                        height = 52.dp,
                    )
                }
            }
        }
    } else {
        null
    }

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.locations_sko_title, sko?.code ?: skoCode, detail?.code ?: locationCode),
                kicker = stringResource(R.string.locations_sko_kicker),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = footer,
    ) {
        val current = draft
        when {
            sko != null && current != null -> SkoSettings(
                sko = sko,
                draft = current,
                canEdit = canEditStocks,
                showInvalid = showInvalid,
                errors = state.writeErrors,
                onDraftChange = {
                    showInvalid = false
                    draft = it
                },
            )
            detail != null -> LocationsFootnote(text = stringResource(R.string.locations_sko_gone, skoCode, detail.code))
            state.problem != null -> StockLoadProblem(problem = state.problem, title = stringResource(R.string.locations_location_failed), onRetry = location::retry)
            else -> SkeletonListCard(rows = 3)
        }
        LocationsFootnote(text = stringResource(R.string.locations_sko_help))
    }
    if (detail != null && sko != null) {
        LocationSheets(sheet = openSheet, detail = detail, state = state, location = location, onDismiss = { openSheet = null }, onlyLinkId = sko.locationOrgStockId)
    }
}

@Composable
private fun SkoSettings(
    sko: LocationOrgStock,
    draft: SkoSettingsDraft,
    canEdit: Boolean,
    showInvalid: Boolean,
    errors: Map<String, List<String>>,
    onDraftChange: (SkoSettingsDraft) -> Unit,
) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val isPicking = draft.type == LocationStockType.Picking
    val invalid = if (showInvalid) draft.invalidLevels else emptySet()

    SkoSummaryCard(
        sko = sko,
        onOpen = if (can.viewInventory) ({ navigator.open(SkuRoute(id = sko.id, code = sko.code)) }) else null,
    )
    LocationsHeading(text = stringResource(R.string.locations_heading_type))
    Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
        if (canEdit || isPicking) {
            LocationChoice(
                title = stringResource(R.string.locations_type_picking),
                isSelected = isPicking,
                onSelect = if (canEdit) ({ onDraftChange(draft.copy(type = LocationStockType.Picking)) }) else null,
                subtitle = stringResource(R.string.locations_type_picking_help),
            )
        }
        if (canEdit || draft.type == LocationStockType.Storing) {
            LocationChoice(
                title = stringResource(R.string.locations_type_storing),
                isSelected = draft.type == LocationStockType.Storing,
                onSelect = if (canEdit) ({ onDraftChange(draft.copy(type = LocationStockType.Storing)) }) else null,
            )
        }
    }
    val wholesale = sko.isDefaultWholesale || PickingChannel.Wholesale in draft.makeDefaultFor
    val dropshipping = sko.isDefaultDropshipping || PickingChannel.Dropshipping in draft.makeDefaultFor
    if (canEdit || wholesale || dropshipping) {
        LocationsHeading(text = stringResource(R.string.locations_heading_active_picking))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
            if (canEdit || wholesale) {
                LocationChoice(
                    title = stringResource(R.string.locations_channel_wholesale),
                    isSelected = wholesale,
                    onSelect = defaultToggle(canEdit, sko.isDefaultWholesale, PickingChannel.Wholesale, draft, onDraftChange),
                    modifier = Modifier.weight(1f).fillMaxHeight(),
                    subtitle = sko.pickingLocation?.code?.let { stringResource(R.string.locations_channel_current, it) }
                        ?: stringResource(R.string.locations_channel_wholesale_help),
                    role = Role.Checkbox,
                )
            }
            if (canEdit || dropshipping) {
                LocationChoice(
                    title = stringResource(R.string.locations_channel_dropshipping),
                    isSelected = dropshipping,
                    onSelect = defaultToggle(canEdit, sko.isDefaultDropshipping, PickingChannel.Dropshipping, draft, onDraftChange),
                    modifier = Modifier.weight(1f).fillMaxHeight(),
                    subtitle = sko.pickingDropshippingLocation?.code?.let { stringResource(R.string.locations_channel_current, it) }
                        ?: stringResource(R.string.locations_channel_dropshipping_help),
                    role = Role.Checkbox,
                )
            }
        }
        if (canEdit) {
            LocationsFootnote(text = stringResource(R.string.locations_default_help))
        }
    }
    if (isPicking) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            LevelField(
                label = stringResource(R.string.locations_min_stock),
                value = draft.minStock,
                isEditable = canEdit,
                error = levelError(StockLevel.Min, invalid, errors),
                onValueChange = { onDraftChange(draft.copy(minStock = it)) },
                modifier = Modifier.weight(1f),
            )
            LevelField(
                label = stringResource(R.string.locations_max_stock),
                value = draft.maxStock,
                isEditable = canEdit,
                error = levelError(StockLevel.Max, invalid, errors),
                onValueChange = { onDraftChange(draft.copy(maxStock = it)) },
                modifier = Modifier.weight(1f),
            )
        }
    } else {
        LevelField(
            label = stringResource(R.string.locations_replenishment_stock),
            value = draft.replenishmentStock,
            isEditable = canEdit,
            error = levelError(StockLevel.Replenishment, invalid, errors),
            onValueChange = { onDraftChange(draft.copy(replenishmentStock = it)) },
        )
    }
    if (canEdit) {
        SheetTextField(
            value = draft.notes,
            onValueChange = { onDraftChange(draft.copy(notes = it)) },
            label = stringResource(R.string.locations_note),
            isError = errors["notes"] != null,
            helper = errors["notes"]?.firstOrNull(),
        )
    } else if (draft.notes.isNotBlank()) {
        LocationsTileRow(icon = R.drawable.ic_ui_edit, title = stringResource(R.string.locations_note), subtitle = draft.notes, tone = Tone.Neutral)
    }
    errors["type"]?.firstOrNull()?.let { LocationsFootnote(text = it) }
}

private fun defaultToggle(
    canEdit: Boolean,
    isAlreadyDefault: Boolean,
    channel: PickingChannel,
    draft: SkoSettingsDraft,
    onDraftChange: (SkoSettingsDraft) -> Unit,
): (() -> Unit)? {
    if (!canEdit || isAlreadyDefault) {
        return null
    }
    return {
        val chosen = if (channel in draft.makeDefaultFor) draft.makeDefaultFor - channel else draft.makeDefaultFor + channel
        onDraftChange(draft.copy(makeDefaultFor = chosen))
    }
}

@Composable
private fun levelError(level: StockLevel, invalid: Set<StockLevel>, errors: Map<String, List<String>>): String? =
    errors[level.field]?.firstOrNull() ?: if (level in invalid) stringResource(R.string.locations_level_invalid) else null

@Composable
private fun LevelField(
    label: String,
    value: String,
    isEditable: Boolean,
    error: String?,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    if (isEditable) {
        SheetTextField(value = value, onValueChange = onValueChange, label = label, isNumber = true, isError = error != null, helper = error, modifier = modifier)
    } else {
        LocationsStatTile(value = value.ifBlank { stringResource(R.string.locations_level_none) }, label = label, modifier = modifier)
    }
}

@Composable
private fun SkoSummaryCard(sko: LocationOrgStock, onOpen: (() -> Unit)?) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .then(if (onOpen != null) Modifier.clickable(role = Role.Button, onClick = onOpen) else Modifier)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        ProductPhoto(code = sko.code, size = 64.dp, imageUrl = sko.imageUrl)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = sko.code, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = stringResource(R.string.locations_sko_summary, sko.name, stockText(sko.stock)), fontSize = 13.sp, color = colors.textTertiary)
        }
    }
}
