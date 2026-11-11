package io.aiku.droid.ui.clocking

import android.content.ActivityNotFoundException
import android.content.ContentResolver
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.provider.OpenableColumns
import android.webkit.MimeTypeMap
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.FileProvider
import io.aiku.droid.R
import io.aiku.droid.data.api.AttachmentDownload
import io.aiku.droid.data.api.HrAttachment
import io.aiku.droid.data.api.UploadFile
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.launch
import java.io.File
import java.io.FileNotFoundException

internal const val HR_MAX_ATTACHMENTS = 3
private const val MAX_ATTACHMENT_BYTES = 5L * 1024 * 1024
private const val ATTACHMENTS_CACHE_DIRECTORY = "attachments"
private const val FILE_PROVIDER_SUFFIX = ".attachments"
private const val FALLBACK_FILE_NAME = "attachment"
private const val FALLBACK_MIME_TYPE = "application/octet-stream"
private const val ANY_MIME_TYPE = "*/*"
private val AllowedAttachmentTypes = arrayOf("application/pdf", "image/jpeg", "image/png")
private val UnsafeFileNameCharacters = Regex("[^A-Za-z0-9._-]")

@Immutable
internal data class PickedAttachment(val uri: Uri, val name: String, val mimeType: String, val sizeBytes: Long?)

internal fun List<PickedAttachment>.toUploadFiles(contentResolver: ContentResolver): List<UploadFile> = map { picked ->
    UploadFile(
        name = picked.name,
        mimeType = picked.mimeType,
        readBytes = { contentResolver.openInputStream(picked.uri)?.use { it.readBytes() } ?: throw FileNotFoundException(picked.name) },
    )
}

private fun describeAttachment(contentResolver: ContentResolver, uri: Uri): PickedAttachment {
    var name: String? = null
    var size: Long? = null
    runCatching {
        contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE), null, null, null)?.use { cursor ->
            if (cursor.moveToFirst()) {
                val nameColumn = cursor.getColumnIndexOrThrow(OpenableColumns.DISPLAY_NAME)
                val sizeColumn = cursor.getColumnIndexOrThrow(OpenableColumns.SIZE)
                name = cursor.getString(nameColumn)
                size = if (cursor.isNull(sizeColumn)) null else cursor.getLong(sizeColumn)
            }
        }
    }
    val fileName = name?.takeIf { it.isNotBlank() } ?: uri.lastPathSegment ?: FALLBACK_FILE_NAME
    val mimeType = contentResolver.getType(uri) ?: mimeTypeOf(fileName) ?: FALLBACK_MIME_TYPE
    return PickedAttachment(uri = uri, name = fileName, mimeType = mimeType, sizeBytes = size)
}

private fun mimeTypeOf(fileName: String): String? =
    MimeTypeMap.getSingleton().getMimeTypeFromExtension(fileName.substringAfterLast('.', missingDelimiterValue = "").lowercase())

private fun cachedAttachmentFile(context: Context, attachment: HrAttachment): File {
    val safeName = attachment.name.replace(UnsafeFileNameCharacters, "_").trim('.').ifBlank { FALLBACK_FILE_NAME }
    return File(File(File(context.cacheDir, ATTACHMENTS_CACHE_DIRECTORY), attachment.id.toString()), safeName)
}

