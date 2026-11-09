package io.aiku.droid.ui

import androidx.annotation.StringRes
import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource

sealed interface UiMessage {
    data class FromServer(val text: String) : UiMessage

    data class FromResource(@StringRes val id: Int, val arguments: List<Any> = emptyList()) : UiMessage
}

@Composable
fun UiMessage.asText(): String = when (this) {
    is UiMessage.FromServer -> text
    is UiMessage.FromResource -> stringResource(id, *arguments.toTypedArray())
}
