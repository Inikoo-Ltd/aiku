package io.aiku.droid.ui.clocking

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Leave
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind

private const val FIELD_ATTACHMENTS = "attachments"

@Composable
internal fun LeaveCertificateSheet(leave: Leave, onDismiss: () -> Unit) {
    val controller = LocalLeave.current
    val state by controller.state.collectAsStateWithLifecycle()
    val toasts = LocalToasts.current
    val contentResolver = LocalContext.current.contentResolver
    val savedMessage = stringResource(R.string.clocking_leave_certificate_saved)
    val missingMessage = stringResource(R.string.clocking_leave_certificate_missing)
    var files by remember { mutableStateOf(emptyList<PickedAttachment>()) }
    var isMissingFiles by remember { mutableStateOf(false) }
    val submission = state.submission

    LaunchedEffect(Unit) { controller.resetSubmission() }
    LaunchedEffect(submission) {
        if (submission == HrSubmission.Saved) {
            toasts.show(savedMessage, ToastKind.Success)
            controller.resetSubmission()
            onDismiss()
        }
    }

    AikuBottomSheet(
        title = stringResource(R.string.clocking_leave_certificate_title),
        subtitle = stringResource(R.string.clocking_leave_certificate_subtitle),
        context = hrDateRange(leave.startDate, leave.endDate),
        onDismiss = onDismiss,
    ) {
        if (leave.attachments.isNotEmpty()) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                HrFieldLabel(text = stringResource(R.string.clocking_leave_certificate_current))
                HrAttachmentList(attachments = leave.attachments, source = controller)
            }
        }
        HrAttachmentPicker(
            label = stringResource(R.string.clocking_leave_certificate),
            addLabel = stringResource(R.string.clocking_leave_upload_certificate),
            files = files,
            onFilesChange = {
                files = it
                isMissingFiles = false
            },
            error = submission.errorFor(FIELD_ATTACHMENTS) ?: missingMessage.takeIf { isMissingFiles },
        )
        HrSubmissionProblem(submission = submission, shownFields = listOf(FIELD_ATTACHMENTS))
        HrFormButtons(
            submitLabel = stringResource(R.string.clocking_leave_certificate_save),
            isSending = submission == HrSubmission.Sending,
            onSubmit = {
                if (files.isEmpty()) {
                    isMissingFiles = true
                } else {
                    controller.replaceCertificate(leave.id, files.toUploadFiles(contentResolver))
                }
            },
            onCancel = onDismiss,
        )
    }
}
