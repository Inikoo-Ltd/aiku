package io.aiku.droid.ui.fulfilment

import androidx.compose.runtime.Composable
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.ui.clocking.LocalClocking
import io.aiku.droid.ui.fulfilment.outbound.PalletReturnsContent
import io.aiku.droid.ui.fulfilment.outbound.PalletReturnsViewModel

@Composable
fun FulfilmentReturnsScreen(container: AppContainer, warehouseId: Long) {
    val clocking = LocalClocking.current

    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        val returns: PalletReturnsViewModel = viewModel(
            key = "pallet-returns-$warehouseId",
            factory = viewModelFactory {
                initializer {
                    PalletReturnsViewModel(
                        api = container.fulfilmentOutboundApi,
                        sessions = container.sessions,
                        warehouseId = warehouseId,
                        cache = container.screenCache,
                        zone = { clocking.state.value.zone },
                    )
                }
            },
        )
        PalletReturnsContent(returns = returns)
    }
}
