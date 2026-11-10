package io.aiku.droid.ui.goodsout

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.navigation.DeliveryNoteCollectionRoute
import io.aiku.droid.ui.navigation.DeliveryNoteIncompleteSetRoute
import io.aiku.droid.ui.navigation.DeliveryNoteTakeOverRoute
import io.aiku.droid.ui.navigation.DeliveryNoteToDoRoute
import io.aiku.droid.ui.navigation.ExternalShipmentRoute

internal enum class LineStatus { ToHandle, Partial, Picked, Packed, Waiting, NotPicked }

internal data class SampleLine(
    val skuCode: String,
    val subtitle: String,
    val done: Int,
    val required: Int,
    val status: LineStatus,
    val reason: String? = null,
)

internal data class SampleTimelineEntry(@StringRes val label: Int, val date: String?)

internal data class SampleDetailLine(@DrawableRes val icon: Int, val text: String)

internal data class SampleDeliveryNoteRow(
    val reference: String,
    val stage: DeliveryNoteStage,
    val subtitle: String,
    val route: Any,
)

internal data class SampleBacklogChannel(
    val name: String,
    val toDo: Int,
    val picking: Int,
    val waiting: Int,
    val picked: Int,
    val packing: Int,
    val packed: Int,
    val finalised: Int,
    val total: Int,
)

internal data class SamplePickerNow(val name: String, val detail: String, val sessionReference: String?)

internal data class SampleStateCount(val stage: DeliveryNoteStage, val count: Int?)

internal data class SampleChoice(val title: String, val subtitle: String, val isAvailable: Boolean = true)

internal data class SampleLocationChoice(val code: String, val subtitle: String, val stock: String, val hasStock: Boolean)

internal data class SampleParcel(val name: String, val weight: String, val length: String, val width: String, val height: String)

internal data class SampleShipper(val name: String, val detail: String)

internal data class SampleOtherShipper(val name: String, val detail: String, val needsTrackingNumber: Boolean)

internal data class SampleFastPickLine(val id: Int, val location: String, val skuCode: String, val name: String, val quantity: Int)

internal data class SamplePackQueueNote(val id: Int, val reference: String, val items: String, val box: String, val weight: String)

internal data class SampleScanEntry(val skuCode: String, val detail: String, val isPicked: Boolean)

internal object GoodsOutSamples {
    const val PICKING_SESSION = "PSaw-0142"
    const val CHANNEL_WHOLESALE = "Wholesale"
    const val CHANNEL_DROPSHIPPING = "Dropshipping"
    const val PLATFORM_SHOPIFY = "Shopify"
    const val PICKER_ME = "Alex Morgan (you)"
    const val PICKER_NOW = "Alex Morgan"
    const val TROLLEY = "Trolley T-03"
    const val PICKED_BAY = "Bay 1"
    const val PACKER_DETAIL = "Same as picker · Alex Morgan"
    const val LINES_TO_HANDLE = 1
    const val PICKED_SUMMARY = "7 of 14 picked"
    const val PACKED_SUMMARY = "6 of 12 packed"
    const val PICKING_LOCATION = "A-03-02"
    const val PICKING_LOCATION_DETAIL = "SKO-00733 · 3 left on this item"
    const val PACKING_PARCEL = "Parcel 1"
    const val PACKING_PARCEL_DETAIL = "SKO-00733 · 4 left on this item"
    const val SHIPPING_ADDRESS = "[Street], [Town] [Postcode], United Kingdom"
    const val SHIPMENT_ADDRESS = "[Customer], [Street], [Town] [Postcode], GB"
    const val SELECTED_SAMPLE_COUNT = 3
    const val COLLECTION_SUMMARY = "1 parcel · 1.2 kg"
    const val DISPATCH_SUMMARY = "DPD (GB) · 2 boxes · 5.5 kg · labels and leaflets printed"
    const val DISPATCHED_ITEMS = "12 items dispatched"
    const val DISPATCHED_ITEMS_DETAIL = "2 not picked · out of stock"
    const val DISPATCHED_DETAIL = "2 boxes with DPD (GB)"

