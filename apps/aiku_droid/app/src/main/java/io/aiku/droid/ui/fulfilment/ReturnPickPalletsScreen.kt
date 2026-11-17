package io.aiku.droid.ui.fulfilment

import androidx.compose.runtime.Composable
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.ui.fulfilment.outbound.PalletReturnFrame
import io.aiku.droid.ui.fulfilment.outbound.PickPalletsContent
import io.aiku.droid.ui.fulfilment.outbound.ReturnScreen
import io.aiku.droid.ui.fulfilment.outbound.rememberPalletReturn

@Composable
fun ReturnPickPalletsScreen(container: AppContainer, warehouseId: Long, id: Long, reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        val viewModel = rememberPalletReturn(container, warehouseId, id, purpose = ReturnScreen.PickPallets.name)
        PalletReturnFrame(viewModel = viewModel, reference = reference, screen = ReturnScreen.PickPallets) { state, palletReturn ->
            PickPalletsContent(viewModel = viewModel, state = state, palletReturn = palletReturn)
        }
    }
}
