package io.aiku.droid.ui.inventory

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SkuRoute

@Composable
fun SkuFamilyScreen(code: String) {
    RequireAbility(ability = Ability.ViewInventory, title = stringResource(R.string.module_inventory), activeTab = MainTab.Menu) {
        SkuFamilyContent(family = sampleFamilyByCode(code))
    }
}

@Composable
private fun SkuFamilyContent(family: SampleFamily) {
    val navigator = LocalAppNavigator.current

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = stringResource(R.string.inventory_pair, family.code, family.name),
                kicker = stringResource(R.string.inventory_family_kicker, stringResource(family.state)),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        InventoryStatGrid(
            stats = listOf(
                InventoryStat(value = family.skoCount.toString(), label = stringResource(R.string.inventory_stat_skos)),
                InventoryStat(value = family.stockValue, label = stringResource(R.string.inventory_stat_stock_value)),
                InventoryStat(
                    value = stringResource(R.string.inventory_cover_weeks_short, family.coverWeeks),
                    label = stringResource(R.string.inventory_stat_cover),
                ),
            ),
            columns = 3,
            modifier = Modifier.fakeData(10.dp),
        )
        InventorySectionHeading(text = stringResource(R.string.inventory_skos_heading))
        ListGroup(modifier = Modifier.fakeData()) {
            sampleFamilySkos.forEachIndexed { index, sko ->
                if (index > 0) RowDivider()
                ItemRow(
                    code = sko.code,
                    subtitle = skoSubtitle(sko),
                    chip = stringResource(sko.badge.label),
                    chipTone = sko.badge.tone,
                    done = sko.stock?.toString(),
                    onClick = { navigator.open(SkuRoute(sko.code)) },
                )
            }
        }
    }
}
