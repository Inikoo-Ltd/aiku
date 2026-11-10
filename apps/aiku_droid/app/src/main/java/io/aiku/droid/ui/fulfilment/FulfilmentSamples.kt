package io.aiku.droid.ui.fulfilment

internal const val CUSTOMER_A_SAMPLE = "[Customer A]"
internal const val CUSTOMER_B_SAMPLE = "[Customer B]"

internal data class FulfilmentDeliverySample(
    val reference: String,
    val state: FulfilmentDeliveryState,
    val summary: String,
)

internal val fulfilmentDeliveriesSample = listOf(
    FulfilmentDeliverySample("bravo-007", FulfilmentDeliveryState.Received, "[Customer B] · 2 pallets, 8 boxes"),
    FulfilmentDeliverySample("acme-012", FulfilmentDeliveryState.Confirmed, "[Customer A] · 6 pallets · arriving today"),
    FulfilmentDeliverySample("acme-011", FulfilmentDeliveryState.BookingIn, "[Customer A] · 3 of 4 pallets booked in"),
)

internal val fulfilmentDeliveryCountsSample = mapOf(
    FulfilmentDeliveryState.Confirmed to 2,
    FulfilmentDeliveryState.Received to 1,
    FulfilmentDeliveryState.BookingIn to 1,
)

internal data class ReceivedLineSample(
    val reference: String,
    val type: FulfilmentPalletType,
    val description: String,
    val state: FulfilmentDeliveryState,
)

internal const val RECEIVED_DELIVERY_CUSTOMER_SAMPLE = CUSTOMER_B_SAMPLE
internal const val RECEIVED_PALLET_COUNT_SAMPLE = 10

internal val receivedLinesSample = listOf(
    ReceivedLineSample("bravo-p0118", FulfilmentPalletType.Pallet, "Customer ref “Spring stock 1”", FulfilmentDeliveryState.Received),
    ReceivedLineSample("bravo-p0119", FulfilmentPalletType.Pallet, "Customer ref “Spring stock 2”", FulfilmentDeliveryState.Received),
    ReceivedLineSample("bravo-p0120 … p0127", FulfilmentPalletType.Box, "8 boxes · “Gift sets”", FulfilmentDeliveryState.Received),
)

internal data class BookInPalletSample(
    val reference: String,
    val type: FulfilmentPalletType,
    val location: String?,
    val state: FulfilmentDeliveryState,
    val customerReference: String? = null,
    val suggestedLocation: String? = null,
    val suggestedArea: String? = null,
)

internal val bookInPalletsSample = listOf(
    BookInPalletSample("acme-p0231", FulfilmentPalletType.Pallet, "D-02-01", FulfilmentDeliveryState.BookedIn),
    BookInPalletSample("acme-p0232", FulfilmentPalletType.Pallet, "D-02-02", FulfilmentDeliveryState.BookedIn),
    BookInPalletSample("acme-p0233", FulfilmentPalletType.Oversize, "D-05-01", FulfilmentDeliveryState.BookedIn),
    BookInPalletSample(
        reference = "acme-p0234",
        type = FulfilmentPalletType.Pallet,
        location = null,
        state = FulfilmentDeliveryState.BookingIn,
        customerReference = "“Winter stock 4”",
        suggestedLocation = "D-02-04",
        suggestedArea = "Area D · fulfilment storage",
    ),
)

internal const val BOOKED_IN_CUSTOMER_SAMPLE = CUSTOMER_A_SAMPLE
internal const val BOOKED_IN_PALLET_COUNT_SAMPLE = 4

internal data class StoredPalletSample(
    val reference: String,
    val state: FulfilmentPalletState,
    val summary: String,
)

internal val storedPalletsSample = listOf(
    StoredPalletSample("acme-p0231", FulfilmentPalletState.Storing, "[Customer A] · Pallet · D-02-01"),
    StoredPalletSample("acme-p0140", FulfilmentPalletState.Storing, "[Customer A] · Pallet · D-03-01 · 3 stored items"),
    StoredPalletSample("bravo-p0097", FulfilmentPalletState.Damaged, "[Customer B] · Pallet · no location"),
    StoredPalletSample("acme-p0051", FulfilmentPalletState.Lost, "[Customer A] · Box · no location"),
)

internal val storedPalletCountsSample = mapOf(
    StoredPalletFilter.Storing to 642,
    StoredPalletFilter.Receiving to 10,
    StoredPalletFilter.Returning to 4,
    StoredPalletFilter.Incidents to 3,
)

internal data class PalletFactsSample(
    val state: FulfilmentPalletState,
    val type: FulfilmentPalletType,
    val location: String,
    val customerReference: String,
)

internal const val PALLET_CUSTOMER_SAMPLE = CUSTOMER_A_SAMPLE
internal const val PALLET_AUDIT_SAMPLE = "acme-sia014"

internal val palletFactsSample = PalletFactsSample(
    state = FulfilmentPalletState.Storing,
    type = FulfilmentPalletType.Pallet,
    location = "D-03-01",
    customerReference = "“Spring range”",
)

internal data class PalletStoredItemSample(val code: String, val description: String, val quantity: Int)

internal val palletStoredItemsSample = listOf(
    PalletStoredItemSample("MUG-BLUE", "Ceramic mug – blue · EAN 5060000000017", 40),
    PalletStoredItemSample("MUG-GREEN", "Ceramic mug – green", 4),
    PalletStoredItemSample("TOWEL-SET", "Tea towel set", 18),
)

