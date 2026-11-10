package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.components.Tone

internal enum class WaitingReason(@StringRes val label: Int) {
    Warehouse(R.string.goodsout_extra_waiting_warehouse),
    CustomerService(R.string.goodsout_extra_waiting_cs),
}

internal data class SampleWaitingLine(
    val skoCode: String,
    val reason: WaitingReason,
    val picked: Int,
    val required: Int,
    val detail: String,
)

internal data class SampleLine(
    val skoCode: String,
    val name: String,
    val location: String,
    val picked: Int,
    val required: Int,
)

internal data class SampleShipper(val name: String, val shipmentsToday: Int)

internal enum class SessionState(@StringRes val label: Int, val tone: Tone) {
    Picking(R.string.goodsout_extra_session_picking, Tone.Primary),
    InProcess(R.string.goodsout_extra_session_in_process, Tone.Neutral),
    Waiting(R.string.goodsout_extra_session_waiting, Tone.Warn),
    PickingFinished(R.string.goodsout_extra_session_picking_finished, Tone.Success),
}

internal sealed interface SessionProgress {
    data class Picking(val deliveryNotes: Int, val trolleys: Int, val percentPicked: Int) : SessionProgress
    data class NotStarted(val deliveryNotes: Int) : SessionProgress
    data class Waiting(val waitingDeliveryNotes: Int) : SessionProgress
    data class Packing(val percentPacked: Int) : SessionProgress
}

internal data class SamplePickingSession(
    val reference: String,
    val state: SessionState,
    val pickerName: String?,
    val progress: SessionProgress,
)

internal data class SampleTrolleyLoad(val trolley: String, val deliveryNotes: Int)

internal data class SampleLocationPick(val location: String, val skoCode: String, val quantity: Int, val deliveryNotes: Int)

internal data class SampleSessionNote(val trolley: String, val deliveryNote: String)

internal data class SamplePutInto(val trolley: String, val deliveryNote: String, val picked: Int, val required: Int)

internal data class SamplePackNote(val deliveryNote: String, val trolley: String, val packed: Int, val items: Int)

internal sealed interface TrolleyUse {
    data class DeliveryNote(val reference: String) : TrolleyUse
    data class Session(val reference: String) : TrolleyUse
}

internal data class SampleTrolley(val code: String, val use: TrolleyUse?)

internal data class SampleBay(val name: String, val deliveryNotes: Int, val waiting: Int)

internal data class SampleChatMessage(val isMine: Boolean, val author: String, val time: String, val text: String)

internal enum class SetPartState(@StringRes val label: Int, val tone: Tone) {
    NotFound(R.string.goodsout_extra_set_part_not_found, Tone.Danger),
    PutBack(R.string.goodsout_extra_set_part_put_back, Tone.Warn),
}

internal data class SampleSetPart(
    val skoCode: String,
    val name: String,
    val part: Int,
    val parts: Int,
    val state: SetPartState,
    val picked: Int,
    val required: Int,
    val returnLocation: String?,
)

internal data class SampleParcel(val number: Int, val weightKg: String, val length: String, val width: String, val height: String)

internal data class SampleTariffLine(
    val skoCode: String,
    val tariffCode: String?,
    val origin: String,
    val name: String?,
    val isDangerous: Boolean = false,
)

internal data class SampleCustomsRow(
    val row: Int,
    val description: String,
    val tariffCode: String?,
    val origin: String,
    val quantity: Int,
    val value: String,
)

internal enum class EmailKind(@StringRes val label: Int) {
    DispatchConfirmation(R.string.goodsout_extra_email_dispatch_confirmation),
    TrackingUpdate(R.string.goodsout_extra_email_tracking_update),
}

internal enum class EmailState(@StringRes val label: Int, val tone: Tone) {
    Opened(R.string.goodsout_extra_email_opened, Tone.Success),
    Delivered(R.string.goodsout_extra_email_delivered, Tone.Primary),
    SoftBounce(R.string.goodsout_extra_email_soft_bounce, Tone.Warn),
}

internal data class SampleEmail(
    val kind: EmailKind,
    val state: EmailState,
    val recipient: String,
    val sentAt: String,
    val orderReference: String?,
    val hasPreview: Boolean,
)

