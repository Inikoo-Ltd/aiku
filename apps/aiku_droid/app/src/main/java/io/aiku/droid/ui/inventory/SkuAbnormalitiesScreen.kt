package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuRoute

@Composable
fun SkuAbnormalitiesScreen() {
    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        SkuAbnormalitiesContent()
    }
}

@Composable
private fun SkuAbnormalitiesContent() {
    val navigator = LocalAppNavigator.current
    val chipCounts = sampleSkoStateCounts.mapIndexed { index, sample ->
        if (index == SKO_ABNORMALITIES_CHIP) sample else sample.copy(count = null)
    }

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.Skus,
                title = stringResource(R.string.inventory_abnormalities_title),
                kicker = stringResource(R.string.inventory_abnormalities_kicker),
                onBack = { navigator.back() },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        FilterChips(
            options = countChipOptions(chipCounts),
            selectedIndex = SKO_ABNORMALITIES_CHIP,
            onSelect = { index -> if (index != SKO_ABNORMALITIES_CHIP) navigator.back() },
            modifier = Modifier.fakeData(17.dp),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            sampleAbnormalSkos.forEachIndexed { index, sko ->
                if (index > 0) RowDivider()
                ItemRow(
                    code = sko.code,
                    subtitle = skoSubtitle(sko),
                    chip = stringResource(sko.badge.label),
                    chipTone = sko.badge.tone,
                    onClick = { navigator.open(SkuRoute(sko.code)) },
                )
            }
        }
        BoardFootnote(text = stringResource(R.string.inventory_abnormalities_footnote))
    }
}
