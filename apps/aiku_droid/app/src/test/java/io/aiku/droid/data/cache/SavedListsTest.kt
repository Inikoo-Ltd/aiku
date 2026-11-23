package io.aiku.droid.data.cache

import io.aiku.droid.data.api.CustomerRef
import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.DeliveryNoteRow
import io.aiku.droid.data.api.StaffRef
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Duration

@OptIn(ExperimentalCoroutinesApi::class)
class SavedListsTest {

    private val store = InMemoryScreenCacheStore()
    private val clock = TestClock()
    private val sessions = FakeSessions()
    private val cache = testScreenCache(sessions = sessions, store = store, clock = clock)

    @Test
    fun `rows from every page are kept together, newest first, and the last load time is stored`() = runTest {
        cache.remember(4, SavedLists.returns, listOf(returnRow(1), returnRow(2)))
        clock.now = CACHE_NOW + Duration.ofMinutes(3)
        cache.remember(4, SavedLists.returns, listOf(returnRow(3)))

        val saved = cache.saved(4, SavedLists.returns)

        assertEquals(listOf(3L, 1L, 2L), saved?.value?.map { it.id })
        assertEquals(CACHE_NOW + Duration.ofMinutes(3), saved?.fetchedAt)
    }

    @Test
    fun `a row seen again replaces its earlier copy and moves to the front`() = runTest {
        cache.remember(4, SavedLists.returns, listOf(returnRow(1), returnRow(2)))

        cache.remember(4, SavedLists.returns, listOf(returnRow(2, state = "returning")))

        val saved = cache.saved(4, SavedLists.returns)?.value.orEmpty()
        assertEquals(listOf(2L, 1L), saved.map { it.id })
        assertEquals("returning", saved.first().state)
    }

    @Test
    fun `the saved set keeps 500 rows and drops the oldest seen first`() = runTest {
        cache.remember(4, SavedLists.returns, (1L..300L).map { returnRow(it) })
        cache.remember(4, SavedLists.returns, (301L..600L).map { returnRow(it) })

        val ids = cache.saved(4, SavedLists.returns)?.value.orEmpty().map { it.id }

        assertEquals(SavedLists.MAX_ROWS, ids.size)
        assertTrue(ids.containsAll((301L..600L).toList()))
        assertTrue(ids.containsAll((1L..200L).toList()))
        assertFalse(ids.any { it in 201L..300L })
    }

    @Test
    fun `each warehouse and list has its own encrypted saved set for the signed-in user`() = runTest {
        cache.remember(4, SavedLists.deliveryNotes, listOf(DeliveryNoteRow(id = 9, reference = "AR9", customerName = "Ana Shop", picker = StaffRef(42, "Ana"))))
        cache.remember(5, SavedLists.returns, listOf(returnRow(1)))

        assertEquals("Ana Shop", cache.saved(4, SavedLists.deliveryNotes)?.value?.single()?.customerName)
        assertNull(cache.saved(5, SavedLists.deliveryNotes))
        assertNull(cache.saved(4, SavedLists.returns))
        val stored = store.rows.getValue(ScreenCacheKey(userId = SIGNED_IN_USER, warehouseId = 4, screen = SavedLists.deliveryNotes.key))
        assertFalse(stored.payload.toString(Charsets.UTF_8).contains("AR9"))
    }

    @Test
    fun `signing out and any 401 wipe the saved rows with the rest of the cache`() = runTest {
        cache.remember(4, SavedLists.returns, listOf(returnRow(1)))

        cache.clear()

        assertNull(cache.saved(4, SavedLists.returns))
    }

    @Test
    fun `nothing is saved without a signed-in user`() = runTest {
        sessions.end()

        cache.remember(4, SavedLists.returns, listOf(returnRow(1)))

        assertTrue(store.rows.isEmpty())
    }

    private fun returnRow(id: Long, state: String = "received") = CustomerReturnRow(id = id, reference = "RET-$id", state = state, customer = CustomerRef("Customer $id"))
}
