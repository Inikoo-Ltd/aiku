package io.aiku.droid.ui.fulfilment

import androidx.compose.runtime.Composable
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.ui.fulfilment.outbound.StoredItemsContent
import io.aiku.droid.ui.fulfilment.outbound.StoredItemsViewModel

@Composable
fun StoredItemsScreen(container: AppContainer, warehouseId: Long) {
    RequireFulfilment(section = R.string.section_stored_items) {
        val items: StoredItemsViewModel = viewModel(
            key = "stored-items-$warehouseId",
            factory = viewModelFactory { initializer { StoredItemsViewModel(container.fulfilmentOutboundApi, container.sessions, warehouseId) } },
        )
        StoredItemsContent(items = items)
    }
}
