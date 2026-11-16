package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

enum class PickingSessionState(val key: String) {
    InProcess("in_process"),
    Handling("handling"),
    HandlingBlocked("handling_blocked"),
    PickingFinished("picking_finished"),
    PackingFinished("packing_finished"),
    ;

    companion object {
        fun ofKey(key: String?): PickingSessionState? = entries.firstOrNull { it.key == key }
    }
}

@Serializable
data class PickingSessionRow(
    val id: Long,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val picker: StaffRef? = null,
    @SerialName("number_delivery_notes") val numberDeliveryNotes: Int = 0,
    @SerialName("number_items") val numberItems: Int = 0,
    @SerialName("number_trolleys") val numberTrolleys: Int = 0,
    @SerialName("picking_percentage") val pickingPercentage: Double = 0.0,
    @SerialName("packing_percentage") val packingPercentage: Double = 0.0,
    @SerialName("is_waiting_ready") val isWaitingReady: Boolean = false,
    @SerialName("start_at") val startAt: String? = null,
    @SerialName("end_at") val endAt: String? = null,
) {
    val sessionState: PickingSessionState? get() = PickingSessionState.ofKey(state)
}

data class PickingSessionPage(
    val sessions: List<PickingSessionRow>,
    val counts: Map<String, Int>,
    val pagination: GoodsOutPagination,
)

@Serializable
data class PickingSessionSettings(
    @SerialName("allow_scan_to_pick") val allowScanToPick: Boolean = false,
    @SerialName("allow_scan_to_pack") val allowScanToPack: Boolean = false,
    @SerialName("allow_waiting") val allowWaiting: Boolean = false,
    @SerialName("allow_picker_set_not_picked") val allowPickerSetNotPicked: Boolean = false,
)

@Serializable
data class SessionDeliveryNote(
    val id: Long,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    @SerialName("number_items") val numberItems: Int = 0,
    @SerialName("picking_percentage") val pickingPercentage: Double = 0.0,
    @SerialName("packing_percentage") val packingPercentage: Double = 0.0,
    @SerialName("has_waiting_items") val hasWaitingItems: Boolean = false,
    @SerialName("is_for_collection") val isForCollection: Boolean = false,
)

@Serializable
data class PickListLocation(val id: Long, val code: String = "")

@Serializable
data class PickListLine(
    @SerialName("delivery_note_item_id") val deliveryNoteItemId: Long,
    @SerialName("delivery_note_id") val deliveryNoteId: Long,
    @SerialName("delivery_note_reference") val deliveryNoteReference: String = "",
    @SerialName("quantity_required") val quantityRequired: Double = 0.0,
    @SerialName("quantity_picked") val quantityPicked: Double = 0.0,
    @SerialName("quantity_to_pick") val quantityToPick: Double = 0.0,
)

@Serializable
data class PickListEntry(
    val key: String,
    val location: PickListLocation? = null,
    @SerialName("org_stock") val orgStock: LineOrgStock? = null,
    @SerialName("quantity_required") val quantityRequired: Double = 0.0,
    @SerialName("quantity_picked") val quantityPicked: Double = 0.0,
    @SerialName("quantity_to_pick") val quantityToPick: Double = 0.0,
    @SerialName("quantity_to_pick_label") val quantityToPickLabel: String? = null,
    val lines: List<PickListLine> = emptyList(),
) {
    val isDone: Boolean get() = quantityToPick <= 0.0
}

object SessionActionKey {
    const val START_PICKING = "start_picking"
    const val BACK_TO_PICKING = "back_to_picking"
    const val ADD_DELIVERY_NOTES = "add_delivery_notes"
    const val REMOVE_DELIVERY_NOTES = "remove_delivery_notes"
    const val SCAN_PICK = "scan_pick"
    const val SCAN_PACK = "scan_pack"
}

