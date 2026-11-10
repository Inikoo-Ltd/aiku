package io.aiku.droid.ui.goodsin

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.GoodsInReturnsRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute

internal enum class DeliveryState(@StringRes val label: Int, val tone: Tone) {
    Arriving(R.string.goodsin_state_arriving, Tone.Neutral),
    Received(R.string.sd_state_received, Tone.Primary),
    Checked(R.string.sd_state_checked, Tone.Primary),
    BookingIn(R.string.sd_state_booking_in, Tone.Primary),
    BookedIn(R.string.sd_state_booked_in, Tone.Success),
}

internal data class SampleDelivery(val reference: String, val state: DeliveryState, val summary: String)

internal data class SourceCounts(
    @StringRes val source: Int,
    val route: Any,
    val arriving: Int,
    val received: Int,
    val checked: Int,
    val bookingIn: Int,
    val bookedInWeek: Int,
    val isFulfilment: Boolean = false,
)

internal data class DeliveryField(@StringRes val label: Int, val value: String)

internal data class CheckItem(val code: String, val name: String, val delivered: Int, val checked: Int?)

internal enum class CheckStatus { Passed, Failed, ToDo }

internal data class GoodsInCheck(val title: String, val status: CheckStatus, val detail: String?)

internal data class Placement(val quantity: Int, val location: String)

internal data class BookingItem(
    val code: String,
    val name: String,
    val checked: Int,
    val placements: List<Placement>,
    val pickingLocation: String?,
)

internal data class LocationChoice(val code: String, val detail: String)

internal enum class ReturnState(@StringRes val label: Int, val tone: Tone) {
    Received(R.string.sd_state_received, Tone.Primary),
    Returning(R.string.goodsin_return_returning, Tone.Primary),
    Returned(R.string.goodsin_return_returned, Tone.Success),
}

internal data class SampleReturn(val reference: String, val state: ReturnState, val summary: String)

internal enum class ReturnOutcome { ToLocation, Damaged, NotReturned }

internal data class ReturnItem(val code: String, val name: String, val quantity: Int, val outcome: ReturnOutcome?, val location: String?)

internal object GoodsInSamples {
    const val SUPPLIER = "[Supplier]"
    const val ORDERER = "[Supplier name] · [SUP-CODE]"
    const val PURCHASE_ORDERS = "POaw-0211, POaw-0214"
    const val ESTIMATED_RECEIVING = "11 Nov 2026"
    const val CAMERA_REFERENCE = "SDaw-0093"
    const val CAMERA_ITEM_CODE = "SKO-01187"
    const val CAMERA_START_COUNT = 37
    const val SCAN_TO_PICK_REFERENCE = "AR24811"
    const val PICKING_LOCATIONS = "A-07-01 for Wholesale · B-02-01 for Dropshipping"
    const val NEW_LOCATION = "A-09-04"
    const val NEW_LOCATION_DETAIL = "Area A · empty"
    const val RETURN_CUSTOMER = "[Customer]"
    const val RETURN_LOCATION = "A-07-01"

    val hubSources = listOf(
        SourceCounts(source = R.string.section_stock_deliveries, route = StockDeliveriesRoute, arriving = 3, received = 2, checked = 1, bookingIn = 1, bookedInWeek = 9),
        SourceCounts(source = R.string.goodsin_source_agents, route = StockDeliveriesRoute, arriving = 1, received = 0, checked = 0, bookingIn = 0, bookedInWeek = 2),
        SourceCounts(source = R.string.goodsin_source_partners, route = StockDeliveriesRoute, arriving = 0, received = 1, checked = 0, bookingIn = 0, bookedInWeek = 1),
        SourceCounts(source = R.string.section_fulfilment_deliveries, route = FulfilmentDeliveriesRoute, arriving = 2, received = 0, checked = 0, bookingIn = 1, bookedInWeek = 4, isFulfilment = true),
        SourceCounts(source = R.string.section_returns, route = GoodsInReturnsRoute, arriving = 0, received = 3, checked = 0, bookingIn = 0, bookedInWeek = 5),
    )

    val stateCounts = mapOf(
        DeliveryState.Arriving to 3,
        DeliveryState.Received to 2,
        DeliveryState.Checked to 1,
        DeliveryState.BookingIn to 1,
    )

    val deliveries = listOf(
        SampleDelivery(reference = "SDaw-0093", state = DeliveryState.Received, summary = "[Supplier] · 6 items · received today 08:40"),
        SampleDelivery(reference = "SDaw-0088", state = DeliveryState.Received, summary = "[Agent] · 3 items · received yesterday"),
        SampleDelivery(reference = "SDaw-0096", state = DeliveryState.Arriving, summary = "[Supplier] · 22 items · dispatched 07 Nov"),
        SampleDelivery(reference = "SDaw-0091", state = DeliveryState.BookingIn, summary = "[Supplier] · 3 of 5 items placed"),
        SampleDelivery(reference = "SDaw-0090", state = DeliveryState.Checked, summary = "[Production] · 4 items checked"),
    )

    val deliveryFields = listOf(
        DeliveryField(label = R.string.goodsin_field_type, value = "Container"),
        DeliveryField(label = R.string.goodsin_field_container_size, value = "40 ft"),
        DeliveryField(label = R.string.goodsin_field_incoterm, value = "FOB"),
        DeliveryField(label = R.string.goodsin_field_port_export, value = "[Port]"),
        DeliveryField(label = R.string.goodsin_field_port_import, value = "Felixstowe"),
        DeliveryField(label = R.string.goodsin_field_container_number, value = "[MSCU 123456 7]"),
        DeliveryField(label = R.string.goodsin_field_shipper_reference, value = "[REF-8812]"),
        DeliveryField(label = R.string.goodsin_field_tracking_number, value = "[TRK-0042]"),
        DeliveryField(label = R.string.goodsin_field_shipping_agent, value = "[Agent]"),
    )