    val stateCounts = listOf(
        SampleStateCount(DeliveryNoteStage.ToDo, 47),
        SampleStateCount(DeliveryNoteStage.Picking, 17),
        SampleStateCount(DeliveryNoteStage.Waiting, 2),
        SampleStateCount(DeliveryNoteStage.Picked, 5),
        SampleStateCount(DeliveryNoteStage.Packing, 3),
        SampleStateCount(DeliveryNoteStage.Packed, 26),
        SampleStateCount(DeliveryNoteStage.Finalised, 10),
        SampleStateCount(DeliveryNoteStage.Dispatched, null),
    )

    val deliveryNotes = listOf(
        SampleDeliveryNoteRow("AR24811", DeliveryNoteStage.ToDo, "[Customer] · Wholesale · 14 items · 6.2 kg", DeliveryNoteToDoRoute("AR24811")),
        SampleDeliveryNoteRow("AR24812", DeliveryNoteStage.ToDo, "[Customer] · Dropshipping · Manual · 3 items · 1.1 kg", DeliveryNoteToDoRoute("AR24812")),
        SampleDeliveryNoteRow("AR24815", DeliveryNoteStage.ToDo, "[Customer] · Wholesale · 28 items · 12.4 kg", DeliveryNoteToDoRoute("AR24815")),
        SampleDeliveryNoteRow("AR24816", DeliveryNoteStage.ToDo, "[Customer] · Dropshipping · Shopify · 4 items · 6.2 kg", DeliveryNoteToDoRoute("AR24816")),
        SampleDeliveryNoteRow("AR24819", DeliveryNoteStage.ToDo, "[Customer] · Wholesale · 9 items · 3.8 kg", DeliveryNoteToDoRoute("AR24819")),
        SampleDeliveryNoteRow("AR24840", DeliveryNoteStage.Packed, "[Customer] · B2C · Collection · 1 item", DeliveryNoteCollectionRoute("AR24840")),
        SampleDeliveryNoteRow("AR24817", DeliveryNoteStage.Picking, "[Customer] · Wholesale · picked by Sam P.", DeliveryNoteTakeOverRoute("AR24817")),
        SampleDeliveryNoteRow("AR24826", DeliveryNoteStage.Waiting, "[Customer] · Wholesale · incomplete set", DeliveryNoteIncompleteSetRoute("AR24826")),
        SampleDeliveryNoteRow("AR24822", DeliveryNoteStage.Packed, "[Customer] · Marketplaces · TikTok · shipped by TikTok", ExternalShipmentRoute("AR24822")),
    )

    val backlogChannels = listOf(
        SampleBacklogChannel("Wholesale", toDo = 12, picking = 9, waiting = 2, picked = 3, packing = 2, packed = 14, finalised = 6, total = 48),
        SampleBacklogChannel("Dropshipping", toDo = 31, picking = 6, waiting = 0, picked = 2, packing = 1, packed = 9, finalised = 3, total = 52),
        SampleBacklogChannel("B2C", toDo = 4, picking = 2, waiting = 0, picked = 0, packing = 0, packed = 3, finalised = 1, total = 10),
    )

    val pickersNow = listOf(
        SamplePickerNow("Alex Morgan", "2 orders · 1 trolley", sessionReference = null),
        SamplePickerNow("Sam P.", "Picking session PSaw-0142 · 12 orders · 2 trolleys", sessionReference = PICKING_SESSION),
    )

    const val TO_DO_SHIPPING_LABEL_NOTE = ""
    const val TO_DO_CUSTOMER_NOTE = "Please use plain packaging, no invoice in the box."
    const val TO_DO_WAREHOUSE_NOTE = "Fragile: wrap the candles separately."

