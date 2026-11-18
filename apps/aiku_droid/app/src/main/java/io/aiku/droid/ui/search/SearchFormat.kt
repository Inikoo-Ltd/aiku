package io.aiku.droid.ui.search

import androidx.annotation.PluralsRes
import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.SearchRow
import io.aiku.droid.data.api.SearchType
import io.aiku.droid.ui.components.LabelCase
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.fulfilment.inbound.palletDeliveryTone
import io.aiku.droid.ui.fulfilment.outbound.palletStateTone
import io.aiku.droid.ui.goodsin.deliveryStateTone
import io.aiku.droid.ui.goodsout.pluralQuantity
import io.aiku.droid.ui.goodsout.stageOf
import io.aiku.droid.ui.goodsout.stageTone
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.scan.ScanDestination
import io.aiku.droid.ui.fulfilment.outbound.returnStateTone as palletReturnStateTone
import io.aiku.droid.ui.goodsin.returnStateTone as customerReturnStateTone

fun SearchRow.destination(): ScanDestination {
    val reference = code.ifBlank { title }
    return when (type) {
        SearchType.Skos -> ScanDestination.Sko(id = id, code = reference)
        SearchType.SkoFamilies -> ScanDestination.SkoFamily(id = id, code = reference)
        SearchType.Locations -> ScanDestination.Location(id = id, code = reference)
        SearchType.DeliveryNotes -> ScanDestination.DeliveryNote(DeliveryNoteRow(id = id, reference = reference, state = state, stateLabel = stateLabel))
        SearchType.StockDeliveries -> ScanDestination.StockDelivery(id = id, reference = reference)
        SearchType.Returns -> ScanDestination.CustomerReturn(id = id, reference = reference)
        SearchType.Pallets -> ScanDestination.Pallet(id = id, reference = reference)
        SearchType.PalletDeliveries -> ScanDestination.PalletDelivery(id = id, reference = reference, state = state)
        SearchType.PalletReturns -> ScanDestination.PalletReturn(id = id, reference = reference, state = state, typeGroup = typeGroup)
    }
}

val SearchLabelCase = LabelCase.AsWritten

data class SearchQuantity(@PluralsRes val plural: Int, val count: Int, val text: String)

fun SearchRow.quantityLabel(): SearchQuantity? {
    val value = quantity?.takeIf { type == SearchType.Skos } ?: return null
    return SearchQuantity(plural = R.plurals.search_sko_in_locations, count = pluralQuantity(value), text = stockText(value))
}

@StringRes
internal fun searchTypeLabel(type: SearchType): Int = when (type) {
    SearchType.Skos -> R.string.section_skos
    SearchType.SkoFamilies -> R.string.section_sko_families
    SearchType.Locations -> R.string.section_locations
    SearchType.DeliveryNotes -> R.string.section_delivery_notes
    SearchType.StockDeliveries -> R.string.section_stock_deliveries
    SearchType.Returns -> R.string.section_returns
    SearchType.Pallets -> R.string.search_type_pallets
    SearchType.PalletDeliveries -> R.string.section_fulfilment_deliveries
    SearchType.PalletReturns -> R.string.section_fulfilment_returns
}

internal val SearchType.hasStateBadge: Boolean
    get() = when (this) {
        SearchType.Skos, SearchType.SkoFamilies, SearchType.Locations -> false
        else -> true
    }

internal val SearchType.hasPhoto: Boolean get() = this == SearchType.Skos

internal fun searchStateTone(row: SearchRow): Tone = when (row.type) {
    SearchType.DeliveryNotes -> stageTone(stageOf(row.state))
    SearchType.StockDeliveries -> deliveryStateTone(row.state)
    SearchType.Returns -> customerReturnStateTone(row.state)
    SearchType.Pallets -> palletStateTone(row.state)
    SearchType.PalletDeliveries -> palletDeliveryTone(row.state)
    SearchType.PalletReturns -> palletReturnStateTone(row.state)
    SearchType.Skos, SearchType.SkoFamilies, SearchType.Locations -> Tone.Neutral
}
