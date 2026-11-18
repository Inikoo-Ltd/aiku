package io.aiku.droid.ui.fulfilment

import androidx.compose.runtime.Composable
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.ui.fulfilment.outbound.PalletReturnFrame
import io.aiku.droid.ui.fulfilment.outbound.SHIPMENT_PURPOSE
import io.aiku.droid.ui.fulfilment.outbound.ShipmentContent
import io.aiku.droid.ui.fulfilment.outbound.rememberPalletReturn

@Composable
fun ReturnShipmentScreen(container: AppContainer, warehouseId: Long, id: Long, reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        val viewModel = rememberPalletReturn(container, warehouseId, id, purpose = SHIPMENT_PURPOSE)
        PalletReturnFrame(viewModel = viewModel, reference = reference, screen = null) { state, palletReturn ->
            ShipmentContent(viewModel = viewModel, state = state, palletReturn = palletReturn)
        }
    }
}
