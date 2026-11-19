package io.aiku.droid.data.cache

import io.aiku.droid.data.session.ByteCipher
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.Dispatchers
import java.time.Instant

val CACHE_NOW: Instant = Instant.parse("2026-11-19T10:00:00Z")

const val SIGNED_IN_USER = 42L

class InMemoryScreenCacheStore : ScreenCacheStore {
    val rows = linkedMapOf<ScreenCacheKey, StoredScreen>()

    override fun read(key: ScreenCacheKey): StoredScreen? = rows[key]

    override fun write(screen: StoredScreen) {
        rows[screen.key] = screen
    }

    override fun markOpened(key: ScreenCacheKey, openedAt: Long) {
        rows[key]?.let { rows[key] = StoredScreen(it.key, it.payload, it.fetchedAt, openedAt) }
    }

    override fun keys(userId: Long): List<ScreenCacheKey> = rows.keys.filter { it.userId == userId }

    override fun delete(key: ScreenCacheKey) {
        rows.remove(key)
    }

    override fun deleteOpenedBefore(openedAt: Long) {
        rows.values.removeAll { it.lastOpenedAt < openedAt }
    }

    override fun deleteOtherUsers(userId: Long) {
        rows.keys.removeAll { it.userId != userId }
    }

    override fun deleteWarehousesOutside(userId: Long, warehouseIds: Set<Long>) {
        rows.keys.removeAll { it.userId == userId && it.warehouseId != CachedScreens.USER_SCOPED && it.warehouseId !in warehouseIds }
    }

    override fun deleteAll() {
        rows.clear()
    }
}

class XorCipher : ByteCipher {
    var failsToDecrypt = false

    override fun encrypt(plainBytes: ByteArray): ByteArray = plainBytes.map { (it.toInt() xor KEY).toByte() }.toByteArray()

    override fun decrypt(encryptedBytes: ByteArray): ByteArray {
        check(!failsToDecrypt) { "key is gone" }
        return encrypt(encryptedBytes)
    }

    private companion object {
        const val KEY = 0x5A
    }
}

class TestClock(var now: Instant = CACHE_NOW) : () -> Instant {
    override fun invoke(): Instant = now
}

fun testScreenCache(
    sessions: SessionRepository = FakeSessions(),
    store: ScreenCacheStore = InMemoryScreenCacheStore(),
    cipher: ByteCipher = XorCipher(),
    clock: () -> Instant = TestClock(),
): ScreenCache = ScreenCache(store = store, cipher = cipher, sessions = sessions, clock = clock, ioDispatcher = Dispatchers.Unconfined)

fun ScreenCache.save(warehouseId: Long, screen: CachedScreen<*>, body: String, userId: Long = SIGNED_IN_USER) {
    replace(ScreenCacheKey(userId = userId, warehouseId = warehouseId, screen = screen.key), body)
}

object CachedBodies {
    fun myWork(vararg references: Pair<Long, String>): String =
        """{"data":[${references.joinToString(",") { (id, reference) -> """{"kind":"delivery_note","id":$id,"reference":"$reference","state":"handling","step":"picking"}""" }}],"counts":{"total":${references.size}}}"""

    fun inbox(vararg rows: Pair<String, String?>, unread: Int = rows.count { it.second == null }): String =
        """{"data":[${rows.joinToString(",") { (id, readAt) -> """{"id":"$id","kind":"delivery_note","event":"assigned","title":"$id assigned","read_at":${readAt?.let { "\"$it\"" } ?: "null"}}""" }}],"meta":{"current_page":1,"last_page":1,"per_page":30,"total":${rows.size}},"unread":$unread}"""

    fun badges(myWork: Int, inboxUnread: Int): String = """{"data":{"my_work":$myWork,"goods_out_unassigned":1,"inbox_unread":$inboxUnread,"goods_in_received":0}}"""

    fun dashboard(unassigned: Int): String = """{"data":{"generated_at":"2026-11-19T09:00:00Z","goods_out":{"unassigned":$unassigned,"handling":2,"packed":3,"in_warehouse":9}}}"""

    fun clocking(status: String): String =
        """{"data":{"status":"$status","since":"2026-11-19T08:00:00Z","timezone":"Europe/London","methods":["qr_code"],"has_shifts":true,"can_edit_notes":true}}"""

    fun thisWeek(workingSeconds: Long): String =
        """{"data":{"from":"2026-11-16","to":"2026-11-22","timesheets":[{"date":"2026-11-17","working_seconds":$workingSeconds}]}}"""

    fun warehouses(vararg ids: Long): String =
        """{"data":[${ids.joinToString(",") { id -> """{"id":$id,"slug":"wh$id","code":"WH$id","name":"Warehouse $id","organisation":{"id":3,"slug":"aw","name":"AW"},"can":{}}""" }}]}"""
}
