package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun DeliveryNoteCancelledScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        CancelledContent(reference = reference)
    }
}

@Composable
private fun CancelledContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val picks = GoodsOutExtraSamples.cancelledPicks
    val units = picks.sumOf { it.picked }

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = reference,
                kicker = deliveryNoteKicker(GoodsOutExtraSamples.B2C),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        CancelledAlert(
            body = pluralStringResource(
                R.plurals.goodsout_extra_cancelled_units_picked,
                units,
                units,
                pluralStringResource(R.plurals.goodsout_extra_sko_count, picks.size, picks.size),
            ),
            modifier = Modifier.fakeData(),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            picks.forEachIndexed { index, pick ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = pick.skoCode,
                    subtitle = stringResource(R.string.goodsout_extra_picked_from, pick.location),
                    done = pick.picked.toString(),
                    required = pick.required.toString(),
                    quantityTone = Tone.Success,
                )
            }
        }
        HintText(text = stringResource(R.string.goodsout_extra_cancelled_hint))
        AikuButton(
            text = stringResource(R.string.goodsout_extra_back_to_delivery_notes),
            onClick = { navigator.open(DeliveryNotesRoute) },
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun CancelledAlert(body: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.dangerSoft)
            .semantics { liveRegion = LiveRegionMode.Assertive }
            .padding(horizontal = 16.dp, vertical = 24.dp),
    ) {
        Text(
            text = stringResource(R.string.goodsout_extra_cancelled_title),
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = colors.dangerInk,
            textAlign = TextAlign.Center,
        )
        Text(text = body, fontSize = 14.sp, lineHeight = 20.sp, color = colors.textSecondary, textAlign = TextAlign.Center)
    }
}
