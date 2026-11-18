package io.aiku.droid.ui.search

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.SearchApi
import io.aiku.droid.data.api.SearchResults
import io.aiku.droid.data.api.SearchType
import io.aiku.droid.data.session.RecentSearches
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CompletableDeferred

data class SearchRequest(val warehouseId: Long, val query: String, val type: SearchType?, val page: Int)

class FakeSearchApi : SearchApi {
    val requests = mutableListOf<SearchRequest>()
    val cancelled = mutableListOf<SearchRequest>()
    val gates = mutableMapOf<String, CompletableDeferred<Unit>>()
    var answer: (SearchRequest) -> GoodsOutResult<SearchResults> = { GoodsOutResult.Loaded(SearchResults(query = it.query, types = SearchType.entries)) }

    val searches: List<SearchRequest> get() = requests.filter { it.query.isNotEmpty() }

    override suspend fun search(token: String, warehouseId: Long, query: String, type: SearchType?, page: Int): GoodsOutResult<SearchResults> {
        val request = SearchRequest(warehouseId, query, type, page)
        requests += request
        try {
            gates[query]?.await()
        } catch (cancellation: CancellationException) {
            cancelled += request
            throw cancellation
        }
        return answer(request)
    }
}

class InMemoryRecentSearches : RecentSearches {
    private val stored = mutableMapOf<Pair<Long, Long>, List<String>>()

    override fun read(userId: Long, warehouseId: Long): List<String> = stored[userId to warehouseId].orEmpty()

    override fun remember(userId: Long, warehouseId: Long, query: String): List<String> {
        val recent = (listOf(query) + read(userId, warehouseId).filterNot { it.equals(query, ignoreCase = true) }).take(RecentSearches.LIMIT)
        stored[userId to warehouseId] = recent
        return recent
    }

    override fun clear() {
        stored.clear()
    }
}
