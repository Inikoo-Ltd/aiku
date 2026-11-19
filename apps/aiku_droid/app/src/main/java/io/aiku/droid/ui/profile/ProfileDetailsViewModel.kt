package io.aiku.droid.ui.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.ProfileResult
import io.aiku.droid.data.api.ProfileSource
import io.aiku.droid.data.api.StaffProfile
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class ProfileDetailsViewModel(
    private val profiles: ProfileSource,
    private val sessions: SessionRepository,
) : ViewModel() {
    private val loadedProfile = MutableStateFlow<StaffProfile?>(null)
    val profile: StateFlow<StaffProfile?> = loadedProfile.asStateFlow()

    private val refreshing = MutableStateFlow(false)
    val isRefreshing: StateFlow<Boolean> = refreshing.asStateFlow()

    private var loadJob: Job? = null

    init {
        refresh()
    }

    fun refresh(isPull: Boolean = false) {
        val token = sessions.session.value?.token ?: return
        loadJob?.cancel()
        refreshing.value = isPull
        loadJob = viewModelScope.launch {
            val result = profiles.profile(token)
            if (result is ProfileResult.Loaded) {
                loadedProfile.value = result.profile
            }
            refreshing.value = false
        }
    }
}
