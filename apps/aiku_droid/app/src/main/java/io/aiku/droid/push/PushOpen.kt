package io.aiku.droid.push

import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.WorkRecord
import io.aiku.droid.ui.inbox.RecordOpening
import io.aiku.droid.ui.inbox.openingIn

data class PushOpen(val notificationId: String, val record: WorkRecord?)

sealed interface PushDestination {
    data class Record(val opening: RecordOpening) : PushDestination
    data object Inbox : PushDestination
}

fun PushOpen.destination(warehouseIds: Collection<Long>): PushDestination =
    record?.openingIn(warehouseIds)?.let { PushDestination.Record(it) } ?: PushDestination.Inbox

object PushData {
    const val NOTIFICATION_ID = "notification_id"
    const val KIND = "kind"
    const val EVENT = "event"
    const val RECORD_TYPE = "record_type"
    const val RECORD_ID = "record_id"
    const val REFERENCE = "reference"
    const val WAREHOUSE_ID = "warehouse_id"
    const val STATE = "state"
    const val TYPE_GROUP = "type_group"

    val keys = listOf(NOTIFICATION_ID, KIND, EVENT, RECORD_TYPE, RECORD_ID, REFERENCE, WAREHOUSE_ID, STATE, TYPE_GROUP)

    fun openOf(data: Map<String, String?>): PushOpen? {
        val notificationId = data[NOTIFICATION_ID].trimmedOrNull() ?: return null
        return PushOpen(notificationId = notificationId, record = recordOf(data))
    }

    fun tagOf(data: Map<String, String?>): String? {
        val kind = data[KIND].trimmedOrNull() ?: data[RECORD_TYPE].trimmedOrNull()
        val recordId = data[RECORD_ID].trimmedOrNull()
        return if (kind != null && recordId != null) "$kind:$recordId" else data[NOTIFICATION_ID].trimmedOrNull()
    }

    private fun recordOf(data: Map<String, String?>): WorkRecord? {
        val kind = MyWorkKind.ofKey(data[KIND].trimmedOrNull() ?: data[RECORD_TYPE].trimmedOrNull()) ?: return null
        val id = data[RECORD_ID].trimmedOrNull()?.toLongOrNull() ?: return null
        return WorkRecord(
            kind = kind,
            id = id,
            reference = data[REFERENCE].trimmedOrNull().orEmpty(),
            state = data[STATE].trimmedOrNull().orEmpty(),
            warehouseId = data[WAREHOUSE_ID].trimmedOrNull()?.toLongOrNull(),
            typeGroup = data[TYPE_GROUP].trimmedOrNull(),
        )
    }

    private fun String?.trimmedOrNull(): String? = this?.trim()?.takeIf { it.isNotEmpty() && it != "null" }
}
