package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

enum class MyWorkKind(val key: String) {
    DeliveryNote("delivery_note"),
    PickingSession("picking_session"),
    CustomerReturn("return"),
    PalletReturn("pallet_return"),
    ;

    companion object {
        fun ofKey(key: String?): MyWorkKind? = entries.firstOrNull { it.key == key }
    }
}

object MyWorkStep {
    const val TO_PICK = "to_pick"
    const val PICKING = "picking"
    const val WAITING = "waiting"
    const val TO_PACK = "to_pack"
    const val PACKING = "packing"
    const val TO_START = "to_start"
    const val RETURNING = "returning"
    const val TO_DISPATCH = "to_dispatch"
}

data class MyWorkRow(
    val kind: MyWorkKind,
    val id: Long,
    val reference: String = "",
    val state: String = "",
    val stateLabel: String = "",
    val step: String = "",
    val stepLabel: String = "",
    val typeGroup: String? = null,
    val items: Int? = null,
    val customer: String? = null,
    val trolleys: List<String> = emptyList(),
    val since: String? = null,
)

@Serializable
data class MyWorkCounts(
    @Serializable(with = LenientInt::class) val total: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("delivery_notes") val deliveryNotes: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("picking_sessions") val pickingSessions: Int = 0,
    @Serializable(with = LenientInt::class) val returns: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("pallet_returns") val palletReturns: Int = 0,
)

data class MyWorkPage(val rows: List<MyWorkRow>, val counts: MyWorkCounts? = null)

@Serializable
data class WorkBadges(
    @Serializable(with = LenientIntOrNull::class) @SerialName("my_work") val myWork: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("goods_out_unassigned") val goodsOutUnassigned: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("inbox_unread") val inboxUnread: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("goods_in_received") val goodsInReceived: Int? = null,
)

interface MyWorkApi {
    suspend fun badges(token: String, warehouseId: Long): GoodsOutResult<WorkBadges>

    suspend fun myWork(token: String, warehouseId: Long): GoodsOutResult<MyWorkPage>
}

class OkHttpMyWorkApi(private val http: OkHttpMobileApi) : MyWorkApi {

    private fun warehouseUrl(warehouseId: Long): String = "${http.mobileApiUrl}/warehouses/$warehouseId"

    override suspend fun badges(token: String, warehouseId: Long): GoodsOutResult<WorkBadges> =
        http.execute(http.get("${warehouseUrl(warehouseId)}/badges", token))
            ?.let { (statusCode, body) -> MyWorkResponses.parseBadges(statusCode, body) }
            ?: GoodsOutResult.Unreachable

    override suspend fun myWork(token: String, warehouseId: Long): GoodsOutResult<MyWorkPage> =
        http.execute(http.get("${warehouseUrl(warehouseId)}/my-work", token))
            ?.let { (statusCode, body) -> MyWorkResponses.parseMyWork(statusCode, body) }
            ?: GoodsOutResult.Unreachable
}
