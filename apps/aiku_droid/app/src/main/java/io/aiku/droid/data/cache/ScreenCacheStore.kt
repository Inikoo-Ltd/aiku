package io.aiku.droid.data.cache

data class ScreenCacheKey(val userId: Long, val warehouseId: Long, val screen: String)

class StoredScreen(
    val key: ScreenCacheKey,
    val payload: ByteArray,
    val fetchedAt: Long,
    val lastOpenedAt: Long,
)

interface ScreenCacheStore {
    fun read(key: ScreenCacheKey): StoredScreen?

    fun write(screen: StoredScreen)

    fun markOpened(key: ScreenCacheKey, openedAt: Long)

    fun keys(userId: Long): List<ScreenCacheKey>

    fun delete(key: ScreenCacheKey)

    fun deleteOpenedBefore(openedAt: Long)

    fun deleteOtherUsers(userId: Long)

    fun deleteWarehousesOutside(userId: Long, warehouseIds: Set<Long>)

    fun deleteAll()
}