    val arrivingItemFields = listOf(
        DeliveryField(label = R.string.goodsin_items, value = "22"),
        DeliveryField(label = R.string.goodsin_field_new_skos, value = "2"),
        DeliveryField(label = R.string.goodsin_field_weight, value = "1,240 kg"),
        DeliveryField(label = R.string.goodsin_field_volume, value = "18.4 m³"),
    )

    val checkingItemFields = listOf(
        DeliveryField(label = R.string.goodsin_items, value = "5"),
        DeliveryField(label = R.string.goodsin_field_received_checked, value = "3 of 5"),
        DeliveryField(label = R.string.goodsin_field_placed, value = "0"),
        DeliveryField(label = R.string.goodsin_field_new_skos, value = "1"),
        DeliveryField(label = R.string.goodsin_field_under, value = "1"),
        DeliveryField(label = R.string.goodsin_field_over, value = "0"),
        DeliveryField(label = R.string.goodsin_field_unit_mismatch, value = "0"),
        DeliveryField(label = R.string.goodsin_field_weight, value = "312 kg · partial"),
    )

    val checkItems = listOf(
        CheckItem(code = "SKO-00733", name = "Bath bomb – rose 180g", delivered = 48, checked = 48),
        CheckItem(code = "SKO-00412", name = "Lavender essential oil 10ml", delivered = 120, checked = 108),
        CheckItem(code = "SKO-01402", name = "Eucalyptus essential oil 10ml", delivered = 60, checked = 0),
        CheckItem(code = "SKO-01187", name = "Soy wax candle – vanilla", delivered = 72, checked = null),
        CheckItem(code = "SKO-00953", name = "Incense sticks – sandalwood", delivered = 200, checked = null),
    )

    val checks = listOf(
        GoodsInCheck(title = "[Check · packaging intact]", status = CheckStatus.Passed, detail = "Alex Morgan · 08:52"),
        GoodsInCheck(title = "[Check · labels match the delivery]", status = CheckStatus.Failed, detail = "“2 cartons without labels” · incident: [type] · qty 2"),
        GoodsInCheck(title = "[Check · pallets wrapped]", status = CheckStatus.ToDo, detail = null),
    )

    val bookingItems = listOf(
        BookingItem(code = "SKO-00733", name = "Bath bomb – rose 180g", checked = 48, placements = listOf(Placement(quantity = 48, location = "A-03-02")), pickingLocation = "A-03-02"),
        BookingItem(code = "SKO-00412", name = "Lavender essential oil 10ml", checked = 108, placements = listOf(Placement(quantity = 108, location = "A-01-01")), pickingLocation = "A-01-01"),
        BookingItem(code = "SKO-01187", name = "Soy wax candle – vanilla", checked = 72, placements = emptyList(), pickingLocation = "A-07-01"),
        BookingItem(code = "SKO-00953", name = "Incense sticks – sandalwood", checked = 200, placements = emptyList(), pickingLocation = "B-01-04"),
        BookingItem(code = "SKO-02210", name = "Rose quartz tumble stones", checked = 6, placements = emptyList(), pickingLocation = null),
    )

    val bookedInItems = listOf(
        BookingItem(code = "SKO-00733", name = "Bath bomb – rose 180g", checked = 48, placements = listOf(Placement(quantity = 48, location = "A-03-02")), pickingLocation = "A-03-02"),
        BookingItem(code = "SKO-00412", name = "Lavender essential oil 10ml", checked = 108, placements = listOf(Placement(quantity = 108, location = "A-01-01")), pickingLocation = "A-01-01"),
        BookingItem(code = "SKO-01187", name = "Soy wax candle – vanilla", checked = 72, placements = listOf(Placement(quantity = 72, location = "A-07-01")), pickingLocation = "A-07-01"),
        BookingItem(code = "SKO-00953", name = "Incense sticks – sandalwood", checked = 200, placements = listOf(Placement(quantity = 150, location = "B-01-04"), Placement(quantity = 50, location = "C-04-02")), pickingLocation = "B-01-04"),
    )

    val locationChoices = listOf(
        LocationChoice(code = "A-07-01", detail = "Picking location · Wholesale · 30 in location"),
        LocationChoice(code = "C-04-02", detail = "Storing · 6 in location"),
    )

    val returns = listOf(
        SampleReturn(reference = "[Return ref] 0412", state = ReturnState.Received, summary = "[Customer] · 3 items"),
        SampleReturn(reference = "[Return ref] 0409", state = ReturnState.Returning, summary = "[Customer] · 1 of 2 items processed"),
        SampleReturn(reference = "[Return ref] 0401", state = ReturnState.Returned, summary = "[Customer] · 4 items"),
    )

    val returnItems = listOf(
        ReturnItem(code = "SKO-00733", name = "Bath bomb – rose 180g", quantity = 2, outcome = ReturnOutcome.ToLocation, location = "A-03-02"),
        ReturnItem(code = "SKO-01187", name = "Soy wax candle – vanilla", quantity = 1, outcome = null, location = null),
    )

    fun checkItem(code: String): CheckItem = checkItems.firstOrNull { it.code == code } ?: checkItems[1]

    fun bookingItem(code: String): BookingItem = bookingItems.firstOrNull { it.code == code } ?: bookingItems[2]
}
