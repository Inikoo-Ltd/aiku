package io.aiku.droid.ui.states

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.EmptyTone
import io.aiku.droid.ui.components.fakeData

@Composable
fun NotDesignedScreen(title: String) {
    AppScreen(header = { AppHeader(title = title) }) {
        EmptyState(
            icon = R.drawable.ic_ui_document,
            title = stringResource(R.string.states_not_designed_title),
            body = stringResource(R.string.states_not_designed_body, title),
            tone = EmptyTone.Info,
            modifier = Modifier.fillMaxWidth().fakeData(),
        )
    }
}
