package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class DispatchedToday(
    @Serializable(with = LenientInt::class) val count: Int = 0,
    @Serializable(with = LenientIntOrNull::class) @SerialName("yesterday_by_now") val yesterdayByNow: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("same_day_last_week") val sameDayLastWeek: Int? = null,
)

@Serializable
data class DashboardGoodsOut(
    @Serializable(with = LenientInt::class) val unassigned: Int = 0,
    @Serializable(with = LenientInt::class) val handling: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("handling_blocked") val handlingBlocked: Int = 0,
    @Serializable(with = LenientIntOrNull::class) val picked: Int? = null,
    @Serializable(with = LenientInt::class) val packed: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("in_warehouse") val inWarehouse: Int = 0,
    @SerialName("dispatched_today") val dispatchedToday: DispatchedToday? = null,
)

@Serializable
data class DashboardGoodsIn(
    @Serializable(with = LenientInt::class) val arriving: Int = 0,
    @Serializable(with = LenientInt::class) val received: Int = 0,
    @Serializable(with = LenientInt::class) val checked: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("booking_in") val bookingIn: Int = 0,
    @Serializable(with = LenientIntOrNull::class) @SerialName("returns_to_process") val returnsToProcess: Int? = null,
)

@Serializable
data class DashboardInventory(
    @Serializable(with = LenientInt::class) @SerialName("number_current_org_stocks") val numberCurrentOrgStocks: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("below_minimum_stock") val belowMinimumStock: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("negative_stocks") val negativeStocks: Int = 0,
    @Serializable(with = LenientInt::class) @SerialName("number_abnormalities") val numberAbnormalities: Int = 0,
)

data class WarehouseDashboard(
    val generatedAt: String? = null,
    val goodsOut: DashboardGoodsOut? = null,
    val goodsIn: DashboardGoodsIn? = null,
    val inventory: DashboardInventory? = null,
)

interface WarehouseDashboardApi {
    suspend fun dashboard(token: String, warehouseId: Long): GoodsOutResult<WarehouseDashboard>
}

class OkHttpWarehouseDashboardApi(private val http: OkHttpMobileApi) : WarehouseDashboardApi {

    override suspend fun dashboard(token: String, warehouseId: Long): GoodsOutResult<WarehouseDashboard> =
        http.execute(http.get("${http.mobileApiUrl}/warehouses/$warehouseId/dashboard", token))
            ?.let { (statusCode, body) -> WarehouseDashboardResponses.parseDashboard(statusCode, body) }
            ?: GoodsOutResult.Unreachable
}
