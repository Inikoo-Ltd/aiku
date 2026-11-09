package io.aiku.droid.ui.inventory

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.SkuFamilyDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SkuFamiliesScreen() {
    val warehouse = LocalWarehouse.current
    val title = stringResource(R.string.inventory_section_families)

    RequireAbility(isAllowed = warehouse.can.viewInventory, title = title, activeTab = AppTab.Inventory) {
        SkuFamiliesContent(title = title, kicker = stringResource(R.string.inventory_kicker, warehouse.name))
    }
}

@Composable
private fun SkuFamiliesContent(title: String, kicker: String) {
    val navigator = LocalAppNavigator.current
    var query by remember { mutableStateOf("") }

    AppScreen(
        header = {
            AppHeader(
                title = title,
                kicker = kicker,
                below = {
                    InventorySectionTabs(active = InventorySection.Families)
                    SearchField(
                        value = query,
                        onValueChange = { query = it },
                        placeholder = stringResource(R.string.inventory_families_search),
                        modifier = Modifier.fillMaxWidth(),
                    )
                },
            )
        },
        activeTab = AppTab.Inventory,
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 16.dp),
        spacing = 10.dp,
    ) {
        ListMetaRow(start = stringResource(R.string.inventory_families_sorted)) {
            MetaText(text = stringResource(R.string.inventory_families_columns))
        }
        ListGroup(modifier = Modifier.fakeData()) {
            InventoryFamilySamples.forEachIndexed { index, family ->
                if (index > 0) {
                    RowDivider()
                }
                ListRow(
                    title = family.name,
                    subtitle = family.summary,
                    leading = { FamilyCodeTile(code = family.code) },
                    trailingValue = family.stockValue,
                    onClick = { navigator.open(SkuFamilyDetailRoute(family.code)) },
                )
            }
        }
        ListFooter(
            text = stringResource(R.string.inventory_showing, "5", "186"),
            modifier = Modifier.align(Alignment.CenterHorizontally).fakeData(8.dp),
        )
    }
}

@Composable
private fun FamilyCodeTile(code: String) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier.size(40.dp).clip(ControlShape).background(colors.soft),
    ) {
        Text(text = code, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = colors.link)
    }
}

@Preview(showSystemUi = true)
@Composable
private fun SkuFamiliesScreenPreview() {
    AikuDroidTheme {
        SkuFamiliesScreen()
    }
}
