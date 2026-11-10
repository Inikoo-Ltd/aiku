package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.border
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.navigation.DeliveryNoteCollectedRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun collectionKicker(): String =
    stringResource(R.string.goodsout_kicker_collection, stringResource(R.string.goodsout_channel_b2c))

@Composable
fun DeliveryNoteCollectionScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { DeliveryNoteHeader(reference = reference, kicker = collectionKicker()) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.pickAndPack) {
                {
                    AikuButton(
                        text = stringResource(R.string.goodsout_finalise_for_collection),
                        onClick = { navigator.switchSection(DeliveryNoteCollectedRoute(reference = reference)) },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Packed))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(CardShape)
                    .background(colors.surface)
                    .border(1.dp, colors.border, CardShape)
                    .padding(14.dp),
            ) {
                IconTile40(icon = R.drawable.ic_ui_location, tone = Tone.Primary)
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = stringResource(R.string.goodsout_collection), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = stringResource(R.string.goodsout_collection_hint), fontSize = 13.sp, color = colors.textTertiary)
                }
            }
            SampleInfoGroup(rows = GoodsOutSamples.collectionParcels, icon = R.drawable.ic_ui_sko)
            GoodsOutHeading(title = stringResource(R.string.goodsout_print))
            SampleInfoGroup(
                rows = GoodsOutSamples.collectionLeaflets,
                icon = R.drawable.ic_ui_print,
                tone = Tone.Success,
                chip = stringResource(R.string.goodsout_printed),
            )
            GoodsOutFootNote(text = stringResource(R.string.goodsout_collection_note))
        }
    }
}
