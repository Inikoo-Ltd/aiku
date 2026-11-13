package io.aiku.droid.ui.mywork

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.GoodsOutSamples
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.NoTasksRoute
import io.aiku.droid.ui.navigation.StockDeliveryCheckingRoute

internal enum class MyWorkModule(@StringRes val label: Int) {
    Warehouse(R.string.mywork_module_warehouse),
    Tasks(R.string.mywork_module_tasks),
}

internal data class MyWorkItem(
    val reference: String,
    val state: String,
    val subtitle: String,
    val module: MyWorkModule,
    val tone: Tone,
    val route: Any,
)

internal object MyWorkSamples {
    val items = listOf(
        MyWorkItem("AR24811", "To pick", "Delivery note · 14 items · aisle A–C", MyWorkModule.Warehouse, Tone.Warn, DeliveryNotePickingRoute(id = GoodsOutSamples.SAMPLE_DELIVERY_NOTE_ID, reference = "AR24811")),
        MyWorkItem("AR24790", "To pack", "Delivery note · 6 items · picked 10 min ago", MyWorkModule.Warehouse, Tone.Primary, DeliveryNotePackingRoute(id = GoodsOutSamples.SAMPLE_DELIVERY_NOTE_ID, reference = "AR24790")),
        MyWorkItem("SDaw-0093", "To check", "Stock delivery · 6 items received", MyWorkModule.Warehouse, Tone.Primary, StockDeliveryCheckingRoute("SDaw-0093")),
        MyWorkItem("A-12-03", "Audit", "Stock audit · location · 4 SKOs", MyWorkModule.Warehouse, Tone.Primary, LocationRoute("A-12-03")),
        MyWorkItem("acme-011", "Booking in", "Fulfilment delivery · [Customer A] · 3 of 4 pallets", MyWorkModule.Warehouse, Tone.Primary, FulfilmentBookInRoute("acme-011")),
        MyWorkItem("[Task]", "Due today", "From Aiku Tasks · assigned by [Supervisor]", MyWorkModule.Tasks, Tone.Neutral, NoTasksRoute),
    )
}
