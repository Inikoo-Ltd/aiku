package io.aiku.droid.ui.scan

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.ui.navigation.LocalAppearanceController
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.aikuColors

internal fun WarehouseAbilities.canResolveScans(): Boolean = viewInventory || viewLocations || viewGoodsIn || viewGoodsOut

@Composable
internal fun rememberScanSolidColors(): AikuColors {
    val theme = LocalAppearanceController.current.appearance.theme
    return remember(theme) { aikuColors(theme, isDark = false) }
}

@Composable
internal fun rememberScanDarkColors(): AikuColors {
    val theme = LocalAppearanceController.current.appearance.theme
    return remember(theme) { aikuColors(theme, isDark = true) }
}
