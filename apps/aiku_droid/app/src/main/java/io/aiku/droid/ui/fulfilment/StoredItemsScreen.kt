package io.aiku.droid.ui.fulfilment

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun StoredItemsScreen() {
    RequireFulfilment(section = R.string.section_stored_items) {
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.StoredItems) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            ListGroup(modifier = Modifier.fakeData()) {
                storedItemsSample.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = item.code,
                        subtitle = item.summary,
                        chip = stringResource(item.state.label),
                        chipTone = item.state.tone,
                        done = item.quantity.toString(),
                    )
                }
            }
            FulfilmentFootnote(text = stringResource(R.string.fulfilment_stored_items_footnote))
        }
    }
}