internal object GoodsOutExtraSamples {
    const val WHOLESALE = "Wholesale"
    const val B2C = "B2C"
    const val MARKETPLACES = "Marketplaces"
    const val TIKTOK = "TikTok"
    const val DROPSHIPPING = "Dropshipping"

    const val WAITING_BAY = "Bay 2"
    val waitingLines = listOf(
        SampleWaitingLine("SKO-01187", WaitingReason.Warehouse, picked = 2, required = 4, detail = "“Location empty, check overstock”"),
        SampleWaitingLine("SKO-00953", WaitingReason.CustomerService, picked = 1, required = 2, detail = "customer to confirm a substitute"),
    )
    val waitingPicked = listOf(SampleLine("SKO-00733", "Bath bomb – rose 180g", "A-03-02", picked = 6, required = 6))
    const val CRM_MESSAGES = 2
    const val CRM_MESSAGES_SKO = "SKO-00953"

    const val NOT_FOUND_QUANTITY = 2
    const val NOT_FOUND_LOCATION = "A-07-01"
    const val WAITING_NOTE = "Location empty, check overstock"

    val cancelledPicks = listOf(
        SampleLine("SKO-00733", "Bath bomb – rose 180g", "A-03-02", picked = 6, required = 6),
        SampleLine("SKO-00412", "Lavender essential oil 10ml", "A-07-01", picked = 2, required = 2),
    )

    val shippers = listOf(
        SampleShipper("DPD (GB)", 48),
        SampleShipper("APC (GB)", 21),
        SampleShipper("Packeta (SK)", 0),
    )

    val pickingSessions = listOf(
        SamplePickingSession("PSaw-0142", SessionState.Picking, "Sam P.", SessionProgress.Picking(deliveryNotes = 12, trolleys = 2, percentPicked = 60)),
        SamplePickingSession("PSaw-0143", SessionState.InProcess, null, SessionProgress.NotStarted(deliveryNotes = 8)),
        SamplePickingSession("PSaw-0139", SessionState.Waiting, "Jo K.", SessionProgress.Waiting(waitingDeliveryNotes = 1)),
        SamplePickingSession("PSaw-0137", SessionState.PickingFinished, "Jo K.", SessionProgress.Packing(percentPacked = 40)),
    )

    const val SESSION_DELIVERY_NOTES = 8
    const val SESSION_ITEMS = 23
    const val SESSION_TROLLEYS = 2
    val sessionTrolleys = listOf(SampleTrolleyLoad("T-03", 4), SampleTrolleyLoad("T-05", 4))
    val sessionLocations = listOf(
        SampleLocationPick("A-01-01", "SKO-00412", quantity = 7, deliveryNotes = 3),
        SampleLocationPick("A-03-02", "SKO-00733", quantity = 9, deliveryNotes = 4),
        SampleLocationPick("B-01-04", "SKO-00953", quantity = 2, deliveryNotes = 1),
    )
    val sessionNotes = listOf(
        SampleSessionNote("T-03", "AR24830"),
        SampleSessionNote("T-03", "AR24831"),
        SampleSessionNote("T-05", "AR24834"),
    )

    const val SESSION_PICKED = 9
    val pickLocation = SampleLine("SKO-00733", "Bath bomb – rose 180g", "A-03-02", picked = 0, required = 9)
    val putInto = listOf(
        SamplePutInto("T-03", "AR24830", picked = 2, required = 2),
        SamplePutInto("T-03", "AR24831", picked = 1, required = 3),
        SamplePutInto("T-05", "AR24834", picked = 0, required = 4),
    )
    val nextLocation = SampleLocationPick("B-01-04", "SKO-00953", quantity = 2, deliveryNotes = 1)

    const val PACKING_PERCENT = 40
    const val PACK_BOX = 1
    val packNotes = listOf(
        SamplePackNote("AR24801", "T-02", packed = 3, items = 3),
        SamplePackNote("AR24802", "T-02", packed = 2, items = 5),
        SamplePackNote("AR24805", "T-04", packed = 0, items = 2),
    )

    val trolleys = listOf(
        SampleTrolley("T-01", TrolleyUse.DeliveryNote("AR24790")),
        SampleTrolley("T-02", TrolleyUse.Session("PSaw-0142")),
        SampleTrolley("T-03", null),
        SampleTrolley("T-05", null),
    )

