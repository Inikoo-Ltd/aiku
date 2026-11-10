package io.aiku.droid.ui.states

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun NoTasksScreen() {
    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.module_goods_out),
                kicker = warehouseKicker(),
            )
        },
        activeTab = MainTab.MyWork,
    ) {
        NoTasksState()
    }
}
