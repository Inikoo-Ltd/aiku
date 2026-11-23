package io.aiku.droid.ui.sync

import io.aiku.droid.data.api.OfflineOperationEntry

data class ConflictDifference(
    val title: String?,
    val subtitle: String?,
    val yours: Double?,
    val aiku: Double?,
) {
    val difference: Double? get() = if (yours != null && aiku != null) aiku - yours else null
}

fun conflictDifferences(operation: OfflineOperationEntry): List<ConflictDifference> {
    val rows = operation.countedRows
    if (rows.isEmpty()) {
        return listOf(
            ConflictDifference(
                title = null,
                subtitle = operation.labels.locationCode?.takeIf { it.isNotBlank() },
                yours = operation.requestedQuantity,
                aiku = operation.appliedQuantity ?: operation.requestedQuantity?.let { 0.0 },
            ),
        )
    }
    val oneSko = rows.mapNotNull { it.skoCode }.distinct().size <= 1
    return rows.map { row ->
        ConflictDifference(
            title = if (oneSko) row.locationCode ?: row.skoCode else row.skoCode ?: row.locationCode,
            subtitle = if (oneSko) null else row.locationCode,
            yours = row.counted,
            aiku = row.quantity,
        )
    }
}