    val bays = listOf(
        SampleBay("Bay 1", deliveryNotes = 0, waiting = 0),
        SampleBay("Bay 2", deliveryNotes = 2, waiting = 1),
        SampleBay("Bay 3", deliveryNotes = 1, waiting = 0),
    )

    const val TAKE_OVER_PICKER = "Sam P."
    const val TAKE_OVER_PICKED = 9
    const val TAKE_OVER_ITEMS = 14
    val takeOverLines = listOf(
        SampleLine("SKO-00412", "Lavender essential oil 10ml", "A-01-01", picked = 6, required = 6),
        SampleLine("SKO-00733", "Bath bomb – rose 180g", "A-03-02", picked = 3, required = 6),
    )

    val chatMessages = listOf(
        SampleChatMessage(isMine = true, author = "Jo K. (packer)", time = "11:05", text = "SKO-00953: 1 of 2 found, pack crushed. Substitute or send 1?"),
        SampleChatMessage(isMine = false, author = "[CS name] · CRM", time = "11:20", text = "Customer agreed: send 1, refund the other. I’ve updated the order."),
    )
    const val CHAT_TASK = "recount B-01-04"
    const val CHAT_TASK_ASSIGNEE = "Sam P."

    const val SET_NAME = "[Gift set – spa trio]"
    val setParts = listOf(
        SampleSetPart("SKO-00953", "Incense sticks – sandalwood", part = 1, parts = 3, state = SetPartState.NotFound, picked = 0, required = 1, returnLocation = null),
        SampleSetPart("SKO-00733", "Bath bomb – rose 180g", part = 2, parts = 3, state = SetPartState.PutBack, picked = 1, required = 1, returnLocation = "A-03-02"),
        SampleSetPart("SKO-00412", "Lavender essential oil 10ml", part = 3, parts = 3, state = SetPartState.PutBack, picked = 1, required = 1, returnLocation = "A-01-01"),
    )

    val externalParcels = listOf(SampleParcel(number = 1, weightKg = "0.9", length = "20", width = "15", height = "10"))

    const val ADDRESS_CONTACT = "[Contact name]"
    const val ADDRESS_COMPANY = "[Company]"
    const val ADDRESS_PHONE = "[Phone]"
    const val ADDRESS_EMAIL = "[Email]"
    const val ADDRESS_STREET = "[Street and number]"
    const val ADDRESS_TOWN = "[Town]"
    const val ADDRESS_POSTCODE = "[Postcode]"
    const val ADDRESS_COUNTRY = "United Kingdom"

    val tariffLines = listOf(
        SampleTariffLine("SKO-00412", "3301 29", "GB", "Lavender essential oil 10ml", isDangerous = true),
        SampleTariffLine("SKO-00733", "3307 30", "GB", "Bath bomb – rose 180g"),
        SampleTariffLine("SKO-01187", null, "CN", null),
    )
    val descriptionLanguages = listOf("EN", "ES", "SK")

    const val CUSTOMS_FORMAT = "[DHL CSV]"
    const val CUSTOMS_FIELD_DELIMITER = ","
    const val CUSTOMS_TEXT_DELIMITER = "\""
    val customsRows = listOf(
        SampleCustomsRow(1, "Lavender essential oil 10ml", "3301 29", "GB", 4, "[£]"),
        SampleCustomsRow(2, "Bath bomb – rose 180g", "3307 30", "GB", 6, "[£]"),
        SampleCustomsRow(3, "Soy wax candle – vanilla", null, "CN", 2, "[£]"),
    )

    val emails = listOf(
        SampleEmail(EmailKind.DispatchConfirmation, EmailState.Opened, "[customer email]", "10 Nov 14:22", orderReference = null, hasPreview = true),
        SampleEmail(EmailKind.TrackingUpdate, EmailState.Delivered, "[customer email]", "10 Nov 14:23", orderReference = null, hasPreview = true),
        SampleEmail(EmailKind.DispatchConfirmation, EmailState.SoftBounce, "[client email]", "09 Nov 16:02", orderReference = "AR24790", hasPreview = false),
    )
}
