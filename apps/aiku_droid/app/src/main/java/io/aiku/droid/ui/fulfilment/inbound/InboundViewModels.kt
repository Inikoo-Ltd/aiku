package io.aiku.droid.ui.fulfilment.inbound

import androidx.compose.runtime.Composable
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.AppContainer
import io.aiku.droid.data.api.PalletFilter
import io.aiku.droid.ui.clocking.LocalClocking

@Composable
internal fun rememberPalletDeliveries(container: AppContainer, warehouseId: Long): PalletDeliveriesViewModel {
    val clocking = LocalClocking.current

    return viewModel(
        key = "pallet-deliveries-$warehouseId",
        factory = viewModelFactory {
            initializer {
                PalletDeliveriesViewModel(
                    api = container.fulfilmentInboundApi,
                    sessions = container.sessions,
                    warehouseId = warehouseId,
                    cache = container.screenCache,
                    zone = { clocking.state.value.zone },
                )
            }
        },
    )
}

@Composable
internal fun rememberPalletDelivery(container: AppContainer, warehouseId: Long, deliveryId: Long): PalletDeliveryViewModel = viewModel(
    key = "pallet-delivery-$warehouseId-$deliveryId",
    factory = viewModelFactory { initializer { PalletDeliveryViewModel(container.fulfilmentInboundApi, container.sessions, warehouseId, deliveryId) } },
)

@Composable
internal fun rememberPallets(container: AppContainer, warehouseId: Long, filter: PalletFilter): PalletsViewModel = viewModel(
    key = "pallets-$warehouseId-${filter.key}",
    factory = viewModelFactory { initializer { PalletsViewModel(container.fulfilmentInboundApi, container.sessions, warehouseId, initialFilter = filter) } },
)

@Composable
internal fun rememberPallet(container: AppContainer, warehouseId: Long, palletId: Long): PalletViewModel = viewModel(
    key = "pallet-$warehouseId-$palletId",
    factory = viewModelFactory { initializer { PalletViewModel(container.fulfilmentInboundApi, container.sessions, warehouseId, palletId) } },
)
