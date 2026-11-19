package io.aiku.droid.ui.splash

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.app.WarehousesUiState
import io.aiku.droid.update.AppUpdate
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

enum class StartupDestination { Undecided, SignIn, SignedIn, UpdateRequired }

fun startupDestination(update: AppUpdate, session: StaffSession?, warehouses: WarehousesUiState?): StartupDestination = when {
    update is AppUpdate.Required -> StartupDestination.UpdateRequired
    session == null -> StartupDestination.SignIn
    warehouses == null || warehouses == WarehousesUiState.Loading -> StartupDestination.Undecided
    else -> StartupDestination.SignedIn
}

data class StartupSplashState(
    val isVisible: Boolean,
    val showsProgress: Boolean = false,
)

class StartupSplashViewModel(isFreshLaunch: Boolean) : ViewModel() {

    private val mutableState = MutableStateFlow(StartupSplashState(isVisible = isFreshLaunch))
    val state: StateFlow<StartupSplashState> = mutableState.asStateFlow()

    private var hasShownLongEnough = false
    private var isDestinationKnown = false

    init {
        if (isFreshLaunch) {
            viewModelScope.launch {
                delay(MINIMUM_VISIBLE_MILLIS)
                hasShownLongEnough = true
                hideWhenSettled()
            }
            viewModelScope.launch {
                delay(PROGRESS_DELAY_MILLIS)
                mutableState.update { if (it.isVisible) it.copy(showsProgress = true) else it }
            }
        }
    }

    fun onDestination(destination: StartupDestination) {
        if (destination == StartupDestination.Undecided) {
            return
        }
        isDestinationKnown = true
        hideWhenSettled()
    }

    private fun hideWhenSettled() {
        if (hasShownLongEnough && isDestinationKnown) {
            mutableState.update { it.copy(isVisible = false) }
        }
    }

    companion object {
        const val MINIMUM_VISIBLE_MILLIS = 400L
        const val PROGRESS_DELAY_MILLIS = 1_500L
    }
}
