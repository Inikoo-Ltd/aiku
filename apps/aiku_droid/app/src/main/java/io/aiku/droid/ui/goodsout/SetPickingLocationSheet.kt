package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.R
import io.aiku.droid.data.api.OrgStockDetail
import io.aiku.droid.data.api.OrgStockLocation
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.inventory.LocalInventoryServices
import io.aiku.droid.ui.inventory.PickingDefaults
import io.aiku.droid.ui.inventory.SkoViewModel
import io.aiku.droid.ui.inventory.StockEvent
import io.aiku.droid.ui.inventory.StockLoadProblem
import io.aiku.droid.ui.inventory.canEditStocks
import io.aiku.droid.ui.inventory.refusalText
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.LocalAikuColors

private fun OrgStockDetail.defaultWholesale(): Long? =
    locations.firstOrNull { it.isDefaultWholesale }?.locationOrgStockId ?: locations.firstOrNull { it.location.id == pickingLocation?.id }?.locationOrgStockId

private fun OrgStockDetail.defaultDropshipping(): Long? =
    locations.firstOrNull { it.isDefaultDropshipping }?.locationOrgStockId
        ?: locations.firstOrNull { it.location.id == pickingDropshippingLocation?.id }?.locationOrgStockId

@Composable
fun SetPickingLocationSheet(orgStockId: Long, code: String, name: String?, imageUrl: String?, onDismiss: () -> Unit) {
    val services = LocalInventoryServices.current
    val warehouse = LocalWarehouse.current
    val sko: SkoViewModel = viewModel(
        key = "picking-location-${warehouse.id}-$orgStockId",
        factory = viewModelFactory { initializer { SkoViewModel(services.api, services.sessions, warehouse.id, orgStockId, withMovements = false) } },
    )
    val state by sko.state.collectAsStateWithLifecycle()
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val detail = state.orgStock
    val problem = state.problem
    var wholesale by remember { mutableStateOf<Long?>(null) }
    var dropshipping by remember { mutableStateOf<Long?>(null) }
    val chosenWholesale = wholesale ?: detail?.defaultWholesale()
    val chosenDropshipping = dropshipping ?: detail?.defaultDropshipping()
    val canEdit = detail?.can.canEditStocks(warehouse.can)
    val event = state.event
    val savedText = stringResource(R.string.goodsout_picking_locations_saved, detail?.code ?: code)
    val refusedText = (event as? StockEvent.Refused)?.let { refusalText(it) }

    LaunchedEffect(Unit) { sko.onStart() }
    LaunchedEffect(event) {
        when (event) {
            is StockEvent.Done -> {
                toasts.show(savedText, ToastKind.Success)
                sko.eventHandled()
                onDismiss()
            }
            is StockEvent.Refused -> {
                refusedText?.let { toasts.show(it, ToastKind.Error) }
                sko.eventHandled()
            }
            null -> Unit
        }
    }

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_set_picking_location_title, detail?.code ?: code),
        subtitle = stringResource(R.string.goodsout_set_picking_location_subtitle, detail?.name ?: name.orEmpty()),
        onDismiss = onDismiss,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            ProductPhoto(code = code, size = 56.dp, imageUrl = detail?.imageUrl ?: imageUrl)
            Text(
                text = stringResource(R.string.goodsout_set_picking_location_hint),
                fontSize = 13.sp,
                color = colors.textTertiary,
                modifier = Modifier.weight(1f).padding(start = 12.dp),
            )
        }
        when {
            detail != null && detail.locations.isEmpty() -> GoodsOutFootNote(text = stringResource(R.string.goodsout_picking_locations_none))
            detail != null -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                GroupLabel(text = stringResource(R.string.goodsout_channel_wholesale))
                detail.locations.filter { canEdit || it.locationOrgStockId == chosenWholesale }.forEach { location ->
                    PickingChoice(location = location, selected = location.locationOrgStockId == chosenWholesale, onClick = { if (canEdit) wholesale = location.locationOrgStockId })
                }
                GroupLabel(text = stringResource(R.string.goodsout_channel_dropshipping))
                detail.locations.filter { canEdit || it.locationOrgStockId == chosenDropshipping }.forEach { location ->
                    PickingChoice(location = location, selected = location.locationOrgStockId == chosenDropshipping, onClick = { if (canEdit) dropshipping = location.locationOrgStockId })
                }
            }
            problem != null -> StockLoadProblem(problem = problem, title = stringResource(R.string.goodsout_picking_locations_failed), onRetry = sko::retry)
            else -> SkeletonListCard(rows = 2)
        }
        GoodsOutFootNote(text = stringResource(R.string.goodsout_set_picking_location_note))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            if (detail != null && canEdit && detail.locations.isNotEmpty()) {
                AikuButton(
                    text = stringResource(R.string.goodsout_save_picking_locations),
                    onClick = {
                        val defaults = PickingDefaults(wholesale = wholesale, dropshipping = dropshipping)
                        if (wholesale == null && dropshipping == null) onDismiss() else sko.setPickingDefaults(defaults)
                    },
                    isLoading = state.isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun PickingChoice(location: OrgStockLocation, selected: Boolean, onClick: () -> Unit) {
    ChoiceRow(
        title = location.location.code,
        subtitle = if (location.quantity > 0.0) {
            stringResource(R.string.goodsout_location_in_stock, stockText(location.quantity, location.quantityLabel))
        } else {
            stringResource(R.string.goodsout_location_empty)
        },
        selected = selected,
        onClick = onClick,
    )
}
