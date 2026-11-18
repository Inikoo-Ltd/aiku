package io.aiku.droid.ui.goodsin

import io.aiku.droid.R
import io.aiku.droid.data.api.CustomerReturn
import io.aiku.droid.data.api.CustomerReturnItem
import io.aiku.droid.data.api.CustomerReturnRow
import io.aiku.droid.data.api.DeliveryNoteAction
import io.aiku.droid.data.api.GoodsInActionKey
import io.aiku.droid.data.api.GoodsInResponses
import io.aiku.droid.data.api.GoodsInSko
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.ListMeta
import io.aiku.droid.data.api.ReturnOutcome
import io.aiku.droid.data.api.ReturnPage
import io.aiku.droid.data.api.ReturnQuery
import io.aiku.droid.data.api.ReturnState
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.api.ReturnWrite
import io.aiku.droid.data.api.StateCounts
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class ReturnsViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeGoodsInApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun item(remaining: Double, isHandled: Boolean = false) = CustomerReturnItem(
        id = 51,
        isHandled = isHandled,
        sko = GoodsInSko(id = 77, code = "AWO-12"),
        expected = 3.0,
        remaining = remaining,
    )

    private fun customerReturn(state: String, item: CustomerReturnItem, vararg actions: String) = CustomerReturn(
        id = 5,
        reference = "DN1-ret",
        state = state,
        items = listOf(item),
        availableActions = actions.map { DeliveryNoteAction(key = it, label = it) },
    )

    private fun answer(after: CustomerReturn, message: String? = null): (ReturnWrite) -> GoodsOutResult<ReturnOutcome> =
        { GoodsOutResult.Loaded(ReturnOutcome(customerReturn = after, status = GoodsInWriteStatus.DONE, message = message)) }

    @Test
    fun `the returns list opens on all with counts, pages and filters by state and search`() {
        api.returnPages = { query ->
            val ids = if (query.page == 1) 1L..2L else 3L..3L
            GoodsOutResult.Loaded(
                ReturnPage(
                    rows = ids.map { CustomerReturnRow(id = it, reference = "R-$it") },
                    meta = ListMeta(currentPage = query.page, lastPage = 2, perPage = 50, total = 3),
                    counts = StateCounts(mapOf("all" to 16, "received" to 3)),
                ),
            )
        }
        val returns = ReturnsViewModel(api, FakeSessions(), warehouseId = 1, perPage = 50)

        returns.onStart()
        returns.loadMore()

        assertEquals(listOf(1L, 2L, 3L), returns.state.value.list.rows.map { it.id })
        assertEquals(16, returns.state.value.counts?.count(ReturnStateFilter.All.key))
        assertEquals(ReturnQuery(page = 1, perPage = 50), api.returnQueries.first())

        returns.selectState(ReturnStateFilter.Received)
        returns.search("DN1")
        dispatcher.scheduler.advanceTimeBy(301)

        assertEquals(ReturnQuery(state = ReturnStateFilter.Received, search = "DN1", page = 1, perPage = 50), api.returnQueries.last())
    }

    @Test
    fun `returns to process asks for received and returning together and is named in the title`() {
        val returns = ReturnsViewModel(api, FakeSessions(), warehouseId = 1, initialState = ReturnStateFilter.ToProcess)

        returns.onStart()

        assertEquals(ReturnStateFilter.ToProcess, api.returnQueries.single().state)
        assertEquals(listOf("state" to "to_process", "page" to "1", "per_page" to "50"), GoodsInResponses.returnsParameters(api.returnQueries.single()))
        assertEquals(R.string.goodsin_returns_to_process_title, returnsListTitle(ReturnStateFilter.ToProcess))
        assertEquals(R.string.section_returns, returnsListTitle(ReturnStateFilter.Received))
        assertEquals(-1, returnChipStates.indexOfFirst { it.first == ReturnStateFilter.ToProcess })
    }

    @Test
    fun `a hub figure opens the returns list on its state`() {
        val returns = ReturnsViewModel(api, FakeSessions(), warehouseId = 1, initialState = ReturnStateFilter.Returning)

        returns.onStart()

        assertEquals(ReturnStateFilter.Returning, api.returnQueries.single().state)
    }

    @Test
    fun `starting, the item actions and undo send their writes`() {
        api.customerReturn = GoodsOutResult.Loaded(customerReturn(ReturnState.RECEIVED, item(remaining = 3.0), GoodsInActionKey.START))
        val returning = customerReturn(ReturnState.RETURNING, item(remaining = 3.0), GoodsInActionKey.PUT_BACK, GoodsInActionKey.UNDO)
        api.returnWriteResults = answer(returning)
        val detail = ReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = 5)
        detail.onStart()

        detail.start()
        detail.backToStock(itemId = 51, quantity = 1.5, locationOrgStockId = 5501)
        detail.damaged(itemId = 51, quantity = 0.5)
        detail.notReturned(itemId = 51, quantity = 1.0)
        detail.undo(putId = 700)

        assertEquals(
            listOf(
                ReturnWrite.Start,
                ReturnWrite.Returned(itemId = 51, quantity = 1.5, locationOrgStockId = 5501),
                ReturnWrite.Damaged(itemId = 51, quantity = 0.5),
                ReturnWrite.NotReturned(itemId = 51, quantity = 1.0),
                ReturnWrite.UndoPut(putId = 700),
            ),
            api.returnWrites,
        )
        assertEquals(ReturnState.RETURNING, detail.state.value.customerReturn?.state)
    }

    @Test
    fun `set as returned closes the return and says what became not returned`() {
        api.customerReturn = GoodsOutResult.Loaded(customerReturn(ReturnState.RETURNING, item(remaining = 1.0, isHandled = true), GoodsInActionKey.SET_AS_RETURNED))
        api.returnWriteResults = answer(customerReturn(ReturnState.RETURNED, item(remaining = 0.0, isHandled = true)), message = "1 remaining set as not returned.")
        val detail = ReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = 5)
        detail.onStart()

        detail.setAsReturned()

        val event = detail.state.value.event as GoodsInEvent.Done
        assertEquals(ReturnWrite.SetAsReturned, event.action)
        assertEquals("1 remaining set as not returned.", event.message)
        assertEquals(ReturnState.RETURNED, detail.state.value.customerReturn?.state)
        assertTrue(detail.state.value.customerReturn?.availableActions.orEmpty().isEmpty())
    }

    @Test
    fun `a refused item action keeps the return and the error`() {
        api.customerReturn = GoodsOutResult.Loaded(customerReturn(ReturnState.RETURNING, item(remaining = 1.0), GoodsInActionKey.PUT_BACK))
        api.returnWriteResults = { GoodsOutResult.Invalid("Only 1 remaining", mapOf("quantity" to listOf("Only 1 remaining"))) }
        val detail = ReturnViewModel(api, FakeSessions(), warehouseId = 1, returnId = 5)
        detail.onStart()

        detail.damaged(itemId = 51, quantity = 2.0)

        assertEquals("Only 1 remaining", (detail.state.value.event as GoodsInEvent.Refused).message)
        assertEquals(listOf("Only 1 remaining"), detail.state.value.writeErrors["quantity"])
        assertFalse(detail.state.value.isWorking)
        assertEquals(ReturnState.RETURNING, detail.state.value.customerReturn?.state)
    }
}
