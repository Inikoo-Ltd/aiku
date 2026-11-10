package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
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
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNoteWaitingRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun DeliveryNoteIncompleteSetScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        IncompleteSetContent(reference = reference)
    }
}

@Composable
private fun IncompleteSetContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var showMore by remember { mutableStateOf(false) }
    val parts = GoodsOutExtraSamples.setParts
    val partsToPutBack = parts.filter { it.state == SetPartState.PutBack }.map { it.skoCode }

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
            if (can.pickAndPack) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_parts_put_back),
                    onClick = { navigator.open(DeliveryNoteWaitingRoute(reference)) },
                    icon = R.drawable.ic_feedback_check,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            } else {
                FooterStatus(
                    title = stringResource(R.string.goodsout_extra_incomplete_set_title),
                    body = stringResource(R.string.goodsout_extra_incomplete_set_status_body),
                )
            }
        },
    ) {
        StateStepper(steps = deliveryNoteSteps(DeliveryNoteStage.Waiting))
        IncompleteSetNotice(codesToPutBack = joinedWithAnd(partsToPutBack), modifier = Modifier.fakeData())
        SectionHeading(text = stringResource(R.string.goodsout_extra_set_heading, GoodsOutExtraSamples.SET_NAME))
        ListGroup(modifier = Modifier.fakeData()) {
            parts.forEachIndexed { index, part ->
                if (index > 0) {
                    RowDivider()
                }
                val partLabel = stringResource(R.string.goodsout_extra_set_part, part.part, part.parts)
                ItemRow(
                    code = part.skoCode,
                    subtitle = listOfNotNull(
                        part.name,
                        partLabel,
                        part.returnLocation?.let { stringResource(R.string.goodsout_extra_return_to, it) },
                    ).joinToString(KICKER_SEPARATOR),
                    chip = stringResource(part.state.label),
                    chipTone = part.state.tone,
                    done = part.picked.toString(),
                    required = part.required.toString(),
                    quantityTone = progressTone(part.picked, part.required) ?: part.state.tone,
                )
            }
        }
        HintText(text = stringResource(R.string.goodsout_extra_incomplete_set_hint))
    }

    if (showMore) {
        DeliveryNoteMoreSheet(reference = reference, onDismiss = { showMore = false })
    }
}

@Composable
private fun joinedWithAnd(items: List<String>): String = when (items.size) {
    0 -> ""
    1 -> items.first()
    else -> stringResource(R.string.goodsout_extra_list_and, items.dropLast(1).joinToString(", "), items.last())
}

@Composable
private fun IncompleteSetNotice(codesToPutBack: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.warnSoft)
            .border(1.dp, colors.warnBorder, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Text(
            text = stringResource(R.string.goodsout_extra_incomplete_set_title),
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
        )
        Text(
            text = stringResource(R.string.goodsout_extra_incomplete_set_body, codesToPutBack),
            fontSize = 13.sp,
            lineHeight = 19.sp,
            color = colors.warnInk,
        )
    }
}
