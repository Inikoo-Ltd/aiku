package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun DeliveryNoteTakeOverScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        TakeOverContent(reference = reference)
    }
}

@Composable
private fun TakeOverContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var showMore by remember { mutableStateOf(false) }
    val picker = GoodsOutExtraSamples.TAKE_OVER_PICKER

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = reference,
                kicker = deliveryNoteKicker(GoodsOutExtraSamples.WHOLESALE),
                actions = { DeliveryNoteMoreButton(onClick = { showMore = true }) },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.superviseGoodsOut) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_unlock_to_pick),
                    onClick = { navigator.open(DeliveryNotePickingRoute(reference)) },
                    icon = R.drawable.ic_ui_lock,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            } else {
                FooterStatus(
                    title = stringResource(R.string.goodsout_extra_assigned_to, picker),
                    body = stringResource(R.string.goodsout_extra_unlock_status_body),
                )
            }
        },
    ) {
        StateStepper(steps = deliveryNoteSteps(DeliveryNoteStage.Picking))
        AssignedElsewhereCard(picker = picker, modifier = Modifier.fakeData())
        SectionHeading(
            text = stringResource(R.string.goodsout_extra_items_by_location),
            trailing = pluralStringResource(R.plurals.goodsout_extra_picked_read_only, GoodsOutExtraSamples.TAKE_OVER_ITEMS, GoodsOutExtraSamples.TAKE_OVER_PICKED, GoodsOutExtraSamples.TAKE_OVER_ITEMS),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            GoodsOutExtraSamples.takeOverLines.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                val isPicked = line.picked >= line.required
                ItemRow(
                    code = line.skoCode,
                    subtitle = listOf(line.location, line.name).joinToString(KICKER_SEPARATOR),
                    chip = if (isPicked) stringResource(R.string.dn_state_picked) else null,
                    chipTone = Tone.Success,
                    done = line.picked.toString(),
                    required = line.required.toString(),
                    quantityTone = progressTone(line.picked, line.required),
                )
            }
        }
    }

    if (showMore) {
        DeliveryNoteMoreSheet(reference = reference, onDismiss = { showMore = false })
    }
}

@Composable
private fun AssignedElsewhereCard(picker: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.warnSoft)
            .border(1.dp, colors.warnBorder, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        ExtraIconTile(icon = R.drawable.ic_ui_lock, tone = Tone.Warn)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = stringResource(R.string.goodsout_extra_picked_by, picker),
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
            )
            Text(text = stringResource(R.string.goodsout_extra_assigned_elsewhere), fontSize = 13.sp, color = colors.warnInk)
        }
    }
}
