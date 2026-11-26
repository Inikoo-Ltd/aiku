package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletReturn
import io.aiku.droid.data.api.PalletReturnWrite
import io.aiku.droid.data.api.ReturnAddress
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.fulfilment.FulfilmentRow
import io.aiku.droid.ui.goodsout.Choices
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.theme.LocalAikuColors

private const val ADDRESS_SEPARATOR = ", "

@Composable
internal fun ReturnLeaving(
    palletReturn: PalletReturn,
    addresses: Choices<ReturnAddress>,
    working: PalletReturnWrite?,
    onLoadAddresses: () -> Unit,
    onSave: (collection: Boolean, addressId: Long?) -> Unit,
) {
    val leaving = palletReturn.leaving
    val address = leaving.deliveryAddress?.takeIf { it.isNotEmpty() }
    var isChanging by rememberSaveable(palletReturn.id) { mutableStateOf(false) }

    if (!leaving.canChange && address == null) {
        return
    }

    ListGroup {
        FulfilmentRow(
            title = stringResource(R.string.fulfilment_leaving_title),
            subtitle = if (palletReturn.isCollection) {
                stringResource(R.string.fulfilment_leaving_collects)
            } else {
                address?.joinToString(ADDRESS_SEPARATOR) ?: stringResource(R.string.fulfilment_leaving_no_address)
            },
            chip = if (leaving.canChange) stringResource(R.string.fulfilment_leaving_change) else null,
            onClick = if (leaving.canChange) ({ isChanging = true }) else null,
        )
    }

    if (isChanging) {
        LeavingSheet(
            palletReturn = palletReturn,
            addresses = addresses,
            isWorking = working is PalletReturnWrite.SetLeaving,
            onLoadAddresses = onLoadAddresses,
            onSave = { collection, addressId ->
                isChanging = false
                onSave(collection, addressId)
            },
            onDismiss = { isChanging = false },
        )
    }
}

@Composable
private fun LeavingSheet(
    palletReturn: PalletReturn,
    addresses: Choices<ReturnAddress>,
    isWorking: Boolean,
    onLoadAddresses: () -> Unit,
    onSave: (Boolean, Long?) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val leaving = palletReturn.leaving
    val selectedAddressId = addresses.items.firstOrNull { it.isSelected }?.id
    var collection by rememberSaveable { mutableStateOf(palletReturn.isCollection) }
    var addressId by rememberSaveable { mutableStateOf<Long?>(null) }
    val chosenAddressId = addressId ?: selectedAddressId
    val isChanged = collection != palletReturn.isCollection || (!collection && chosenAddressId != null && chosenAddressId != selectedAddressId)

    LaunchedEffect(leaving.canSetShipment) {
        if (leaving.canSetShipment) {
            onLoadAddresses()
        }
    }

    AikuBottomSheet(
        title = stringResource(R.string.fulfilment_leaving_question),
        subtitle = palletReturn.reference,
        onDismiss = onDismiss,
        footer = {
            AikuButton(
                text = stringResource(R.string.fulfilment_leaving_save),
                onClick = { if (isChanged) onSave(collection, if (collection) null else chosenAddressId) else onDismiss() },
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        if (leaving.canSetCollection || palletReturn.isCollection) {
            ChoiceRow(
                title = stringResource(R.string.fulfilment_leaving_collects),
                subtitle = stringResource(R.string.fulfilment_leaving_collects_hint),
                selected = collection,
                onClick = { collection = true },
            )
        }
        if (leaving.canSetShipment) {
            GroupLabel(text = stringResource(R.string.fulfilment_leaving_ship_to))
            when {
                addresses.problem != null -> GoodsOutLoadProblem(problem = addresses.problem, title = stringResource(R.string.fulfilment_leaving_addresses_failed), onRetry = onLoadAddresses)
                addresses.isLoading && addresses.items.isEmpty() -> CircularProgressIndicator(color = colors.link, strokeWidth = 2.dp)
                addresses.items.isEmpty() -> Text(text = stringResource(R.string.fulfilment_leaving_no_addresses), fontSize = 13.sp, color = colors.textTertiary)
                else -> Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    addresses.items.forEach { option ->
                        ChoiceRow(
                            title = option.lines.firstOrNull().orEmpty(),
                            subtitle = listOfNotNull(
                                option.lines.drop(1).joinToString(ADDRESS_SEPARATOR).takeIf { it.isNotBlank() },
                                stringResource(R.string.fulfilment_leaving_default_address).takeIf { option.isDefaultDelivery },
                            ).joinToString(" · ").takeIf { it.isNotBlank() },
                            selected = !collection && option.id == chosenAddressId,
                            onClick = {
                                collection = false
                                addressId = option.id
                            },
                        )
                    }
                }
            }
        }
    }
}
