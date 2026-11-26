package io.aiku.droid.ui.sync

import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.data.api.OfflineOperationRecord
import io.aiku.droid.data.api.OfflineRecordKind
import io.aiku.droid.data.api.OfflineResolution
import io.aiku.droid.data.offline.OfflineRecordKey
import io.aiku.droid.data.offline.OfflineRecordType

private const val SKO_ITEM_TYPE = "sko"

data class ConflictGroup(val record: OfflineOperationRecord?, val lines: List<OfflineOperationEntry>) {
    val recordKey: OfflineRecordKey? get() = record?.let(::recordKeyOf)

    val reference: String?
        get() {
            val type = record?.type ?: return null
            return lines.firstNotNullOfOrNull { line -> referenceOf(type, line) }
        }

    val bulkResolutions: List<OfflineResolution>
        get() = if (lines.size < 2) {
            emptyList()
        } else {
            lines.map { it.allowedResolutions.toSet() }.reduce { shared, allowed -> shared intersect allowed }.toList()
        }
}

fun groupConflicts(operations: List<OfflineOperationEntry>): List<ConflictGroup> {
    val groups = linkedMapOf<Any, MutableList<OfflineOperationEntry>>()
    operations.forEachIndexed { index, operation ->
        val key: Any = operation.record?.takeIf { recordKeyOf(it) != null } ?: index
        groups.getOrPut(key) { mutableListOf() } += operation
    }
    return groups.map { (key, lines) -> ConflictGroup(record = key as? OfflineOperationRecord, lines = lines) }
}

fun recordKeyOf(record: OfflineOperationRecord): OfflineRecordKey? {
    val type = when (record.type) {
        OfflineRecordKind.DELIVERY_NOTE -> OfflineRecordType.DELIVERY_NOTE
        OfflineRecordKind.RETURN -> OfflineRecordType.RETURN
        OfflineRecordKind.STOCK_DELIVERY -> OfflineRecordType.STOCK_DELIVERY
        OfflineRecordKind.LOCATION -> OfflineRecordType.LOCATION
        OfflineRecordKind.ORG_STOCK -> OfflineRecordType.ORG_STOCK
        else -> return null
    }
    return OfflineRecordKey(type = type, id = record.id)
}

private fun referenceOf(type: String, line: OfflineOperationEntry): String? {
    val item = line.item
    val itemType = item?.recordType?.let { if (it == SKO_ITEM_TYPE) OfflineRecordKind.ORG_STOCK else it }
    val fromItem = item?.recordReference?.takeIf { itemType == type && it.isNotBlank() }
    return fromItem ?: when (type) {
        OfflineRecordKind.LOCATION -> line.labels.locationCode?.takeIf { it.isNotBlank() }
        OfflineRecordKind.ORG_STOCK -> item?.skoCode?.takeIf { it.isNotBlank() }
        else -> null
    }
}
