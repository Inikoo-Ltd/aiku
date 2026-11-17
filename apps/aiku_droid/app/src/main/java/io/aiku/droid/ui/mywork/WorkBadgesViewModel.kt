package io.aiku.droid.ui.mywork

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MyWorkApi
import io.aiku.droid.data.api.WorkBadges
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch

class WorkBadgesViewModel(
    private val api: MyWorkApi,
    private val sessions: SessionRepository,
) : ViewModel(), WorkBadgesController {

    private val mutableBadges = MutableStateFlow(WorkBadges())
    override val badges: StateFlow<WorkBadges> = mutableBadges.asStateFlow()

    private var warehouseId: Long? = null
    private var refreshJob: Job? = null

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
        if (id == warehouseId) {
            return
        }
        warehouseId = id
        clear()
        refresh()
    }

    override fun refresh() {
        val id = warehouseId ?: return
        val token = sessions.session.value?.token ?: return
        refreshJob?.cancel()

        refreshJob = viewModelScope.launch {
            when (val result = api.badges(token, id)) {
                is GoodsOutResult.Loaded -> mutableBadges.value = result.value
                GoodsOutResult.Unreachable, is GoodsOutResult.ServerError -> Unit
                else -> mutableBadges.value = WorkBadges()
            }
        }
    }

    private fun clear() {
        refreshJob?.cancel()
        mutableBadges.value = WorkBadges()
    }
}
