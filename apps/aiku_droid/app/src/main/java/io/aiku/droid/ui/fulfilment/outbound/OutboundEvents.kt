package io.aiku.droid.ui.fulfilment.outbound

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PalletReturnScanStatus
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.goodsout.GoodsOutProblem
import io.aiku.droid.ui.goodsout.problem
import io.aiku.droid.ui.goodsout.problemText

sealed interface OutboundEvent {
    data class Done(val action: Any, val message: String?, val isNothingChanged: Boolean) : OutboundEvent
    data class Refused(val action: Any?, val message: String, val problem: GoodsOutProblem) : OutboundEvent
    data class Scanned(val code: String, val status: String, val message: String?, val lineId: Long?) : OutboundEvent
    data class LineFound(val lineId: Long) : OutboundEvent
    data class PalletFound(val palletId: Long, val reference: String) : OutboundEvent
    data class NotOnReturn(val code: String) : OutboundEvent
}

internal fun GoodsOutResult<*>.outboundRefusal(action: Any?): OutboundEvent.Refused {
    val message = when (this) {
        is GoodsOutResult.Forbidden -> message
        is GoodsOutResult.NotFound -> message
        is GoodsOutResult.Invalid -> message
        else -> ""
    }
    return OutboundEvent.Refused(action = action, message = message, problem = problem())
}

internal val GoodsOutResult<*>.isUnsure: Boolean get() = this == GoodsOutResult.Unreachable || this is GoodsOutResult.ServerError

internal fun scanToastKind(status: String): ToastKind = when (status) {
    PalletReturnScanStatus.PICKED -> ToastKind.Success
    PalletReturnScanStatus.ALREADY_PICKED -> ToastKind.Info
    else -> ToastKind.Warning
}

@Composable
internal fun OutboundEvents(
    event: OutboundEvent?,
    onHandled: () -> Unit,
    onSheetProblem: ((String) -> Unit)? = null,
    doneText: (OutboundEvent.Done) -> String? = { null },
    otherText: (OutboundEvent) -> String? = { null },
    onDone: (OutboundEvent.Done) -> Unit = {},
    onLineFound: (Long) -> Unit = {},
) {
    val toasts = LocalToasts.current
    val problemTexts = GoodsOutProblem.entries.associateWith { problemText(it) }

    LaunchedEffect(event) {
        when (event) {
            is OutboundEvent.Done -> {
                (event.message ?: doneText(event))?.let { toasts.show(it, if (event.isNothingChanged) ToastKind.Info else ToastKind.Success) }
                onDone(event)
            }
            is OutboundEvent.Refused -> {
                val text = event.message.ifBlank { problemTexts.getValue(event.problem) }
                if (onSheetProblem != null) onSheetProblem(text) else toasts.show(text, ToastKind.Error)
            }
            is OutboundEvent.Scanned -> (event.message ?: otherText(event))?.let { toasts.show(it, scanToastKind(event.status)) }
            is OutboundEvent.LineFound -> onLineFound(event.lineId)
            is OutboundEvent.PalletFound -> otherText(event)?.let { toasts.show(it, ToastKind.Info) }
            is OutboundEvent.NotOnReturn -> otherText(event)?.let { toasts.show(it, ToastKind.Warning) }
            null -> return@LaunchedEffect
        }
        onHandled()
    }
}
