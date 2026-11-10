package io.aiku.droid.ui.goodsin

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.GoodsInReturnRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun GoodsInReturnsScreen() {
    val navigator = LocalAppNavigator.current

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.GoodsInReturns) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsInSamples.returns.forEachIndexed { index, sampleReturn ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = sampleReturn.reference,
                        subtitle = sampleReturn.summary,
                        chip = stringResource(sampleReturn.state.label),
                        chipTone = sampleReturn.state.tone,
                        showPhoto = false,
                        onClick = if (sampleReturn.state != ReturnState.Returned) {
                            { navigator.open(GoodsInReturnRoute(sampleReturn.reference)) }
                        } else {
                            null
                        },
                    )
                }
            }
            HelperNote(text = stringResource(R.string.goodsin_returns_note))
        }
    }
}
