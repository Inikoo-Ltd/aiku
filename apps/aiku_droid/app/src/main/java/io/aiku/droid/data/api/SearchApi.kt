package io.aiku.droid.data.api

import android.util.Log
import kotlinx.coroutines.suspendCancellableCoroutine
import okhttp3.Call
import okhttp3.Callback
import okhttp3.OkHttpClient
import okhttp3.Response
import java.io.IOException
import kotlin.coroutines.resume

enum class SearchType(val key: String, val rowType: String) {
    Skos("skos", "sko"),
    SkoFamilies("sko_families", "sko_family"),
    Locations("locations", "location"),
    DeliveryNotes("delivery_notes", "delivery_note"),
    StockDeliveries("stock_deliveries", "stock_delivery"),
    Returns("returns", "return"),
    Pallets("pallets", "pallet"),
    PalletDeliveries("pallet_deliveries", "pallet_delivery"),
    PalletReturns("pallet_returns", "pallet_return"),
    ;

    companion object {
        const val ALL = "all"

        fun ofKey(key: String?): SearchType? = entries.firstOrNull { it.key == key }

        fun ofRowType(rowType: String?): SearchType? = entries.firstOrNull { it.rowType == rowType }
    }
}

data class SearchRow(
    val type: SearchType,
    val id: Long,
    val code: String = "",
    val title: String = "",
    val subtitle: String = "",
    val state: String = "",
    val stateLabel: String = "",
    val quantity: Double? = null,
    val imageUrl: String? = null,
    val typeGroup: String? = null,
) {
    fun isSameRecord(other: SearchRow): Boolean = type == other.type && id == other.id
}

data class SearchGroup(
    val type: SearchType,
    val label: String = "",
    val hasMore: Boolean = false,
    val rows: List<SearchRow> = emptyList(),
)

data class SearchResults(
    val query: String = "",
    val types: List<SearchType> = emptyList(),
    val exact: SearchRow? = null,
    val groups: List<SearchGroup> = emptyList(),
)

interface SearchApi {
    suspend fun search(token: String, warehouseId: Long, query: String, type: SearchType?, page: Int): GoodsOutResult<SearchResults>
}

class OkHttpSearchApi(private val http: OkHttpMobileApi, private val client: OkHttpClient) : SearchApi {

    override suspend fun search(token: String, warehouseId: Long, query: String, type: SearchType?, page: Int): GoodsOutResult<SearchResults> {
        val parameters = buildList {
            add("q" to query)
            add("type" to (type?.key ?: SearchType.ALL))
            if (type != null) {
                add("page" to page.toString())
            }
        }
        val request = http.get(http.urlWith("${http.mobileApiUrl}/warehouses/$warehouseId/search", parameters), token)

        return client.newCall(request).awaitBody()
            ?.let { (statusCode, body) -> SearchResponses.parseSearch(statusCode, body) }
            ?: GoodsOutResult.Unreachable
    }

    private suspend fun Call.awaitBody(): Pair<Int, String>? = suspendCancellableCoroutine { continuation ->
        continuation.invokeOnCancellation { cancel() }
        enqueue(
            object : Callback {
                override fun onFailure(call: Call, e: IOException) {
                    if (!call.isCanceled()) {
                        Log.w(LOG_TAG, "${call.request().method} ${call.request().url} failed", e)
                    }
                    continuation.resume(null)
                }

                override fun onResponse(call: Call, response: Response) {
                    val received = try {
                        response.use { it.code to it.body.string() }
                    } catch (exception: IOException) {
                        Log.w(LOG_TAG, "${call.request().method} ${call.request().url} failed", exception)
                        null
                    }
                    continuation.resume(received)
                }
            },
        )
    }

    private companion object {
        const val LOG_TAG = "AikuApi"
    }
}
