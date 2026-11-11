package io.aiku.droid.ui.clocking

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.HrAttachment
import io.aiku.droid.data.api.HrPagination
import io.aiku.droid.data.api.HrWriteResult
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import java.io.File

internal const val HR_PAGE_SIZE = 20

data class PagedList<T>(
    val items: List<T> = emptyList(),
    val page: Int = 0,
    val lastPage: Int = 0,
    val loadingPage: Int? = null,
    val problem: ClockingProblem? = null,
    val failedPage: Int? = null,
) {
    val hasLoaded: Boolean get() = page > 0
    val hasMore: Boolean get() = hasLoaded && page < lastPage
    val canLoadMore: Boolean get() = hasMore && loadingPage == null
    val canLoadMoreOnScroll: Boolean get() = canLoadMore && problem == null
    val isLoadingMore: Boolean get() = (loadingPage ?: 0) > 1
    val firstPageProblem: ClockingProblem? get() = problem?.takeIf { failedPage == 1 }
    val nextPageProblem: ClockingProblem? get() = problem?.takeIf { (failedPage ?: 0) > 1 }

    fun loading(page: Int): PagedList<T> = copy(loadingPage = page, problem = null, failedPage = null)

    fun loaded(pageItems: List<T>, pagination: HrPagination?, idOf: (T) -> Long): PagedList<T> {
        val loadedPage = pagination?.currentPage ?: 1
        val merged = if (loadedPage <= 1) pageItems else (items + pageItems).distinctBy(idOf)
        return PagedList(items = merged, page = loadedPage, lastPage = pagination?.lastPage ?: loadedPage)
    }

    fun failed(page: Int, problem: ClockingProblem): PagedList<T> = copy(loadingPage = null, problem = problem, failedPage = page)
}

sealed interface HrSubmission {
    data object Idle : HrSubmission
    data object Sending : HrSubmission
    data class Rejected(val fieldErrors: Map<String, String>, val message: String) : HrSubmission
    data class Failed(val problem: ClockingProblem) : HrSubmission
    data object Saved : HrSubmission
}

interface AttachmentSource {
    suspend fun download(attachment: HrAttachment, target: File): AttachmentDownload
}

internal fun clockingProblemOf(result: ClockingResult<*>): ClockingProblem =
    if (result == ClockingResult.Unreachable) ClockingProblem.Offline else ClockingProblem.ServerError

internal fun HrWriteResult<*>.toSubmission(): HrSubmission = when (this) {
    is HrWriteResult.Saved -> HrSubmission.Saved
    is HrWriteResult.Invalid -> HrSubmission.Rejected(fieldErrors = fieldErrors, message = message)
    is HrWriteResult.Refused -> HrSubmission.Rejected(fieldErrors = emptyMap(), message = message)
    HrWriteResult.Unreachable -> HrSubmission.Failed(ClockingProblem.Offline)
    is HrWriteResult.ServerError -> HrSubmission.Failed(ClockingProblem.ServerError)
    HrWriteResult.SessionExpired -> HrSubmission.Idle
}

internal fun ViewModel.onEachSessionToken(sessions: SessionRepository, onToken: (String?) -> Unit) {
    viewModelScope.launch {
        sessions.session
            .map { it?.token }
            .distinctUntilChanged()
            .collect { token -> onToken(token) }
    }
}
