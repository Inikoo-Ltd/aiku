package io.aiku.droid.ui.states

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.ProblemCategory
import io.aiku.droid.data.api.ProblemReport
import io.aiku.droid.data.api.ProblemReportResult
import io.aiku.droid.data.api.SupportApi
import io.aiku.droid.data.api.SupportReferences
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.ui.clocking.HrAttachmentPicker
import io.aiku.droid.ui.clocking.HrFieldError
import io.aiku.droid.ui.clocking.PickedAttachment
import io.aiku.droid.ui.clocking.toUploadFiles
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.launch

enum class ReportProblemCategory(@StringRes val label: Int, val category: ProblemCategory) {
    UnknownBarcode(R.string.states_report_unknown_barcode_category, ProblemCategory.UnknownBarcode),
    WrongStock(R.string.states_report_wrong_stock, ProblemCategory.WrongStock),
    DataProblem(R.string.states_report_data_problem, ProblemCategory.DataProblem),
    AppError(R.string.states_report_app_error, ProblemCategory.AppError),
    Other(R.string.states_report_other, ProblemCategory.Other),
}

class SupportServices(val api: SupportApi, val sessions: SessionRepository)

val LocalSupportServices = staticCompositionLocalOf<SupportServices?> { null }

@Composable
fun ReportProblemSheet(
    onDismiss: () -> Unit,
    initialCategory: ReportProblemCategory = ReportProblemCategory.Other,
    errorCode: ErrorCode? = null,
    screen: String? = null,
    lastScan: String? = null,
) {
    val toasts = LocalToasts.current
    val context = LocalContext.current
    val services = LocalSupportServices.current
    val warehouseId = LocalWarehouse.current.id.takeIf { it > 0 }
    val scope = rememberCoroutineScope()
    val supportReference = remember(errorCode) { SupportReferences.recent()?.reference?.takeIf { errorCode?.isServerSide == true } }
    var category by rememberSaveable { mutableStateOf(initialCategory) }
    var details by rememberSaveable { mutableStateOf("") }
    var files by remember { mutableStateOf(emptyList<PickedAttachment>()) }
    var isSending by remember { mutableStateOf(false) }
    var problem by remember { mutableStateOf<String?>(null) }
    val missingDetails = stringResource(R.string.states_report_details_required)
    val offlineMessage = stringResource(R.string.states_report_offline)
    val tooManyMessage = stringResource(R.string.states_report_too_many)
    val signedOutMessage = stringResource(R.string.states_report_signed_out)
    val failedMessage = stringResource(R.string.states_report_failed)
    val sentMessage = stringResource(R.string.states_report_sent_as)

    val send: () -> Unit = send@{
        if (details.isBlank()) {
            problem = missingDetails
            return@send
        }
        val token = services?.sessions?.session?.value?.token
        if (services == null || token == null) {
            problem = signedOutMessage
            return@send
        }
        isSending = true
        problem = null
        scope.launch {
            val report = ProblemReport(
                category = category.category,
                details = details,
                screen = screen,
                errorCode = errorCode?.code,
                supportReference = supportReference,
                lastScan = lastScan,
                warehouseId = warehouseId,
                images = files.toUploadFiles(context.contentResolver),
            )
            when (val result = services.api.reportProblem(token, report)) {
                is ProblemReportResult.Sent -> {
                    onDismiss()
                    toasts.show(sentMessage.format(result.reference))
                }
                is ProblemReportResult.Invalid -> problem = result.message.ifBlank { failedMessage }
                ProblemReportResult.TooMany -> problem = tooManyMessage
                ProblemReportResult.Unreachable -> problem = offlineMessage
                ProblemReportResult.SessionExpired -> problem = signedOutMessage
                is ProblemReportResult.ServerError -> problem = failedMessage
            }
            isSending = false
        }
    }

    AikuBottomSheet(
        title = stringResource(R.string.states_report_title),
        subtitle = stringResource(R.string.states_report_subtitle_ticket),
        onDismiss = onDismiss,
    ) {
        ReportProblemContent(
            category = category,
            onCategoryChange = { category = it },
            details = details,
            onDetailsChange = {
                details = it
                problem = null
            },
            files = files,
            onFilesChange = { files = it },
            added = listOfNotNull(errorCode?.code, supportReference).joinToString(" · ").takeIf { it.isNotEmpty() },
            problem = problem,
            isSending = isSending,
            onSend = send,
            onCancel = onDismiss,
        )
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun ReportProblemContent(
    category: ReportProblemCategory,
    onCategoryChange: (ReportProblemCategory) -> Unit,
    details: String,
    onDetailsChange: (String) -> Unit,
    files: List<PickedAttachment>,
    onFilesChange: (List<PickedAttachment>) -> Unit,
    added: String?,
    problem: String?,
    isSending: Boolean,
    onSend: () -> Unit,
    onCancel: () -> Unit,
) {
    val colors = LocalAikuColors.current

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth().selectableGroup(),
    ) {
        ReportProblemCategory.entries.forEach { option ->
            ReportCategoryChip(
                label = stringResource(option.label),
                isSelected = option == category,
                onClick = { onCategoryChange(option) },
            )
        }
    }
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            text = stringResource(R.string.states_report_what_happened),
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            color = colors.textSecondary,
        )
        ReportDetailsField(value = details, onValueChange = onDetailsChange)
    }
    HrAttachmentPicker(
        label = stringResource(R.string.states_report_photos),
        addLabel = stringResource(R.string.states_report_add_photo),
        files = files,
        onFilesChange = onFilesChange,
    )
    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, ControlShape)
            .padding(12.dp),
    ) {
        Text(
            text = stringResource(R.string.states_report_added_automatically),
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            color = colors.textSecondary,
        )
        Text(
            text = stringResource(R.string.states_report_added_details_ticket),
            fontSize = 12.sp,
            fontWeight = FontWeight.Normal,
            color = colors.textTertiary,
        )
        if (added != null) {
            Text(text = added, fontSize = 12.sp, fontFamily = FontFamily.Monospace, color = colors.textTertiary)
        }
    }
    problem?.let { HrFieldError(message = it) }
    AikuButton(
        text = stringResource(R.string.states_report_send),
        onClick = onSend,
        isLoading = isSending,
        modifier = Modifier.fillMaxWidth(),
        icon = R.drawable.ic_ui_next,
        height = 52.dp,
    )
    AikuButton(
        text = stringResource(R.string.dialog_cancel),
        onClick = onCancel,
        modifier = Modifier.fillMaxWidth(),
        style = ButtonStyle.Outline,
        height = 44.dp,
    )
}

