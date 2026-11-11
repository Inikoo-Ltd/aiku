package io.aiku.droid.ui.clocking

import androidx.compose.runtime.staticCompositionLocalOf
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.HrApi
import io.aiku.droid.data.api.HrAttachment
import io.aiku.droid.data.api.HrWriteResult
import io.aiku.droid.data.api.Leave
import io.aiku.droid.data.api.LeavePage
import io.aiku.droid.data.api.LeaveRequestInput
import io.aiku.droid.data.api.UploadFile
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.io.File

data class LeaveUiState(
    val list: PagedList<Leave> = PagedList(),
    val overview: LeavePage? = null,
    val submission: HrSubmission = HrSubmission.Idle,
)

interface LeaveController : AttachmentSource {
    val state: StateFlow<LeaveUiState>

    fun load()

    fun loadMore()

    fun resetSubmission()

    fun requestLeave(input: LeaveRequestInput)

    fun replaceCertificate(leaveId: Long, attachments: List<UploadFile>)
}

class LeaveViewModel(
    private val hrApi: HrApi,
    private val sessions: SessionRepository,
) : ViewModel(), LeaveController {

    private val mutableState = MutableStateFlow(LeaveUiState())
    override val state: StateFlow<LeaveUiState> = mutableState.asStateFlow()

    private val mutableSessionExpired = MutableStateFlow(false)
    val isSessionExpired: StateFlow<Boolean> = mutableSessionExpired.asStateFlow()

    private var listJob: Job? = null
    private var submitJob: Job? = null

    init {
        onEachSessionToken(sessions) {
            listOfNotNull(listJob, submitJob).forEach { it.cancel() }
            mutableState.value = LeaveUiState()
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

    override fun requestLeave(input: LeaveRequestInput) = submit { token -> hrApi.requestLeave(token, input) }

    override fun replaceCertificate(leaveId: Long, attachments: List<UploadFile>) =
        submit { token -> hrApi.replaceLeaveAttachments(token, leaveId, attachments) }

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
            when (val result = hrApi.leaves(token, page, HR_PAGE_SIZE)) {
                is ClockingResult.Loaded -> mutableState.update { current ->
                    current.copy(
                        list = current.list.loaded(result.value.leaves, result.value.pagination, Leave::id),
                        overview = result.value,
                    )
                }
                ClockingResult.SessionExpired -> expireSession()
                else -> mutableState.update { it.copy(list = it.list.failed(page, clockingProblemOf(result))) }
            }
        }
    }

    private fun submit(send: suspend (token: String) -> HrWriteResult<Leave>) {
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

private object NoLeave : LeaveController {
    override val state: StateFlow<LeaveUiState> = MutableStateFlow(LeaveUiState()).asStateFlow()
    override fun load() = Unit
    override fun loadMore() = Unit
    override fun resetSubmission() = Unit
    override fun requestLeave(input: LeaveRequestInput) = Unit
    override fun replaceCertificate(leaveId: Long, attachments: List<UploadFile>) = Unit
    override suspend fun download(attachment: HrAttachment, target: File): AttachmentDownload = AttachmentDownload.Unreachable
}

val LocalLeave = staticCompositionLocalOf<LeaveController> { NoLeave }
