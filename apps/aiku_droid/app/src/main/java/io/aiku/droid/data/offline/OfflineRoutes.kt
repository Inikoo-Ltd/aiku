package io.aiku.droid.data.offline

import okhttp3.HttpUrl

object OfflineRoutes {
    private val MOBILE_PREFIX = listOf("app", "api", "mobile")
    private const val WAREHOUSES = "warehouses"
    private const val DELIVERY_NOTES = "delivery-notes"
    private const val LOCATIONS = "locations"
    private const val RETURNS = "returns"
    private const val STOCK_DELIVERIES = "stock-deliveries"
    private const val ORG_STOCKS = "org-stocks"

    fun mobileRouteOf(url: HttpUrl): List<String>? {
        val segments = url.pathSegments.filter { it.isNotEmpty() }
        val start = segments.windowed(MOBILE_PREFIX.size).indexOf(MOBILE_PREFIX)
        return if (start < 0) null else segments.drop(start + MOBILE_PREFIX.size)
    }

    fun detailRecordOf(route: List<String>): OfflineRecordKey? = recordPrefixOf(route)?.takeIf { (_, length) -> length == route.size }?.first

    fun recordUnder(route: List<String>): OfflineRecordKey? = recordPrefixOf(route)?.first

    fun detailPath(record: OfflineRecordKey, warehouseId: Long): String = when (record.type) {
        OfflineRecordType.DELIVERY_NOTE -> "$DELIVERY_NOTES/${record.id}"
        OfflineRecordType.LOCATION -> "$LOCATIONS/${record.id}"
        OfflineRecordType.RETURN -> "$WAREHOUSES/$warehouseId/$RETURNS/${record.id}"
        OfflineRecordType.STOCK_DELIVERY -> "$WAREHOUSES/$warehouseId/$STOCK_DELIVERIES/${record.id}"
        OfflineRecordType.ORG_STOCK -> "$WAREHOUSES/$warehouseId/$ORG_STOCKS/${record.id}"
    }

    fun warehouseListOf(route: List<String>, list: String): Long? =
        route.takeIf { it.size == 3 && it[0] == WAREHOUSES && it[2] == list }?.get(1)?.toLongOrNull()

    private fun recordPrefixOf(route: List<String>): Pair<OfflineRecordKey, Int>? {
        val first = route.getOrNull(0) ?: return null
        return when {
            first == DELIVERY_NOTES -> route.getOrNull(1)?.toLongOrNull()?.let { OfflineRecordKey(OfflineRecordType.DELIVERY_NOTE, it) to 2 }
            first == LOCATIONS -> route.getOrNull(1)?.toLongOrNull()?.let { OfflineRecordKey(OfflineRecordType.LOCATION, it) to 2 }
            first == WAREHOUSES && route.getOrNull(1)?.toLongOrNull() != null -> {
                val type = when (route.getOrNull(2)) {
                    RETURNS -> OfflineRecordType.RETURN
                    STOCK_DELIVERIES -> OfflineRecordType.STOCK_DELIVERY
                    ORG_STOCKS -> OfflineRecordType.ORG_STOCK
                    else -> null
                }
                val id = route.getOrNull(3)?.toLongOrNull()
                if (type != null && id != null) OfflineRecordKey(type, id) to 4 else null
            }
            else -> null
        }
    }
}