    val toDoTimeline = listOf(
        SampleTimelineEntry(R.string.goodsout_timeline_created, "7 Nov 22:52"),
        SampleTimelineEntry(R.string.goodsout_timeline_in_session, null),
        SampleTimelineEntry(R.string.dn_state_picking, null),
        SampleTimelineEntry(R.string.dn_state_picked, null),
        SampleTimelineEntry(R.string.dn_state_packing, null),
        SampleTimelineEntry(R.string.dn_state_packed, null),
        SampleTimelineEntry(R.string.dn_state_finalised, null),
        SampleTimelineEntry(R.string.dn_state_dispatched, null),
    )

    val toDoOrder = listOf(
        SampleDetailLine(R.drawable.ic_ui_order, "AR24816 · order"),
        SampleDetailLine(R.drawable.ic_ui_person, "[Dropship customer] (20523)"),
        SampleDetailLine(R.drawable.ic_ui_person, "[End customer name]"),
        SampleDetailLine(R.drawable.ic_ui_mail, "[end customer email]"),
        SampleDetailLine(R.drawable.ic_ui_phone, "[phone]"),
    )

    val toDoSummary = listOf(
        SampleDetailLine(R.drawable.ic_ui_sko, "4 items (6 SKOs, 14 units)"),
        SampleDetailLine(R.drawable.ic_ui_clock, "~12 min picking, ~6 min packing"),
        SampleDetailLine(R.drawable.ic_ui_weight, "6.2 kg"),
    )

    val toDoLines = listOf(
        SampleLine("SKO-00412", "A-01-01 · Lavender essential oil 10ml", done = 0, required = 4, status = LineStatus.ToHandle),
        SampleLine("SKO-00733", "A-03-02 · Bath bomb – rose 180g", done = 0, required = 6, status = LineStatus.ToHandle),
        SampleLine("SKO-01187", "A-07-01 · Soy wax candle – vanilla", done = 0, required = 2, status = LineStatus.ToHandle),
        SampleLine("SKO-00953", "B-01-04 · Incense sticks – sandalwood", done = 0, required = 2, status = LineStatus.ToHandle),
    )

    const val PICKING_CUSTOMER_NOTE = "Deliver before 10am, ring the bell at goods-in."
    const val PICKING_WAREHOUSE_NOTE = "Orders for this customer always go on a pallet."

    val pickingTimeline = listOf(
        SampleTimelineEntry(R.string.goodsout_timeline_created, "9 Nov 08:10"),
        SampleTimelineEntry(R.string.dn_state_picking, "10 Nov 09:12"),
        SampleTimelineEntry(R.string.dn_state_picked, null),
        SampleTimelineEntry(R.string.dn_state_packing, null),
        SampleTimelineEntry(R.string.dn_state_packed, null),
        SampleTimelineEntry(R.string.dn_state_finalised, null),
        SampleTimelineEntry(R.string.dn_state_dispatched, null),
    )

    val pickingOrder = listOf(
        SampleDetailLine(R.drawable.ic_ui_order, "AR24811 · order"),
        SampleDetailLine(R.drawable.ic_ui_person, "[Customer] (18831)"),
        SampleDetailLine(R.drawable.ic_ui_person, "[Contact name]"),
        SampleDetailLine(R.drawable.ic_ui_mail, "[email]"),
        SampleDetailLine(R.drawable.ic_ui_phone, "[phone]"),
    )

    val pickingSummary = listOf(
        SampleDetailLine(R.drawable.ic_ui_sko, "4 items (14 SKOs, 14 units)"),
        SampleDetailLine(R.drawable.ic_ui_clock, "~18 min picking, ~8 min packing"),
        SampleDetailLine(R.drawable.ic_ui_weight, "6.2 kg"),
    )

