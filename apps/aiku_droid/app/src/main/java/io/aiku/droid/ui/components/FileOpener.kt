package io.aiku.droid.ui.components

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.webkit.MimeTypeMap
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.core.content.FileProvider
import androidx.core.net.toUri
import io.aiku.droid.R
import io.aiku.droid.data.api.AttachmentDownload
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.launch
import java.io.File

private const val DOWNLOADS_CACHE_DIRECTORY = "attachments"
private const val FILE_PROVIDER_SUFFIX = ".attachments"
private const val FALLBACK_FILE_NAME = "file"
private const val FALLBACK_MIME_TYPE = "application/octet-stream"
private const val ANY_MIME_TYPE = "*/*"
private val UnsafeFileNameCharacters = Regex("[^A-Za-z0-9._-]")

internal fun mimeTypeOf(fileName: String): String? =
    MimeTypeMap.getSingleton().getMimeTypeFromExtension(fileName.substringAfterLast('.', missingDelimiterValue = "").lowercase())

private fun safeFileName(name: String): String = name.replace(UnsafeFileNameCharacters, "_").trim('.').ifBlank { FALLBACK_FILE_NAME }

private fun cachedDownloadFile(context: Context, folder: String, fileName: String): File =
    File(File(File(context.cacheDir, DOWNLOADS_CACHE_DIRECTORY), safeFileName(folder)), safeFileName(fileName))

private fun Context.viewDownloadedFile(download: AttachmentDownload.Saved): Boolean {
    val uri = FileProvider.getUriForFile(this, packageName + FILE_PROVIDER_SUFFIX, download.file)
    val mimeType = download.mimeType?.takeUnless { it == FALLBACK_MIME_TYPE } ?: mimeTypeOf(download.file.name) ?: ANY_MIME_TYPE
    val intent = Intent(Intent.ACTION_VIEW)
        .setDataAndType(uri, mimeType)
        .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    return startViewer(intent)
}

private fun Context.startViewer(intent: Intent): Boolean = try {
    startActivity(intent)
    true
} catch (exception: ActivityNotFoundException) {
    false
}

@Immutable
internal data class FileOpenerMessages(val noApp: String, val offline: String, val failed: String, val refused: String)

@Stable
class FileOpener internal constructor(
    private val context: Context,
    private val toasts: ToastState,
    private val scope: CoroutineScope,
    private val messages: FileOpenerMessages,
) {
    var openingKey by mutableStateOf<String?>(null)
        private set

    fun open(key: String, folder: String, fileName: String, download: suspend (File) -> AttachmentDownload, onDownloaded: () -> Unit = {}) {
        if (openingKey != null) {
            return
        }
        openingKey = key
        scope.launch {
            val result = download(cachedDownloadFile(context, folder, fileName))
            openingKey = null
            when (result) {
                is AttachmentDownload.Saved -> {
                    onDownloaded()
                    if (!context.viewDownloadedFile(result)) {
                        toasts.show(messages.noApp, ToastKind.Error)
                    }
                }
                is AttachmentDownload.Refused -> toasts.show(result.message.ifBlank { messages.refused }, ToastKind.Error)
                AttachmentDownload.Unreachable -> toasts.show(messages.offline, ToastKind.Offline)
                is AttachmentDownload.ServerError -> toasts.show(messages.failed, ToastKind.Error)
                AttachmentDownload.SessionExpired -> Unit
            }
        }
    }

    fun openLink(url: String) {
        if (!context.startViewer(Intent(Intent.ACTION_VIEW, url.toUri()))) {
            toasts.show(messages.noApp, ToastKind.Error)
        }
    }
}

@Composable
fun rememberFileOpener(): FileOpener {
    val context = LocalContext.current
    val toasts = LocalToasts.current
    val scope = rememberCoroutineScope()
    val messages = FileOpenerMessages(
        noApp = stringResource(R.string.file_open_no_app),
        offline = stringResource(R.string.file_open_offline),
        failed = stringResource(R.string.file_open_failed),
        refused = stringResource(R.string.file_open_refused),
    )

    return remember(context, toasts, scope, messages) { FileOpener(context, toasts, scope, messages) }
}