private fun openDownloadedAttachment(context: Context, download: AttachmentDownload.Saved): Boolean {
    val uri = FileProvider.getUriForFile(context, context.packageName + FILE_PROVIDER_SUFFIX, download.file)
    val mimeType = download.mimeType?.takeUnless { it == FALLBACK_MIME_TYPE } ?: mimeTypeOf(download.file.name) ?: ANY_MIME_TYPE
    val intent = Intent(Intent.ACTION_VIEW)
        .setDataAndType(uri, mimeType)
        .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    return try {
        context.startActivity(intent)
        true
    } catch (exception: ActivityNotFoundException) {
        false
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun HrAttachmentList(attachments: List<HrAttachment>, source: AttachmentSource, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val toasts = LocalToasts.current
    val scope = rememberCoroutineScope()
    var openingId by remember { mutableStateOf<Long?>(null) }
    val noAppMessage = stringResource(R.string.clocking_attachment_no_app)
    val offlineMessage = stringResource(R.string.clocking_attachment_offline)
    val failedMessage = stringResource(R.string.clocking_attachment_failed)
    val refusedMessage = stringResource(R.string.clocking_attachment_refused)

    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp), modifier = modifier.fillMaxWidth()) {
        attachments.forEach { attachment ->
            HrAttachmentChip(
                name = attachment.name.ifBlank { FALLBACK_FILE_NAME },
                isOpening = openingId == attachment.id,
                onClick = {
                    if (openingId == null) {
                        openingId = attachment.id
                        scope.launch {
                            val download = source.download(attachment, cachedAttachmentFile(context, attachment))
                            openingId = null
                            when (download) {
                                is AttachmentDownload.Saved -> if (!openDownloadedAttachment(context, download)) {
                                    toasts.show(noAppMessage, ToastKind.Error)
                                }
                                is AttachmentDownload.Refused -> toasts.show(download.message.ifBlank { refusedMessage }, ToastKind.Error)
                                AttachmentDownload.Unreachable -> toasts.show(offlineMessage, ToastKind.Offline)
                                is AttachmentDownload.ServerError -> toasts.show(failedMessage, ToastKind.Error)
                                AttachmentDownload.SessionExpired -> Unit
                            }
                        }
                    }
                },
            )
        }
    }
}

@Composable
private fun HrAttachmentChip(name: String, isOpening: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val openLabel = stringResource(R.string.clocking_attachment_open, name)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .widthIn(max = 280.dp)
            .heightIn(min = 44.dp)
            .clip(ControlShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, ControlShape)
            .clickable(role = Role.Button, onClickLabel = openLabel, onClick = onClick)
            .padding(horizontal = 10.dp),
    ) {
        if (isOpening) {
            CircularProgressIndicator(color = colors.link, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
        } else {
            Icon(painter = painterResource(R.drawable.ic_ui_document), contentDescription = null, tint = colors.link, modifier = Modifier.size(16.dp))
        }
        Text(text = name, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.link, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
internal fun HrAttachmentPicker(
    label: String,
    addLabel: String,
    files: List<PickedAttachment>,
    onFilesChange: (List<PickedAttachment>) -> Unit,
    modifier: Modifier = Modifier,
    error: String? = null,
) {
    val context = LocalContext.current
    var pickProblem by remember { mutableStateOf<String?>(null) }
    val tooManyMessage = stringResource(R.string.clocking_attachments_too_many)
    val tooBigMessage = stringResource(R.string.clocking_attachments_too_big)
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.OpenMultipleDocuments()) { uris ->
        val picked = uris.map { describeAttachment(context.contentResolver, it) }.filterNot { new -> files.any { it.uri == new.uri } }
        val smallEnough = picked.filter { (it.sizeBytes ?: 0L) <= MAX_ATTACHMENT_BYTES }
        val room = (HR_MAX_ATTACHMENTS - files.size).coerceAtLeast(0)
        pickProblem = when {
            smallEnough.size < picked.size -> tooBigMessage
            smallEnough.size > room -> tooManyMessage
            else -> null
        }
        onFilesChange(files + smallEnough.take(room))
    }

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        HrFieldLabel(text = label)
        files.forEach { file ->
            HrPickedFileRow(
                file = file,
                onRemove = {
                    pickProblem = null
                    onFilesChange(files - file)
                },
            )
        }
        if (files.size < HR_MAX_ATTACHMENTS) {
            AikuButton(
                text = addLabel,
                onClick = { launcher.launch(AllowedAttachmentTypes) },
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_ui_document,
                height = 44.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
        HrFieldHelper(text = stringResource(R.string.clocking_attachments_hint))
        (error ?: pickProblem)?.let { HrFieldError(message = it) }
    }
}

@Composable
private fun HrPickedFileRow(file: PickedAttachment, onRemove: () -> Unit) {
    val colors = LocalAikuColors.current
    val removeLabel = stringResource(R.string.clocking_attachments_remove, file.name)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, ControlShape)
            .padding(start = 12.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_document), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(18.dp))
        Text(
            text = file.name,
            fontSize = 14.sp,
            color = colors.text,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(44.dp)
                .clickable(role = Role.Button, onClickLabel = removeLabel, onClick = onRemove)
                .semantics { contentDescription = removeLabel },
        ) {
            Icon(painter = painterResource(R.drawable.ic_ui_close), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(18.dp))
        }
    }
}
