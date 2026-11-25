package io.aiku.droid.ui.states

import android.content.ClipData
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.ClipEntry
import androidx.compose.ui.platform.LocalClipboard
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import io.aiku.droid.R
import io.aiku.droid.data.api.SupportReferences
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.launch

private const val DOCS_BASE_URL = "https://aiku.io/docs/"
private val DOCS_LANGUAGES = setOf("es", "sk")

enum class ErrorCode(val code: String) {
    Unreachable("AIKU-APP-001"),
    ServerError("AIKU-APP-002"),
    Unavailable("AIKU-APP-003"),
    SessionExpired("AIKU-APP-004"),
    NoAccess("AIKU-APP-005"),
    NotFound("AIKU-APP-006"),
    UpdateRequired("AIKU-APP-007"),
    CameraBlocked("AIKU-APP-008"),
    BarcodeUnknown("AIKU-APP-009"),
    ClockingQrInvalid("AIKU-CLK-001"),
    ClockingLocationNeeded("AIKU-CLK-002"),
    ClockingNoPin("AIKU-CLK-003"),
    SyncOverRequired("AIKU-SYNC-001"),
    SyncLocationEmpty("AIKU-SYNC-002"),
    SyncNotEnoughStock("AIKU-SYNC-003"),
    SyncOtherChange("AIKU-SYNC-004"),
    SyncNoteCancelled("AIKU-SYNC-005"),
    SyncDeliveryClosed("AIKU-SYNC-006"),
    SyncReturnClosed("AIKU-SYNC-007"),
    SyncWaitingCleared("AIKU-SYNC-008"),
    SyncInPickingSession("AIKU-SYNC-009"),
    SyncNotHandler("AIKU-SYNC-010"),
    SyncOverPacked("AIKU-SYNC-011"),
    SyncOverChecked("AIKU-SYNC-012"),
    SyncOverExpected("AIKU-SYNC-013"),
    SyncSettingOff("AIKU-SYNC-014"),
    SyncWrongState("AIKU-SYNC-015"),
    SyncNoRight("AIKU-SYNC-016"),
    SyncNotFound("AIKU-SYNC-017"),
    SyncInvalid("AIKU-SYNC-018"),
    ;

    val isServerSide: Boolean get() = this == ServerError || this == Unavailable

    fun docsUrl(language: String): String = DOCS_BASE_URL + code.lowercase() + if (language in DOCS_LANGUAGES) "-$language" else ""

    companion object {
        private val UNAVAILABLE_STATUSES = setOf(502, 503, 504)

        private val SYNC_REASONS = mapOf(
            "over_required" to SyncOverRequired,
            "location_empty" to SyncLocationEmpty,
            "not_enough_stock" to SyncNotEnoughStock,
            "other_change" to SyncOtherChange,
            "note_cancelled" to SyncNoteCancelled,
            "delivery_closed" to SyncDeliveryClosed,
            "return_closed" to SyncReturnClosed,
            "waiting_cleared" to SyncWaitingCleared,
            "in_picking_session" to SyncInPickingSession,
            "not_handler" to SyncNotHandler,
            "over_packed" to SyncOverPacked,
            "over_checked" to SyncOverChecked,
            "over_expected" to SyncOverExpected,
            "setting_off" to SyncSettingOff,
            "wrong_state" to SyncWrongState,
            "no_right" to SyncNoRight,
            "not_found" to SyncNotFound,
            "invalid" to SyncInvalid,
        )

        fun forServerStatus(statusCode: Int?): ErrorCode = if (statusCode in UNAVAILABLE_STATUSES) Unavailable else ServerError

        fun forSyncReason(reason: String?): ErrorCode? = SYNC_REASONS[reason]
    }
}