@Serializable
data class PickingSession(
    val id: Long,
    val reference: String = "",
    val state: String = "",
    @SerialName("state_label") val stateLabel: String = "",
    val picker: StaffRef? = null,
    @SerialName("number_delivery_notes") val numberDeliveryNotes: Int = 0,
    @SerialName("number_items") val numberItems: Int = 0,
    @SerialName("number_trolleys") val numberTrolleys: Int = 0,
    @SerialName("picking_percentage") val pickingPercentage: Double = 0.0,
    @SerialName("packing_percentage") val packingPercentage: Double = 0.0,
    @SerialName("is_waiting_ready") val isWaitingReady: Boolean = false,
    @SerialName("start_at") val startAt: String? = null,
    @SerialName("end_at") val endAt: String? = null,
    @SerialName("can_work") val canWork: Boolean = false,
    val trolleys: List<TrolleyRef> = emptyList(),
    @SerialName("can_change_trolleys") val canChangeTrolleys: Boolean = false,
    val settings: PickingSessionSettings = PickingSessionSettings(),
    @SerialName("available_actions") val availableActions: List<DeliveryNoteAction> = emptyList(),
    @SerialName("delivery_notes") val deliveryNotes: List<SessionDeliveryNote> = emptyList(),
    @SerialName("pick_list") val pickList: List<PickListEntry> = emptyList(),
) {
    val sessionState: PickingSessionState? get() = PickingSessionState.ofKey(state)

    fun action(key: String): DeliveryNoteAction? = availableActions.firstOrNull { it.key == key }

    fun pick(key: String?): PickListEntry? = pickList.firstOrNull { it.key == key }

    fun pickLine(deliveryNoteItemId: Long): PickListLine? = pickList.firstNotNullOfOrNull { entry -> entry.lines.firstOrNull { it.deliveryNoteItemId == deliveryNoteItemId } }
}

sealed interface PickingSessionWrite {
    data object StartPicking : PickingSessionWrite
    data object BackToPicking : PickingSessionWrite
    data class AttachTrolley(val trolleyId: Long) : PickingSessionWrite
    data class DetachTrolley(val trolleyId: Long) : PickingSessionWrite
    data class AddDeliveryNotes(val deliveryNoteIds: List<Long>) : PickingSessionWrite
    data class RemoveDeliveryNotes(val deliveryNoteIds: List<Long>) : PickingSessionWrite
}

data class SessionActionOutcome(val session: PickingSession, val status: String, val message: String?)

data class SessionScanPickOutcome(
    val session: PickingSession,
    val status: String,
    val message: String?,
    val item: ScanPickItem?,
    val deliveryNote: DeliveryNoteRef?,
)

data class SessionScanPackOutcome(
    val session: PickingSession,
    val status: String,
    val message: String?,
    val item: ScanPackItem?,
    val deliveryNote: DeliveryNoteRef?,
)

object ScanStatus {
    const val NOT_SCANNABLE = "not_scannable"
}

enum class DispatchRole(val key: String) {
    Pickers("pickers"),
    Packers("packers"),
}

@Serializable
data class DispatchPerson(
    val user: StaffRef,
    @SerialName("delivery_notes") val deliveryNotes: List<NoteReference> = emptyList(),
    val trolleys: List<TrolleyRef> = emptyList(),
    @SerialName("picking_sessions") val pickingSessions: List<NoteReference> = emptyList(),
)

interface PickingSessionApi {
    suspend fun pickingSessions(token: String, warehouseId: Long, state: PickingSessionState?, page: Int): GoodsOutResult<PickingSessionPage>

    suspend fun pickingSession(token: String, sessionId: Long): GoodsOutResult<PickingSession>

    suspend fun createPickingSession(token: String, warehouseId: Long, deliveryNoteIds: List<Long>): GoodsOutResult<PickingSession>

    suspend fun writeSession(token: String, sessionId: Long, write: PickingSessionWrite): GoodsOutResult<SessionActionOutcome>

    suspend fun sessionScanPick(token: String, sessionId: Long, request: ScanPickRequest): GoodsOutResult<SessionScanPickOutcome>

    suspend fun sessionScanPack(token: String, sessionId: Long, request: ScanPackRequest): GoodsOutResult<SessionScanPackOutcome>

    suspend fun dispatchPersonnel(token: String, warehouseId: Long, role: DispatchRole): GoodsOutResult<List<DispatchPerson>>
}