internal const val INCIDENT_NOTE_SAMPLE = "Forklift hit the corner, 3 boxes crushed"

internal data class PalletIncidentSample(
    val reference: String,
    val kind: PalletIncidentKind,
    val summary: String,
)

internal val palletIncidentsSample = listOf(
    PalletIncidentSample("bravo-p0097", PalletIncidentKind.Damaged, "“Forklift hit the corner” · Jo K. · 06 Nov"),
    PalletIncidentSample("acme-p0051", PalletIncidentKind.Lost, "“Not at D-04-06 at audit” · Sam P. · 02 Nov"),
    PalletIncidentSample("acme-p0122", PalletIncidentKind.Other, "Not picked in return acme-r003 · “Pallet wet”"),
)

internal data class StoredItemSample(
    val code: String,
    val state: StoredItemState,
    val summary: String,
    val quantity: Int,
)

internal val storedItemsSample = listOf(
    StoredItemSample("MUG-BLUE", StoredItemState.Active, "[Customer A] · Ceramic mug – blue · 2 pallets", 64),
    StoredItemSample("MUG-GREEN", StoredItemState.Active, "[Customer A] · Ceramic mug – green · 1 pallet", 4),
    StoredItemSample("GIFT-L", StoredItemState.Discontinuing, "[Customer B] · Gift box large · 3 pallets", 210),
)

internal data class AuditLineSample(val code: String, val expected: Int, val counted: Int?)

internal const val AUDIT_PALLET_SAMPLE = "acme-p0140"
internal const val AUDIT_LAST_SCANNED_SAMPLE = "MUG-GREEN"
internal val auditScopeSample = StoredItemAuditScope.Pallet

internal val auditLinesSample = listOf(
    AuditLineSample("MUG-BLUE", expected = 40, counted = 40),
    AuditLineSample("MUG-GREEN", expected = 4, counted = 2),
    AuditLineSample("TOWEL-SET", expected = 18, counted = null),
)

internal data class FulfilmentReturnSample(
    val reference: String,
    val state: FulfilmentReturnState,
    val type: FulfilmentReturnType,
    val summary: String,
)

internal val fulfilmentReturnsSample = listOf(
    FulfilmentReturnSample("acme-r004", FulfilmentReturnState.Confirmed, FulfilmentReturnType.Pallets, "[Customer A] · Pallets (wholesale) · 2 pallets"),
    FulfilmentReturnSample("bravo-r011", FulfilmentReturnState.Picking, FulfilmentReturnType.StoredItems, "[Customer B] · Stored items · 4 of 6 picked"),
    FulfilmentReturnSample("acme-r009", FulfilmentReturnState.Picking, FulfilmentReturnType.Dropshipping, "[Customer A] · Dropshipping · from Shopify"),
    FulfilmentReturnSample("acme-r003", FulfilmentReturnState.Picked, FulfilmentReturnType.Pallets, "[Customer A] · Pallets (wholesale) · ready to dispatch"),
)

internal data class ReturnPalletSample(
    val reference: String,
    val type: FulfilmentPalletType,
    val location: String,
    val state: ReturnPalletState,
)

internal const val RETURN_PALLETS_CUSTOMER_SAMPLE = CUSTOMER_A_SAMPLE

internal val returnPalletsSample = listOf(
    ReturnPalletSample("acme-p0118", FulfilmentPalletType.Pallet, "D-01-03", ReturnPalletState.Picked),
    ReturnPalletSample("acme-p0121", FulfilmentPalletType.Pallet, "D-01-07", ReturnPalletState.ToPick),
)

internal data class ReturnItemSample(
    val code: String,
    val name: String,
    val picked: Int,
    val required: Int,
    val onPallet: Int,
)

internal data class ReturnItemPalletSample(
    val reference: String,
    val location: String,
    val items: List<ReturnItemSample>,
)

internal const val RETURN_ITEMS_CUSTOMER_SAMPLE = CUSTOMER_B_SAMPLE

internal val returnItemPalletsSample = listOf(
    ReturnItemPalletSample(
        reference = "bravo-p0140",
        location = "D-03-01",
        items = listOf(
            ReturnItemSample("GIFT-L", "Gift box large", picked = 4, required = 4, onPallet = 4),
            ReturnItemSample("REED-100", "Reed diffuser 100ml", picked = 1, required = 2, onPallet = 1),
        ),
    ),
    ReturnItemPalletSample(
        reference = "bravo-p0152",
        location = "D-04-06",
        items = listOf(
            ReturnItemSample("CANDLE-AMB", "Soy candle – amber", picked = 0, required = 1, onPallet = 1),
        ),
    ),
)

internal const val RETURN_PICKED_CUSTOMER_SAMPLE = CUSTOMER_A_SAMPLE
internal val returnPickedPalletsSample = listOf("acme-p0118", "acme-p0121")

internal const val RETURN_SHIPPER_SAMPLE = "[Pallet carrier]"
internal const val RETURN_TRACKING_SAMPLE = "[number]"
internal const val RETURN_SHIPMENT_PALLET_COUNT_SAMPLE = 2

internal const val RETURN_DISPATCHED_CUSTOMER_SAMPLE = CUSTOMER_A_SAMPLE
internal const val RETURN_DISPATCHED_PALLET_COUNT_SAMPLE = 2
