package io.aiku.droid.ui.inbox

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxApi
import io.aiku.droid.data.api.InboxNotification
import io.aiku.droid.data.api.InboxPage
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Instant

data class InboxUiState(
    val notifications: List<InboxNotification> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 0,
    val unread: Int? = null,
    val loadingPage: Int? = null,
    val isRefreshing: Boolean = false,
    val problem: GoodsOutProblem? = null,
    val failedPage: Int? = null,
    val markAllProblem: GoodsOutProblem? = null,
    val isSessionExpired: Boolean = false,
) {
    val hasLoaded: Boolean get() = page > 0
    val isEmpty: Boolean get() = hasLoaded && notifications.isEmpty() && problem == null
    val canLoadMore: Boolean get() = hasLoaded && page < lastPage && loadingPage == null && problem == null
    val isLoadingMore: Boolean get() = (loadingPage ?: 0) > 1
    val firstPageProblem: GoodsOutProblem? get() = problem?.takeIf { failedPage == 1 }
    val nextPageProblem: GoodsOutProblem? get() = problem?.takeIf { (failedPage ?: 0) > 1 }
    val unreadCount: Int get() = unread ?: notifications.count { it.isUnread }
}

class InboxViewModel(
    private val api: InboxApi,
    private val sessions: SessionRepository,
    arrivals: Flow<Unit>,
    private val now: () -> Instant = Instant::now,
) : ViewModel() {

    private val mutableState = MutableStateFlow(InboxUiState())
    val state: StateFlow<InboxUiState> = mutableState.asStateFlow()

    private var listJob: Job? = null

    init {
        viewModelScope.launch {
            arrivals.collect {
                if (mutableState.value.hasLoaded) {
                    loadPage(page = 1, isPull = false)
                }
            }
        }
    }

    fun onStart() {
        loadPage(page = 1, isPull = false)
    }

    fun refresh() {
        loadPage(page = 1, isPull = true)
    }

    fun retry() {
        loadPage(page = mutableState.value.failedPage ?: 1, isPull = false)
    }

    fun loadMore() {
        val current = mutableState.value
        if (current.canLoadMore) {
            loadPage(page = current.page + 1, isPull = false)
        }
    }

    fun markRead(notification: InboxNotification) {
        val token = sessions.session.value?.token ?: return
        if (!notification.isUnread) {
            return
        }
        val readAt = now().toString()
        mutableState.update { current ->
            current.copy(
                notifications = current.notifications.map { if (it.id == notification.id) it.copy(readAt = readAt) else it },
                unread = current.unread?.minus(1)?.coerceAtLeast(0),
            )
        }

        viewModelScope.launch {
            when (val result = api.markRead(token, notification.id)) {
                is GoodsOutResult.Loaded -> mutableState.update { current ->
                    current.copy(notifications = current.notifications.map { if (it.id == result.value.id) result.value else it })
                }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> Unit
            }
        }
    }

    fun markAllRead() {
        val token = sessions.session.value?.token ?: return
        val before = mutableState.value
        val readAt = now().toString()
        mutableState.update { current ->
            current.copy(
                notifications = current.notifications.map { if (it.isUnread) it.copy(readAt = readAt) else it },
                unread = 0,
                markAllProblem = null,
            )
        }

        viewModelScope.launch {
            when (val result = api.markAllRead(token)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.copy(unread = result.value) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(isSessionExpired = true) }
                else -> mutableState.update { current ->
                    current.copy(
                        notifications = current.notifications.map { row -> before.notifications.firstOrNull { it.id == row.id } ?: row },
                        unread = before.unread,
                        markAllProblem = result.problem(),
                    )
                }
            }
        }
    }

    fun markAllProblemShown() {
        mutableState.update { it.copy(markAllProblem = null) }
    }

    fun signInAgain() {
        sessions.end()
    }

    private fun loadPage(page: Int, isPull: Boolean) {
        val token = sessions.session.value?.token ?: return
        listJob?.cancel()
        mutableState.update { it.copy(loadingPage = page, isRefreshing = isPull, problem = null, failedPage = null) }

        listJob = viewModelScope.launch {
            when (val result = api.notifications(token, page)) {
                is GoodsOutResult.Loaded -> mutableState.update { it.loaded(result.value) }
                GoodsOutResult.SessionExpired -> mutableState.update { it.copy(loadingPage = null, isRefreshing = false, isSessionExpired = true) }
                else -> mutableState.update {
                    it.copy(loadingPage = null, isRefreshing = false, problem = result.problem(), failedPage = page)
                }
            }
        }
    }

    private fun InboxUiState.loaded(inboxPage: InboxPage): InboxUiState {
        val loadedPage = inboxPage.meta.currentPage
        val merged = if (loadedPage <= 1) inboxPage.notifications else (notifications + inboxPage.notifications).distinctBy { it.id }
        return copy(
            notifications = merged,
            page = loadedPage,
            lastPage = inboxPage.meta.lastPage,
            unread = inboxPage.unread ?: unread,
            loadingPage = null,
            isRefreshing = false,
            problem = null,
            failedPage = null,
        )
    }
}
