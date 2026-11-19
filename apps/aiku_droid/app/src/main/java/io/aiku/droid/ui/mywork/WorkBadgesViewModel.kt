package io.aiku.droid.ui.mywork

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxApi
import io.aiku.droid.data.api.MyWorkApi
import io.aiku.droid.data.api.WorkBadges
import io.aiku.droid.data.cache.CachedScreens
import io.aiku.droid.data.cache.ScreenCache
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

class WorkBadgesViewModel(
    private val api: MyWorkApi,
    private val sessions: SessionRepository,
    private val inbox: InboxApi,
    private val cache: ScreenCache,
) : ViewModel(), WorkBadgesController {

    private val mutableBadges = MutableStateFlow(WorkBadges())
    override val badges: StateFlow<WorkBadges> = mutableBadges.asStateFlow()

    private var shown: ShownWarehouse? = null
    private var refreshJob: Job? = null
    private var hasWarehouseBadges = false

    init {
        viewModelScope.launch {
            sessions.session
                .map { it?.token }
                .distinctUntilChanged()
                .collect {
                    clear()
                    refresh()
                }
        }
    }

    fun showWarehouse(id: Long?) {
        val next = ShownWarehouse(id)
        if (shown == next) {
            return
        }
        shown = next
        clear()
        refresh()
    }

    override fun refresh() {
        val current = shown ?: return
        val token = sessions.session.value?.token ?: return
        refreshJob?.cancel()

        refreshJob = viewModelScope.launch {
            val warehouseId = current.id
            if (warehouseId == null) {
                refreshInboxOnly(token)
            } else {
                refreshWarehouse(token, warehouseId)
            }
        }
    }

    override fun showInboxUnread(count: Int) {
        mutableBadges.update { it.copy(inboxUnread = count) }
    }

    private suspend fun refreshWarehouse(token: String, warehouseId: Long) {
        if (!hasWarehouseBadges) {
            cache.read(warehouseId, CachedScreens.workBadges)?.let { cached ->
                mutableBadges.value = cached.value
                hasWarehouseBadges = true
            }
        }
        when (val result = api.badges(token, warehouseId)) {
            is GoodsOutResult.Loaded -> {
                mutableBadges.value = result.value
                hasWarehouseBadges = true
            }
            GoodsOutResult.Unreachable, is GoodsOutResult.ServerError -> Unit
            else -> mutableBadges.value = WorkBadges()
        }
    }

    private suspend fun refreshInboxOnly(token: String) {
        when (val result = inbox.unreadCount(token)) {
            is GoodsOutResult.Loaded -> mutableBadges.value = WorkBadges(inboxUnread = result.value)
            GoodsOutResult.Unreachable, is GoodsOutResult.ServerError -> Unit
            else -> mutableBadges.value = WorkBadges()
        }
    }

    private fun clear() {
        refreshJob?.cancel()
        hasWarehouseBadges = false
        mutableBadges.value = WorkBadges()
    }

    private data class ShownWarehouse(val id: Long?)
}
