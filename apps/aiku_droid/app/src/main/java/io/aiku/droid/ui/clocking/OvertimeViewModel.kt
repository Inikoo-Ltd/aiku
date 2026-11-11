package io.aiku.droid.ui.clocking

import androidx.compose.runtime.staticCompositionLocalOf
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.HrApi
import io.aiku.droid.data.api.HrWriteResult
import io.aiku.droid.data.api.OvertimeRequest
import io.aiku.droid.data.api.OvertimeRequestInput
import io.aiku.droid.data.api.OvertimeTypeOption
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class OvertimeUiState(
    val list: PagedList<OvertimeRequest> = PagedList(),
    val typeOptions: List<OvertimeTypeOption> = emptyList(),
    val submission: HrSubmission = HrSubmission.Idle,
)

interface OvertimeController {
    val state: StateFlow<OvertimeUiState>

    fun load()

    fun loadMore()

    fun resetSubmission()

    fun requestOvertime(input: OvertimeRequestInput)

    fun updateOvertime(overtimeId: Long, input: OvertimeRequestInput)
}

class OvertimeViewModel(
    private val hrApi: HrApi,
    private val sessions: SessionRepository,
) : ViewModel(), OvertimeController {

    private val mutableState = MutableStateFlow(OvertimeUiState())
    override val state: StateFlow<OvertimeUiState> = mutableState.asStateFlow()

    private val mutableSessionExpired = MutableStateFlow(false)
    val isSessionExpired: StateFlow<Boolean> = mutableSessionExpired.asStateFlow()

    private var listJob: Job? = null
    private var submitJob: Job? = null

    init {
        onEachSessionToken(sessions) {
            listOfNotNull(listJob, submitJob).forEach { it.cancel() }
            mutableState.value = OvertimeUiState()
            mutableSessionExpired.value = false
        }
    }

    override fun load() = loadPage(1)

    override fun loadMore() {
        val list = mutableState.value.list
        if (list.canLoadMore) {
            loadPage(list.page + 1)
        }
    }

    override fun resetSubmission() {
        submitJob?.cancel()
        mutableState.update { it.copy(submission = HrSubmission.Idle) }
    }

    override fun requestOvertime(input: OvertimeRequestInput) = submit { token -> hrApi.requestOvertime(token, input) }

    override fun updateOvertime(overtimeId: Long, input: OvertimeRequestInput) = submit { token -> hrApi.updateOvertime(token, overtimeId, input) }

    private fun loadPage(page: Int) {
        val token = currentToken() ?: return
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = hrApi.overtimeRequests(token, page, HR_PAGE_SIZE)) {
                is ClockingResult.Loaded -> mutableState.update { current ->
                    current.copy(
                        list = current.list.loaded(result.value.overtimeRequests, result.value.pagination, OvertimeRequest::id),
                        typeOptions = result.value.typeOptions,
                    )
                }
                ClockingResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(list = it.list.failed(page, clockingProblemOf(result))) }
            }
        }
    }

    private fun submit(send: suspend (token: String) -> HrWriteResult<OvertimeRequest>) {
        val token = currentToken() ?: return
        if (mutableState.value.submission == HrSubmission.Sending) {
            return
        }
        mutableState.update { it.copy(submission = HrSubmission.Sending) }

        submitJob = viewModelScope.launch {
            val result = send(token)
            if (result == HrWriteResult.SessionExpired) {
                expireSession()
            }
            mutableState.update { it.copy(submission = result.toSubmission()) }
            if (result is HrWriteResult.Saved) {
                loadPage(1)
            }
        }
    }

    private fun currentToken(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableSessionExpired.value = true
    }
}

private object NoOvertime : OvertimeController {
    override val state: StateFlow<OvertimeUiState> = MutableStateFlow(OvertimeUiState()).asStateFlow()
    override fun load() = Unit
    override fun loadMore() = Unit
    override fun resetSubmission() = Unit
    override fun requestOvertime(input: OvertimeRequestInput) = Unit
    override fun updateOvertime(overtimeId: Long, input: OvertimeRequestInput) = Unit
}

val LocalOvertime = staticCompositionLocalOf<OvertimeController> { NoOvertime }
