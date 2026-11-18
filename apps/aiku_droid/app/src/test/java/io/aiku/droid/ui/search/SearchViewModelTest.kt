package io.aiku.droid.ui.search

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.SearchGroup
import io.aiku.droid.data.api.SearchResults
import io.aiku.droid.data.api.SearchRow
import io.aiku.droid.data.api.SearchType
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class SearchViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeSearchApi()
    private val sessions = FakeSessions()
    private val recent = InMemoryRecentSearches()
    private val sko = SearchRow(type = SearchType.Skos, id = 412, code = "SKO-00412", title = "SKO-00412", subtitle = "Lavender oil", quantity = 1240.0)
    private val otherSko = SearchRow(type = SearchType.Skos, id = 413, code = "SKO-00413", title = "SKO-00413")
    private val note = SearchRow(type = SearchType.DeliveryNotes, id = 991, code = "AR24817", title = "AR24817", state = "handling", stateLabel = "Picking")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun viewModel(warehouseId: Long? = 4) = SearchViewModel(api, sessions, recent, warehouseId)

    private fun typeAndWait(search: SearchViewModel, query: String) {
        search.onQueryChange(query)
        dispatcher.scheduler.advanceTimeBy(SearchViewModel.DEBOUNCE_MILLIS + 1)
    }

    private fun results(query: String, vararg groups: SearchGroup, exact: SearchRow? = null) =
        GoodsOutResult.Loaded(SearchResults(query = query, types = listOf(SearchType.Skos, SearchType.DeliveryNotes), exact = exact, groups = groups.toList()))

    @Test
    fun `opening asks for the types the user may search and reads this warehouse's recent searches`() {
        recent.remember(42, 4, "AR24817")
        recent.remember(42, 9, "elsewhere")
        api.answer = { GoodsOutResult.Loaded(SearchResults(types = listOf(SearchType.Skos, SearchType.Locations))) }
        val search = viewModel()

        search.onStart()

        assertEquals(listOf(SearchRequest(4, "", null, 1)), api.requests)
        assertEquals(listOf(SearchType.Skos, SearchType.Locations), search.state.value.types)
        assertEquals(listOf("AR24817"), search.state.value.recent)
        assertTrue(search.state.value.isQueryTooShort)
    }

    @Test
    fun `recent searches show only while the query is under two characters`() {
        recent.remember(42, 4, "AR24817")
        api.answer = { results(it.query, SearchGroup(type = SearchType.Skos, label = "SKOs", rows = listOf(sko))) }
        val search = viewModel()
        search.onStart()

        assertEquals(listOf("AR24817"), search.state.value.shownRecent)

        typeAndWait(search, "lav")

        assertEquals(listOf("AR24817"), search.state.value.recent)
        assertTrue(search.state.value.hasResults)
        assertTrue(search.state.value.shownRecent.isEmpty())

        api.answer = { results(it.query) }
        typeAndWait(search, "zzz")

        assertTrue(search.state.value.isEmpty)
        assertTrue(search.state.value.shownRecent.isEmpty())

        search.onQueryChange("z")

        assertEquals(listOf("AR24817"), search.state.value.shownRecent)
    }

    @Test
    fun `typing waits 300 ms before searching with the trimmed query`() {
        val search = viewModel()

        search.onQueryChange(" lav ")
        dispatcher.scheduler.advanceTimeBy(SearchViewModel.DEBOUNCE_MILLIS - 1)

        assertTrue(api.searches.isEmpty())
        assertTrue(search.state.value.isSearching)

        dispatcher.scheduler.advanceTimeBy(2)

        assertEquals(listOf(SearchRequest(4, "lav", null, 1)), api.searches)
        assertFalse(search.state.value.isSearching)
        assertEquals("lav", search.state.value.loadedQuery)
    }

    @Test
    fun `only the last of quickly typed queries is sent`() {
        val search = viewModel()

        search.onQueryChange("la")
        dispatcher.scheduler.advanceTimeBy(100)
        search.onQueryChange("lav")
        dispatcher.scheduler.advanceTimeBy(100)
        search.onQueryChange("lave")
        dispatcher.scheduler.advanceTimeBy(SearchViewModel.DEBOUNCE_MILLIS + 1)

        assertEquals(listOf("lave"), api.searches.map { it.query })
    }

    @Test
    fun `a newer query cancels the search in flight and its results never show`() {
        api.gates["lav"] = CompletableDeferred()
        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", false, listOf(if (request.query == "lav") sko else otherSko))) }
        val search = viewModel()

        typeAndWait(search, "lav")
        assertEquals(listOf("lav"), api.searches.map { it.query })

        typeAndWait(search, "SKO-00413")
        api.gates.getValue("lav").complete(Unit)

        assertEquals(listOf("lav"), api.cancelled.map { it.query })
        assertEquals(listOf(otherSko), search.state.value.groups.single().rows)
        assertEquals("SKO-00413", search.state.value.loadedQuery)
    }

    @Test
    fun `fewer than 2 characters clears the results without asking the server`() {
        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", false, listOf(sko))) }
        val search = viewModel()
        typeAndWait(search, "lav")

        typeAndWait(search, "l")

        assertEquals(1, api.searches.size)
        assertTrue(search.state.value.groups.isEmpty())
        assertNull(search.state.value.loadedQuery)
        assertFalse(search.state.value.isEmpty)
    }

    @Test
    fun `the exact match is shown on top and not repeated in its group`() {
        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", false, listOf(sko, otherSko)), exact = sko) }
        val search = viewModel()

        typeAndWait(search, "SKO-00412")

        assertEquals(sko, search.state.value.exact)
        assertEquals(listOf(otherSko), search.state.value.groups.single().rows)
    }

    @Test
    fun `an exact match alone drops its group`() {
        api.answer = { request -> results(request.query, SearchGroup(SearchType.DeliveryNotes, "Delivery notes", false, listOf(note)), exact = note) }
        val search = viewModel()

        typeAndWait(search, "AR24817")

        assertEquals(note, search.state.value.exact)
        assertTrue(search.state.value.groups.isEmpty())
        assertTrue(search.state.value.hasResults)
        assertFalse(search.state.value.isEmpty)
    }

    @Test
    fun `no results shows the empty state`() {
        api.answer = { request -> results(request.query) }
        val search = viewModel()

        typeAndWait(search, "rosemary")

        assertTrue(search.state.value.isEmpty)
    }

    @Test
    fun `a failed search shows the problem and retry searches again at once`() {
        api.answer = { GoodsOutResult.Unreachable }
        val search = viewModel()
        typeAndWait(search, "lav")

        assertEquals(GoodsOutProblem.Offline, search.state.value.problem)
        assertFalse(search.state.value.isEmpty)

        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", false, listOf(sko))) }
        search.retry()

        assertNull(search.state.value.problem)
        assertEquals(listOf(sko), search.state.value.groups.single().rows)
        assertEquals(2, api.searches.size)
    }

    @Test
    fun `a chip searches that type at once and Show more in All switches to its type`() {
        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", true, listOf(sko)), SearchGroup(SearchType.DeliveryNotes, "Delivery notes", false, listOf(note))) }
        val search = viewModel()
        typeAndWait(search, "lav")

        search.showMore(SearchType.Skos)

        assertEquals(SearchType.Skos, search.state.value.scope)
        assertEquals(SearchRequest(4, "lav", SearchType.Skos, 1), api.searches.last())
        assertEquals(listOf(SearchType.Skos), search.state.value.groups.map { it.type })

        search.selectScope(null)

        assertEquals(SearchRequest(4, "lav", null, 1), api.searches.last())
        assertEquals(2, search.state.value.groups.size)
    }

    @Test
    fun `Show more in a single type loads the next page and appends it`() {
        api.answer = { request ->
            when (request.page) {
                1 -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", true, listOf(sko)))
                else -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", false, listOf(sko, otherSko)))
            }
        }
        val search = viewModel()
        search.selectScope(SearchType.Skos)
        typeAndWait(search, "SKO")

        assertTrue(search.state.value.canLoadMore)

        search.showMore(SearchType.Skos)

        assertEquals(SearchRequest(4, "SKO", SearchType.Skos, 2), api.searches.last())
        assertEquals(listOf(sko, otherSko), search.state.value.groups.single().rows)
        assertFalse(search.state.value.groups.single().hasMore)
        assertFalse(search.state.value.canLoadMore)
        assertEquals(2, search.state.value.page)
    }

    @Test
    fun `a failed next page keeps the rows and retry asks for that page again`() {
        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", true, listOf(sko))) }
        val search = viewModel()
        search.selectScope(SearchType.Skos)
        typeAndWait(search, "SKO")

        api.answer = { GoodsOutResult.ServerError(500) }
        search.loadMore()

        assertEquals(GoodsOutProblem.ServerError, search.state.value.moreProblem)
        assertEquals(listOf(sko), search.state.value.groups.single().rows)

        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", false, listOf(otherSko))) }
        search.retry()

        assertEquals(SearchRequest(4, "SKO", SearchType.Skos, 2), api.searches.last())
        assertEquals(listOf(sko, otherSko), search.state.value.groups.single().rows)
        assertNull(search.state.value.moreProblem)
    }

    @Test
    fun `no searchable type at all shows the no access problem without chips`() {
        api.answer = { GoodsOutResult.Forbidden("") }
        val search = viewModel()

        search.onStart()

        assertEquals(GoodsOutProblem.Forbidden, search.state.value.problem)
        assertTrue(search.state.value.types.isEmpty())
    }

    @Test
    fun `an expired session asks to sign in again`() {
        api.answer = { GoodsOutResult.SessionExpired }
        val search = viewModel()

        typeAndWait(search, "lav")

        assertTrue(search.state.value.isSessionExpired)
    }

    @Test
    fun `opening a result remembers the query that found it for this warehouse`() {
        api.answer = { request -> results(request.query, SearchGroup(SearchType.Skos, "SKOs", false, listOf(sko))) }
        val search = viewModel()
        typeAndWait(search, "lav")

        search.rememberSearch()

        assertEquals(listOf("lav"), search.state.value.recent)
        assertEquals(listOf("lav"), recent.read(42, 4))
        assertTrue(recent.read(42, 9).isEmpty())
    }

    @Test
    fun `a recent search runs at once`() {
        val search = viewModel()

        search.pickRecent("AR24817")

        assertEquals(listOf(SearchRequest(4, "AR24817", null, 1)), api.searches)
        assertEquals("AR24817", search.state.value.query)
    }

    @Test
    fun `without a warehouse nothing is asked`() {
        val search = viewModel(warehouseId = null)

        search.onStart()
        typeAndWait(search, "lav")

        assertTrue(api.requests.isEmpty())
        assertFalse(search.state.value.isSearching)
    }
}
