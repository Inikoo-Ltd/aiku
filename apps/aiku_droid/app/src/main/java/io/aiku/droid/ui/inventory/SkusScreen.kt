package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuAbnormalitiesRoute
import io.aiku.droid.ui.navigation.SkuRoute

@Composable
fun SkusScreen() {
    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        SkusContent()
    }
}

@Composable
private fun SkusContent() {
    val navigator = LocalAppNavigator.current
    var selectedState by remember { mutableIntStateOf(0) }
    var query by remember { mutableStateOf("") }
    var showFilter by remember { mutableStateOf(false) }

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.Skus,
                actions = {
                    HeaderIconButton(
                        icon = R.drawable.ic_ui_search,
                        description = stringResource(R.string.filter_open),
                        onClick = { showFilter = true },
                        filled = true,
                    )
                },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        FilterChips(
            options = countChipOptions(sampleSkoStateCounts),
            selectedIndex = selectedState,
            onSelect = { index ->
                if (index == SKO_ABNORMALITIES_CHIP) {
                    navigator.open(SkuAbnormalitiesRoute)
                } else {
                    selectedState = index
                }
            },
            modifier = Modifier.fakeData(17.dp),
        )
        SearchScanField(
            placeholder = stringResource(R.string.inventory_skos_search),
            value = query,
            onValueChange = { query = it },
        )
        ListGroup(modifier = Modifier.fakeData()) {
            sampleSkos.forEachIndexed { index, sko ->
                if (index > 0) RowDivider()
                val locationCount = sko.locationCount ?: 0
                ItemRow(
                    code = sko.code,
                    subtitle = stringResource(
                        R.string.inventory_pair,
                        sko.name,
                        pluralStringResource(R.plurals.inventory_location_count, locationCount, locationCount),
                    ),
                    chip = stringResource(sko.badge.label),
                    chipTone = sko.badge.tone,
                    done = sko.stock?.toString(),
                    onClick = { navigator.open(SkuRoute(sko.code)) },
                )
            }
        }
        BoardFootnote(text = stringResource(R.string.inventory_skos_footnote))
    }

    if (showFilter) {
        SkuFilterSheet(onDismiss = { showFilter = false })
    }
}
