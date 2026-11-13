package io.aiku.droid.ui.scan

import androidx.compose.runtime.Composable
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.states.NotFoundScreen

@Composable
fun ScanResultScreen(code: String) {
    val navigator = LocalAppNavigator.current

    NotFoundScreen(barcode = code, onScanAgain = { navigator.switchSection(ScanRoute) })
}
