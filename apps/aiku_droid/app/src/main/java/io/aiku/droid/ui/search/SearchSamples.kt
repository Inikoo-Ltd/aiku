package io.aiku.droid.ui.search

import androidx.annotation.StringRes
import io.aiku.droid.R
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.SkuFamilyRoute
import io.aiku.droid.ui.navigation.SkuRoute

internal data class SearchResult(val title: String, val subtitle: String, val trailing: String?, val hasPhoto: Boolean, val route: Any)

internal data class SearchGroup(@StringRes val label: Int, val scope: SearchScope, val results: List<SearchResult>)

internal object SearchSamples {
    const val INITIAL_QUERY = "lav"

    val recent = listOf("AR24817", "A-12-03", "bath bomb")

    val groups: List<SearchGroup>
        get() = listOf(
            SearchGroup(
                R.string.section_skos,
                SearchScope.Skus,
                listOf(
                    SearchResult("SKO-00412", "Lavender essential oil 10ml", "1,240", true, SkuRoute("SKO-00412")),
                    SearchResult("SKO-00734", "Bath bomb – lavender 180g", "210", true, SkuRoute("SKO-00734")),
                    SearchResult("SKO-01190", "Soy candle – lavender", "96", true, SkuRoute("SKO-01190")),
                    SearchResult("SKO-00733", "Bath bomb – rose 180g", "36", true, SkuRoute("SKO-00733")),
                ),
            ),
            SearchGroup(
                R.string.section_sko_families,
                SearchScope.Skus,
                listOf(SearchResult("Lavender range", "LV · 14 SKOs", null, false, SkuFamilyRoute("LV"))),
            ),
            SearchGroup(
                R.string.section_locations,
                SearchScope.Locations,
                listOf(SearchResult("A-03-02", "Area A · 6 SKOs", null, false, LocationRoute("A-03-02"))),
            ),
            SearchGroup(
                R.string.section_delivery_notes,
                SearchScope.DeliveryNotes,
                listOf(SearchResult("AR24817", "Wholesale · 6 items · Picking", null, false, DeliveryNotePickingRoute("AR24817"))),
            ),
        )
}

internal enum class SearchScope { All, Skus, Locations, DeliveryNotes }