@Composable
fun ErrorHelp(
    code: ErrorCode,
    modifier: Modifier = Modifier,
    centered: Boolean = false,
    showSupportReference: Boolean = code.isServerSide,
) {
    val colors = LocalAikuColors.current
    var isOpen by rememberSaveable(code) { mutableStateOf(false) }
    val latestReference by SupportReferences.latest.collectAsState()
    val supportReference = latestReference?.takeIf { showSupportReference && SupportReferences.recent() == it }

    Column(
        horizontalAlignment = if (centered) Alignment.CenterHorizontally else Alignment.Start,
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(text = code.code, fontSize = 12.sp, fontFamily = FontFamily.Monospace, color = colors.textTertiary)
            Text(text = "·", fontSize = 12.sp, color = colors.textQuaternary)
            Text(
                text = stringResource(R.string.errors_what_does_this_mean),
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.link,
                modifier = Modifier
                    .clip(ChipShape)
                    .clickable(role = Role.Button) { isOpen = true }
                    .padding(vertical = 6.dp),
            )
        }
        if (supportReference != null) {
            SupportReferenceLine(reference = supportReference.reference, centered = centered)
        }
    }

    if (isOpen) {
        ErrorDocsViewer(code = code, onDismiss = { isOpen = false })
    }
}

@Composable
private fun SupportReferenceLine(reference: String, centered: Boolean) {
    val colors = LocalAikuColors.current
    val clipboard = LocalClipboard.current
    val scope = rememberCoroutineScope()
    val clipLabel = stringResource(R.string.errors_support_reference_label)
    var isCopied by remember(reference) { mutableStateOf(false) }

    Text(
        text = if (isCopied) stringResource(R.string.errors_support_reference_copied) else stringResource(R.string.errors_support_reference, reference),
        fontSize = 11.sp,
        fontFamily = FontFamily.Monospace,
        color = if (isCopied) colors.success else colors.textQuaternary,
        textAlign = if (centered) TextAlign.Center else TextAlign.Start,
        modifier = Modifier
            .clip(ChipShape)
            .clickable(role = Role.Button) {
                scope.launch {
                    clipboard.setClipEntry(ClipEntry(ClipData.newPlainText(clipLabel, reference)))
                    isCopied = true
                }
            }
            .padding(vertical = 4.dp),
    )
}

@Composable
private fun ErrorDocsViewer(code: ErrorCode, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val uriHandler = LocalUriHandler.current
    val language = LocalConfiguration.current.locales[0].language
    val url = remember(code, language) { code.docsUrl(language) }
    var hasFailed by remember(url) { mutableStateOf(false) }
    val openInBrowser = { runCatching { uriHandler.openUri(url) } }

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(modifier = Modifier.fillMaxSize().background(colors.background)) {
            AppHeader(
                title = code.code,
                onBack = onDismiss,
                actions = {
                    Text(
                        text = stringResource(R.string.errors_open_in_browser),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = colors.onNav,
                        modifier = Modifier
                            .clip(ChipShape)
                            .clickable(role = Role.Button) { openInBrowser() }
                            .padding(horizontal = 8.dp, vertical = 8.dp),
                    )
                },
            )
            if (hasFailed) {
                InlineAlert(
                    title = stringResource(R.string.errors_page_failed_title),
                    body = stringResource(R.string.errors_page_failed_body),
                    tone = AlertTone.Warn,
                    actionLabel = stringResource(R.string.errors_open_in_browser),
                    onAction = { openInBrowser() },
                    modifier = Modifier.padding(16.dp),
                )
            } else {
                AndroidView(
                    factory = { context ->
                        WebView(context).apply {
                            webViewClient = object : WebViewClient() {
                                override fun onReceivedError(view: WebView?, request: WebResourceRequest?, error: WebResourceError?) {
                                    if (request?.isForMainFrame == true) {
                                        hasFailed = true
                                    }
                                }
                            }
                            loadUrl(url)
                        }
                    },
                    modifier = Modifier.fillMaxWidth().weight(1f),
                )
            }
        }
    }
}
