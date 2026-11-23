package io.aiku.droid.data.cache

import io.aiku.droid.data.api.ClockingResponses
import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.PalletDeliveryRow
import io.aiku.droid.data.api.PalletReturnRow
import io.aiku.droid.data.api.PickingSessionRow
import io.aiku.droid.data.api.StockDeliveryRow
import kotlinx.serialization.KSerializer
import kotlinx.serialization.builtins.ListSerializer

class SavedList<T>(val key: String, serializer: KSerializer<T>, val id: (T) -> Long) {
    private val rowsSerializer = ListSerializer(serializer)

    internal val screen: CachedScreen<List<T>> = CachedScreen(key) { body -> runCatching { ClockingResponses.json.decodeFromString(rowsSerializer, body) }.getOrNull() }

    internal fun encode(rows: List<T>): String = ClockingResponses.json.encodeToString(rowsSerializer, rows)

    internal fun merged(seen: List<T>, earlier: List<T>): List<T> = (seen + earlier).distinctBy(id).take(SavedLists.MAX_ROWS)
}

object SavedLists {
    const val MAX_ROWS = 500
    private const val PREFIX = "saved_rows:"

    val deliveryNotes: SavedList<DeliveryNoteRow> = SavedList(PREFIX + "delivery_notes", DeliveryNoteRow.serializer()) { it.id }

    val pickingSessions: SavedList<PickingSessionRow> = SavedList(PREFIX + "picking_sessions", PickingSessionRow.serializer()) { it.id }

    val palletReturns: SavedList<PalletReturnRow> = SavedList(PREFIX + "pallet_returns", PalletReturnRow.serializer()) { it.id }

    val stockDeliveries: SavedList<StockDeliveryRow> = SavedList(PREFIX + "stock_deliveries", StockDeliveryRow.serializer()) { it.id }

    val palletDeliveries: SavedList<PalletDeliveryRow> = SavedList(PREFIX + "pallet_deliveries", PalletDeliveryRow.serializer()) { it.id }

    val returns: SavedList<CustomerReturnRow> = SavedList(PREFIX + "returns", CustomerReturnRow.serializer()) { it.id }
}
