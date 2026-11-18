package io.aiku.droid.ui.search

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.SearchApi
import io.aiku.droid.data.api.SearchGroup
import io.aiku.droid.data.api.SearchResults
import io.aiku.droid.data.api.SearchRow
import io.aiku.droid.data.api.SearchType
import io.aiku.droid.data.session.RecentSearches
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class SearchUiState(
    val query: String = "",
    val scope: SearchType? = null,
    val types: List<SearchType> = emptyList(),
    val exact: SearchRow? = null,
    val groups: List<SearchGroup> = emptyList(),
    val loadedQuery: String? = null,
    val page: Int = 1,
    val isSearching: Boolean = false,
    val isLoadingMore: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val moreProblem: GoodsOutProblem? = null,
    val recent: List<String> = emptyList(),
    val isSessionExpired: Boolean = false,
) {
    val needle: String get() = query.trim()

    val isQueryTooShort: Boolean get() = needle.length < SearchViewModel.MIN_QUERY_LENGTH

    val shownRecent: List<String> get() = if (isQueryTooShort) recent else emptyList()

    val hasResults: Boolean get() = exact != null || groups.isNotEmpty()

    val isEmpty: Boolean get() = !isQueryTooShort && !isSearching && problem == null && loadedQuery == needle && !hasResults

    val canLoadMore: Boolean get() = scope != null && groups.any { it.hasMore } && !isLoadingMore && !isSearching
}

class SearchViewModel(
    private val api: SearchApi,
    private val sessions: SessionRepository,
    private val recentSearches: RecentSearches,
    private val warehouseId: Long?,
    private val debounceMillis: Long = DEBOUNCE_MILLIS,
) : ViewModel() {

    private val mutableState = MutableStateFlow(SearchUiState())
    val state: StateFlow<SearchUiState> = mutableState.asStateFlow()

    private var searchJob: Job? = null
    private var typesJob: Job? = null

    fun onStart() {
        val warehouse = warehouseId ?: return
        val userId = sessions.session.value?.userId ?: return
        mutableState.update { it.copy(recent = recentSearches.read(userId, warehouse)) }
        if (mutableState.value.types.isEmpty() && typesJob?.isActive != true) {
            loadTypes(warehouse)
        }
    }

    fun onQueryChange(query: String) {
        val previousNeedle = mutableState.value.needle
        mutableState.update { it.copy(query = query) }
        if (query.trim() == previousNeedle) {
            return
        }
        searchFromStart(delayMillis = debounceMillis)
    }

    fun searchNow() {
        val current = mutableState.value
        if (current.loadedQuery == current.needle && current.problem == null && !current.isSearching) {
            return
        }
        searchFromStart(delayMillis = 0)
    }

    fun pickRecent(query: String) {
        mutableState.update { it.copy(query = query) }
        searchFromStart(delayMillis = 0)
    }

    fun selectScope(scope: SearchType?) {
        if (scope == mutableState.value.scope) {
            return
        }
        mutableState.update { it.copy(scope = scope, exact = null, groups = emptyList(), loadedQuery = null) }
        searchFromStart(delayMillis = 0)
    }

    fun showMore(type: SearchType) {
        if (mutableState.value.scope == null) {
            selectScope(type)
        } else {
            loadMore()
        }
    }

    fun loadMore() {
        val current = mutableState.value
        val scope = current.scope ?: return
        val warehouse = warehouseId ?: return
        val token = sessions.session.value?.token ?: return
        val needle = current.loadedQuery ?: return
        if (!current.canLoadMore) {
            return
        }
        val nextPage = current.page + 1
        mutableState.update { it.copy(isLoadingMore = true, moreProblem = null) }

        searchJob = viewModelScope.launch {
            when (val result = api.search(token, warehouse, needle, scope, nextPage)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.withNextPage(result.value, scope, nextPage) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isLoadingMore = false, isSessionExpired = true) }
                else -> mutableState.update { it.copy(isLoadingMore = false, moreProblem = result.problem()) }
            }
        }
    }

    fun retry() {
        if (mutableState.value.moreProblem != null) {
            loadMore()
        } else {
            searchFromStart(delayMillis = 0)
        }
    }

    fun rememberSearch() {
        val warehouse = warehouseId ?: return
        val userId = sessions.session.value?.userId ?: return
        val current = mutableState.value
        val searched = (current.loadedQuery ?: current.needle).takeIf { it.isNotEmpty() } ?: return
        mutableState.update { it.copy(recent = recentSearches.remember(userId, warehouse, searched)) }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun searchFromStart(delayMillis: Long) {
        searchJob?.cancel()
        val current = mutableState.value
        val needle = current.needle
        val warehouse = warehouseId
        val token = sessions.session.value?.token
        if (current.isQueryTooShort || warehouse == null || token == null) {
            mutableState.update {
                it.copy(exact = null, groups = emptyList(), loadedQuery = null, page = 1, isSearching = false, isLoadingMore = false, problem = null, moreProblem = null)
            }
            return
        }
        val scope = current.scope
        mutableState.update { it.copy(isSearching = true, isLoadingMore = false, problem = null, moreProblem = null) }

        searchJob = viewModelScope.launch {
            if (delayMillis > 0) {
                delay(delayMillis)
            }
            when (val result = api.search(token, warehouse, needle, scope, FIRST_PAGE)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.withFirstPage(result.value, needle, scope) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSearching = false, isSessionExpired = true) }
                else -> mutableState.update {
                    it.copy(
                        types = if (scope == null && result is GoodsOutResult.Forbidden) emptyList() else it.types,
                        exact = null,
                        groups = emptyList(),
                        loadedQuery = null,
                        isSearching = false,
                        problem = result.problem(),
                    )
                }
            }
        }
    }

    private fun loadTypes(warehouse: Long) {
        val token = sessions.session.value?.token ?: return

        typesJob = viewModelScope.launch {
            when (val result = api.search(token, warehouse, "", null, FIRST_PAGE)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(types = result.value.types) }
                is GoodsOutResult.Forbidden -> mutableState.update { if (it.isSearching || it.hasResults) it else it.copy(problem = result.problem()) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> Unit
            }
        }
    }

    private fun SearchUiState.withFirstPage(results: SearchResults, needle: String, scope: SearchType?): SearchUiState {
        val exact = results.exact
        val groups = results.groups
            .filter { scope == null || it.type == scope }
            .map { group -> group.copy(rows = group.rows.filterNot { row -> exact != null && row.isSameRecord(exact) }) }
            .filter { it.rows.isNotEmpty() || it.hasMore }
        return copy(
            types = results.types.ifEmpty { types },
            exact = exact,
            groups = groups,
            loadedQuery = needle,
            page = FIRST_PAGE,
            isSearching = false,
            problem = null,
        )
    }

    private fun SearchUiState.withNextPage(results: SearchResults, scope: SearchType, page: Int): SearchUiState {
        val next = results.groups.firstOrNull { it.type == scope }
        val groups = groups.map { group ->
            if (group.type != scope) {
                group
            } else {
                val known = group.rows + listOfNotNull(exact)
                val added = next?.rows.orEmpty().filterNot { row -> known.any { it.isSameRecord(row) } }
                group.copy(rows = group.rows + added, hasMore = next?.hasMore == true)
            }
        }
        return copy(groups = groups, page = page, isLoadingMore = false, moreProblem = null)
    }

    companion object {
        const val MIN_QUERY_LENGTH = 2
        const val DEBOUNCE_MILLIS = 300L
        private const val FIRST_PAGE = 1
    }
}
