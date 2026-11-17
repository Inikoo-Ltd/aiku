package io.aiku.droid.ui.fulfilment

import androidx.compose.runtime.Composable
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.ui.fulfilment.outbound.StoredItemAuditContent
import io.aiku.droid.ui.fulfilment.outbound.StoredItemAuditViewModel

@Composable
fun StoredItemAuditScreen(container: AppContainer, warehouseId: Long, id: Long, reference: String) {
    RequireFulfilment(section = R.string.section_stored_pallets) {
        val audit: StoredItemAuditViewModel = viewModel(
            key = "stored-item-audit-$warehouseId-$id",
            factory = viewModelFactory { initializer { StoredItemAuditViewModel(container.fulfilmentOutboundApi, container.sessions, warehouseId, id) } },
        )
        StoredItemAuditContent(audit = audit, reference = reference)
    }
}
