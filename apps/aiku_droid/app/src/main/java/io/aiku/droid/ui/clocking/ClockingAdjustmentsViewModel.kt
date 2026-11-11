package io.aiku.droid.ui.clocking

import androidx.compose.runtime.staticCompositionLocalOf
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.ClockingAdjustment
import io.aiku.droid.data.api.ClockingAdjustmentInput
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.HrApi
import io.aiku.droid.data.api.HrAttachment
import io.aiku.droid.data.api.HrWriteResult
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.io.File

data class ClockingAdjustmentsUiState(
    val list: PagedList<ClockingAdjustment> = PagedList(),
    val submission: HrSubmission = HrSubmission.Idle,
    val cancellation: HrSubmission = HrSubmission.Idle,
    val cancellingId: Long? = null,
)

interface ClockingAdjustmentsController : AttachmentSource {
    val state: StateFlow<ClockingAdjustmentsUiState>

    fun load()

    fun loadMore()

    fun resetSubmission()

    fun requestAdjustment(input: ClockingAdjustmentInput)

    fun cancelAdjustment(adjustmentId: Long)

    fun resetCancellation()
}

class ClockingAdjustmentsViewModel(
    private val hrApi: HrApi,
    private val sessions: SessionRepository,
) : ViewModel(), ClockingAdjustmentsController {

    private val mutableState = MutableStateFlow(ClockingAdjustmentsUiState())
    override val state: StateFlow<ClockingAdjustmentsUiState> = mutableState.asStateFlow()

    private val mutableSessionExpired = MutableStateFlow(false)
    val isSessionExpired: StateFlow<Boolean> = mutableSessionExpired.asStateFlow()

    private var listJob: Job? = null
    private var submitJob: Job? = null
    private var cancelJob: Job? = null

    init {
        onEachSessionToken(sessions) {
            listOfNotNull(listJob, submitJob, cancelJob).forEach { it.cancel() }
            mutableState.value = ClockingAdjustmentsUiState()
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

    override fun requestAdjustment(input: ClockingAdjustmentInput) {
        val token = currentToken() ?: return
        if (mutableState.value.submission == HrSubmission.Sending) {
            return
        }
        mutableState.update { it.copy(submission = HrSubmission.Sending) }

        submitJob = viewModelScope.launch {
            val result = hrApi.requestClockingAdjustment(token, input)
            if (result == HrWriteResult.SessionExpired) {
                expireSession()
            }
            mutableState.update { it.copy(submission = result.toSubmission()) }
            if (result is HrWriteResult.Saved) {
                loadPage(1)
            }
        }
    }

    override fun cancelAdjustment(adjustmentId: Long) {
        val token = currentToken() ?: return
        if (mutableState.value.cancellingId != null) {
            return
        }
        mutableState.update { it.copy(cancellation = HrSubmission.Sending, cancellingId = adjustmentId) }

        cancelJob = viewModelScope.launch {
            val result = hrApi.cancelClockingAdjustment(token, adjustmentId)
            if (result == HrWriteResult.SessionExpired) {
                expireSession()
            }
            mutableState.update { it.copy(cancellation = result.toSubmission(), cancellingId = null) }
            if (result is HrWriteResult.Saved) {
                loadPage(1)
            }
        }
    }

    override fun resetCancellation() {
        mutableState.update { it.copy(cancellation = HrSubmission.Idle) }
    }

    override suspend fun download(attachment: HrAttachment, target: File): AttachmentDownload {
        val token = currentToken() ?: return AttachmentDownload.SessionExpired
        val result = hrApi.downloadAttachment(token, attachment.url, target)
        if (result == AttachmentDownload.SessionExpired) {
            expireSession()
        }
        return result
    }

    private fun loadPage(page: Int) {
        val token = currentToken() ?: return
        listJob?.cancel()
        mutableState.update { it.copy(list = it.list.loading(page)) }

        listJob = viewModelScope.launch {
            when (val result = hrApi.clockingAdjustments(token, page, HR_PAGE_SIZE)) {
                is ClockingResult.Loaded -> mutableState.update { current ->
                    current.copy(list = current.list.loaded(result.value.adjustments, result.value.pagination, ClockingAdjustment::id))
                }
                ClockingResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(list = it.list.failed(page, clockingProblemOf(result))) }
            }
        }
    }

    private fun currentToken(): String? = sessions.session.value?.token

    private fun expireSession() {
        mutableSessionExpired.value = true
    }
}

private object NoClockingAdjustments : ClockingAdjustmentsController {
    override val state: StateFlow<ClockingAdjustmentsUiState> = MutableStateFlow(ClockingAdjustmentsUiState()).asStateFlow()
    override fun load() = Unit
    override fun loadMore() = Unit
    override fun resetSubmission() = Unit
    override fun requestAdjustment(input: ClockingAdjustmentInput) = Unit
    override fun cancelAdjustment(adjustmentId: Long) = Unit
    override fun resetCancellation() = Unit
    override suspend fun download(attachment: HrAttachment, target: File): AttachmentDownload = AttachmentDownload.Unreachable
}

val LocalClockingAdjustments = staticCompositionLocalOf<ClockingAdjustmentsController> { NoClockingAdjustments }
