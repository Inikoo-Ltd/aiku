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
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchScanField
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuFamilyRoute

@Composable
fun SkuFamiliesScreen() {
    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        SkuFamiliesContent()
    }
}

@Composable
private fun SkuFamiliesContent() {
    val navigator = LocalAppNavigator.current
    var selectedState by remember { mutableIntStateOf(0) }
    var query by remember { mutableStateOf("") }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.SkuFamilies) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        FilterChips(
            options = countChipOptions(sampleFamilyStateCounts),
            selectedIndex = selectedState,
            onSelect = { selectedState = it },
            modifier = Modifier.fakeData(17.dp),
        )
        SearchScanField(
            placeholder = stringResource(R.string.inventory_families_search),
            value = query,
            onValueChange = { query = it },
        )
        ListGroup(modifier = Modifier.fakeData()) {
            sampleFamilies.forEachIndexed { index, family ->
                if (index > 0) RowDivider()
                IconItemRow(
                    icon = R.drawable.ic_ui_sko,
                    title = stringResource(R.string.inventory_pair, family.code, family.name),
                    subtitle = stringResource(
                        R.string.inventory_family_subtitle,
                        pluralStringResource(R.plurals.inventory_sko_count, family.skoCount, family.skoCount),
                        family.stockValue,
                        pluralStringResource(R.plurals.inventory_cover_weeks, family.coverWeeks, family.coverWeeks),
                    ),
                    onClick = { navigator.open(SkuFamilyRoute(family.code)) },
                )
            }
        }
    }
}