    val pickingLines = listOf(
        SampleLine("SKO-00412", "A-01-01 · Lavender essential oil 10ml", done = 4, required = 4, status = LineStatus.Picked),
        SampleLine("SKO-00733", "A-03-02 · Bath bomb – rose 180g · 30 in location", done = 3, required = 6, status = LineStatus.Partial),
        SampleLine("SKO-01187", "A-07-01 · Soy wax candle – vanilla", done = 0, required = 2, status = LineStatus.Waiting, reason = "Waiting · warehouse"),
        SampleLine("SKO-00953", "B-01-04 · Incense sticks – sandalwood", done = 0, required = 2, status = LineStatus.NotPicked, reason = "Not picked · out of stock"),
    )

    val handledLines = listOf(
        SampleLine("SKO-00412", "A-01-01 · Lavender essential oil 10ml", done = 4, required = 4, status = LineStatus.Picked),
        SampleLine("SKO-00733", "A-03-02 · Bath bomb – rose 180g", done = 6, required = 6, status = LineStatus.Picked),
        SampleLine("SKO-01187", "C-04-02 · Soy wax candle – vanilla · from another location", done = 2, required = 2, status = LineStatus.Picked),
        SampleLine("SKO-00953", "B-01-04 · Incense sticks – sandalwood", done = 0, required = 2, status = LineStatus.NotPicked, reason = "Not picked · out of stock"),
    )

    val packerMustAdd = listOf("2 × [BOX-M]", "1 × [GIFT-WRAP]")

    val packingLines = listOf(
        SampleLine("SKO-00412", "Lavender essential oil 10ml · Parcel 1", done = 4, required = 4, status = LineStatus.Packed),
        SampleLine("SKO-00733", "Bath bomb – rose 180g · Parcel 1", done = 2, required = 6, status = LineStatus.Partial),
        SampleLine("SKO-01187", "Soy wax candle – vanilla · no parcel yet", done = 0, required = 2, status = LineStatus.ToHandle),
    )

    val packingParcels = listOf(
        SampleParcel("Parcel 1", weight = "3.4", length = "40", width = "30", height = "20"),
        SampleParcel("Parcel 2", weight = "2.1", length = "", width = "", height = ""),
    )

    val apiShippers = listOf(
        SampleShipper("DPD (GB)", "[phone] · [tracking url]"),
        SampleShipper("APC (GB)", "[phone] · [tracking url]"),
    )

    val shipmentParcels = listOf(
        SampleChoice("Parcel 1", "3.4 kg · 40 × 30 × 20 cm"),
        SampleChoice("Parcel 2", "2.1 kg · 30 × 20 × 15 cm"),
    )

    val otherShippers = listOf(
        SampleOtherShipper("[Local courier]", "No API · tracking number by hand", needsTrackingNumber = true),
        SampleOtherShipper("[Pallet carrier]", "No API · tracking number by hand", needsTrackingNumber = true),
        SampleOtherShipper("Packeta (SK)", "API · label created automatically", needsTrackingNumber = false),
    )

    const val MANUAL_TRACKING_NUMBER = "[TRK-0099-1234]"

    val shipments = listOf(
        SampleChoice("DPD (GB)", "Tracking [TRK-1234-5678] · 2 parcels · shipment cost [£ 6.20]"),
    )

    val leaflets = listOf(
        SampleChoice("Leaflet · [Spring catalogue]", "Goes in the parcel"),
        SampleChoice("Leaflet · [Returns form]", "Goes in the parcel"),
    )

    val leafletsPrinted = listOf(true, true)

    val collectionParcels = listOf(
        SampleChoice("Parcel 1", "1.2 kg · 25 × 20 × 10 cm"),
    )

    val collectionLeaflets = listOf(
        SampleChoice("Leaflet · [Spring catalogue]", "Goes in the parcel"),
    )

    val pickers = listOf(
        SampleChoice("Alex Morgan (you)", "Picking 1 order"),
        SampleChoice("Sam P.", "Picking session PSaw-0142"),
        SampleChoice("Jo K.", "No current work"),
    )

    const val PICKER_SHEET_SELECTED = 1

    val availableTrolleys = listOf(
        SampleChoice("T-03", "Free"),
        SampleChoice("T-05", "Free"),
        SampleChoice("T-07", "Free"),
    )