@Composable
private fun ReportCategoryChip(label: String, isSelected: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .heightIn(min = 40.dp)
            .clip(CircleShape)
            .background(if (isSelected) colors.soft else colors.surface)
            .border(
                width = if (isSelected) 2.dp else 1.dp,
                color = if (isSelected) colors.primary else colors.borderStrong,
                shape = CircleShape,
            )
            .selectable(selected = isSelected, role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp),
    ) {
        Text(
            text = label,
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (isSelected) colors.link else colors.textSecondary,
        )
    }
}

@Composable
private fun ReportDetailsField(value: String, onValueChange: (String) -> Unit) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(6.dp)
    val detailsInteraction = remember { MutableInteractionSource() }
    val isDetailsFocused by detailsInteraction.collectIsFocusedAsState()
    val detailsFocusProgress = animateFocusProgress(isDetailsFocused)

    BasicTextField(
        value = value,
        onValueChange = onValueChange,
        textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, color = colors.text),
        cursorBrush = SolidColor(colors.primary),
        keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
        maxLines = 5,
        interactionSource = detailsInteraction,
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 96.dp)
            .focusHalo(progress = detailsFocusProgress, color = colors.focus, cornerRadius = 6.dp)
            .clip(shape)
            .background(colors.surface)
            .border(1.dp, lerp(colors.borderStrong, colors.focus, detailsFocusProgress), shape)
            .padding(horizontal = 12.dp, vertical = 13.dp),
    )
}
