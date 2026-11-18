package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.decodeFromJsonElement

private const val HTTP_OK = 200

@Serializable
private data class WarehouseDashboardResponse(val data: WarehouseDashboardWire)

@Serializable
private data class WarehouseDashboardWire(
    @SerialName("generated_at") val generatedAt: String? = null,
    @SerialName("goods_out") val goodsOut: JsonElement? = null,
    @SerialName("goods_in") val goodsIn: JsonElement? = null,
    val inventory: JsonElement? = null,
)

object WarehouseDashboardResponses {
    private val json = ClockingResponses.json

    fun parseDashboard(statusCode: Int, body: String): GoodsOutResult<WarehouseDashboard> = when (statusCode) {
        HTTP_OK -> decode<WarehouseDashboardResponse>(body)
            ?.let { GoodsOutResult.Loaded(dashboardOf(it.data)) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }

    private fun dashboardOf(wire: WarehouseDashboardWire): WarehouseDashboard = WarehouseDashboard(
        generatedAt = wire.generatedAt?.takeIf { it.isNotBlank() },
        goodsOut = blockOf<DashboardGoodsOut>(wire.goodsOut),
        goodsIn = blockOf<DashboardGoodsIn>(wire.goodsIn),
        inventory = blockOf<DashboardInventory>(wire.inventory),
    )

    private inline fun <reified T> blockOf(element: JsonElement?): T? =
        (element as? JsonObject)?.let { block -> runCatching { json.decodeFromJsonElement<T>(block) }.getOrNull() }

    private inline fun <reified T> decode(body: String): T? = runCatching { json.decodeFromString<T>(body) }.getOrNull()
}