    val unavailableTrolleys = listOf(
        SampleChoice("T-01", "Delivery note AR24790", isAvailable = false),
        SampleChoice("T-02", "Picking session PSaw-0142", isAvailable = false),
    )

    val pickedBays = listOf(
        SampleChoice("Bay 1", "Free"),
        SampleChoice("Bay 2", "2 delivery notes"),
        SampleChoice("Bay 4", "Free"),
    )

    const val LINE_SHEET_PICKED = 3
    const val LINE_SHEET_REQUIRED = 6
    const val LINE_SHEET_DETAIL = "A-03-02 · Bath bomb – rose 180g"
    const val SKO_NAME = "Bath bomb – rose 180g"

    val notPickedReasons = listOf("Out of stock", "Cancelled by customer", "Cancelled by warehouse", "Not applicable")

    val pickLocations = listOf(
        SampleLocationChoice("A-03-02", "Picking location · Wholesale", "30 stocks", hasStock = true),
        SampleLocationChoice("C-04-02", "Storing", "14 stocks", hasStock = true),
        SampleLocationChoice("B-02-01", "Picking location · Dropshipping", "Empty", hasStock = false),
    )

    const val PICK_LOCATION_SELECTED = 1

    val wholesalePickingLocations = listOf(
        SampleChoice("A-03-02", "30 stocks"),
        SampleChoice("C-04-02", "14 stocks"),
    )

    val dropshippingPickingLocations = listOf(
        SampleChoice("B-02-01", "Empty"),
        SampleChoice("C-04-02", "14 stocks"),
    )

    val printers = listOf(
        SampleChoice("Dispatch-1 · A4", "Packing bench 1 · last used"),
        SampleChoice("Goods-out office · A4", ""),
    )

    const val PRINT_PAGES = 2

    const val SCAN_PICKED_COUNT = 8
    const val SCAN_TOTAL_COUNT = 14
    const val SCAN_QUEUED = "Each scan picks 1 · 2 queued"
    const val SCAN_SKO = "SKO-00733"
    const val SCAN_SKO_DETAIL = "A-03-02 · Bath bomb – rose 180g"
    const val SCAN_REMAINING = 23
    const val SCAN_PICKED = 1
    const val SCAN_AMOUNT = 6

    val scannedEntries = listOf(
        SampleScanEntry("SKO-00733", "09:41:20 · It belongs to this order", isPicked = true),
        SampleScanEntry("SKO-00412", "09:41:12 · not on this delivery note", isPicked = false),
        SampleScanEntry("SKO-00733", "09:40:58", isPicked = true),
    )

    val fastPickLines = listOf(
        SampleFastPickLine(1, "A-01-01", "SKO-00412", "Lavender essential oil 10ml", 6),
        SampleFastPickLine(2, "A-03-02", "SKO-00733", "Bath bomb – rose 180g", 6),
        SampleFastPickLine(3, "A-07-01", "SKO-01187", "Soy wax candle – vanilla", 1),
        SampleFastPickLine(4, "A-12-03", "SKO-00953", "Incense sticks – sandalwood", 2),
        SampleFastPickLine(5, "B-01-04", "SKO-00734", "Bath bomb – lavender 180g", 3),
        SampleFastPickLine(6, "C-02-01", "SKO-01402", "Eucalyptus essential oil 10ml", 1),
    )

    const val FAST_PICK_DONE_ID = 1

    val packQueue = listOf(
        SamplePackQueueNote(1, "AR24790", "3 items", "Box S", "1.2 kg"),
        SamplePackQueueNote(2, "AR24811", "14 items", "Box M", "6.2 kg"),
        SamplePackQueueNote(3, "AR24812", "3 items", "Box S", "1.1 kg"),
        SamplePackQueueNote(4, "AR24815", "28 items", "2 × Box L", "12.4 kg"),
        SamplePackQueueNote(5, "AR24816", "1 item", "Envelope", "0.3 kg"),
    )
}
