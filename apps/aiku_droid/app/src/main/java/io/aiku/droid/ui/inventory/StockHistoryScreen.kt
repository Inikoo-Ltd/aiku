package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun StockHistoryScreen() {
    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        StockHistoryContent()
    }
}

@Composable
private fun StockHistoryContent() {
    var filter by remember { mutableStateOf(MovementFilter.All) }
    val movements = if (filter == MovementFilter.All) sampleMovements else sampleMovements.filter { it.type.filter == filter }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.StockHistory) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        FilterChips(
            options = MovementFilter.entries.map { FilterOption(label = stringResource(it.label)) },
            selectedIndex = filter.ordinal,
            onSelect = { filter = MovementFilter.entries[it] },
        )
        ListGroup(modifier = Modifier.fakeData()) {
            movements.forEachIndexed { index, movement ->
                if (index > 0) RowDivider()
                ItemRow(
                    code = movement.skoCode,
                    subtitle = movement.detail,
                    chip = stringResource(R.string.inventory_movement_chip, stringResource(movement.type.label), movement.quantity),
                    chipTone = movement.tone,
                )
            }
        }
    }
}
