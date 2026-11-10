package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun DeliveryNoteCollectedScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { DeliveryNoteHeader(reference = reference, kicker = collectionKicker()) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.pickAndPack) {
                {
                    AikuButton(
                        text = stringResource(R.string.goodsout_set_as_collected),
                        onClick = { navigator.switchSection(DeliveryNoteDispatchedRoute(reference = reference)) },
                        icon = R.drawable.ic_ui_check,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Finalised))
            SummaryCard(
                title = stringResource(R.string.goodsout_ready_to_collect),
                body = GoodsOutSamples.COLLECTION_SUMMARY,
                bodyModifier = Modifier.fakeData(cornerRadius = 4.dp),
            )
            GoodsOutFootNote(text = stringResource(R.string.goodsout_collected_note))
        }
    }
}
