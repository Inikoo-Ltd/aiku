package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AskCrmRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun DeliveryNoteWaitingScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        WaitingContent(reference = reference)
    }
}

@Composable
private fun WaitingContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    var showBackToPicking by remember { mutableStateOf(false) }
    val waitingLines = GoodsOutExtraSamples.waitingLines
    val stillWaitingMessage = pluralStringResource(R.plurals.goodsout_extra_still_waiting, waitingLines.size, waitingLines.size)

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = reference,
                kicker = deliveryNoteKicker(GoodsOutExtraSamples.WHOLESALE, GoodsOutExtraSamples.WAITING_BAY),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.superviseGoodsOut) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_back_to_picking),
                    onClick = { showBackToPicking = true },
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
                FooterCaption(text = stringResource(R.string.goodsout_extra_supervisor_only))
            } else {
                FooterStatus(
                    title = pluralStringResource(R.plurals.goodsout_extra_waiting_status_title, waitingLines.size, waitingLines.size),
                    body = stringResource(R.string.goodsout_extra_waiting_status_body),
                )
            }
        },
    ) {
        StateStepper(steps = deliveryNoteSteps(DeliveryNoteStage.Waiting))
        SectionHeading(text = stringResource(R.string.goodsout_extra_waiting_lines))
        ListGroup(modifier = Modifier.fakeData()) {
            waitingLines.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = line.skoCode,
                    subtitle = stringResource(R.string.goodsout_extra_waiting_line_subtitle, line.picked, line.required, line.detail),
                    chip = stringResource(line.reason.label),
                    chipTone = Tone.Warn,
                    done = line.picked.toString(),
                    required = line.required.toString(),
                    quantityTone = Tone.Warn,
                )
            }
        }
        SectionHeading(text = stringResource(R.string.dn_state_picked))
        ListGroup(modifier = Modifier.fakeData()) {
            GoodsOutExtraSamples.waitingPicked.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = line.skoCode,
                    subtitle = line.name,
                    chip = stringResource(R.string.dn_state_picked),
                    chipTone = Tone.Success,
                    done = line.picked.toString(),
                    required = line.required.toString(),
                    quantityTone = Tone.Success,
                )
            }
        }
        if (can.pickAndPack) {
            ListGroup {
                ExtraRow(
                    title = stringResource(R.string.goodsout_extra_ask_crm),
                    subtitle = pluralStringResource(
                        R.plurals.goodsout_extra_crm_messages_about,
                        GoodsOutExtraSamples.CRM_MESSAGES,
                        GoodsOutExtraSamples.CRM_MESSAGES,
                        GoodsOutExtraSamples.CRM_MESSAGES_SKO,
                    ),
                    leading = { ExtraIconTile(icon = R.drawable.ic_ui_chat, tone = Tone.Primary) },
                    onClick = { navigator.open(AskCrmRoute(reference)) },
                    modifier = Modifier.fakeData(cornerRadius = 0.dp),
                )
                RowDivider()
                ExtraRow(
                    title = stringResource(R.string.goodsout_extra_auto_finish_waiting),
                    subtitle = stringResource(R.string.goodsout_extra_auto_finish_waiting_caption),
                    leading = { ExtraIconTile(icon = R.drawable.ic_feedback_check, tone = Tone.Primary) },
                    onClick = { toasts.show(stillWaitingMessage, ToastKind.Info) },
                )
            }
        }
        HintText(text = stringResource(R.string.goodsout_extra_waiting_hint))
    }

    if (showBackToPicking) {
        BackToPickingSheet(reference = reference, onDismiss = { showBackToPicking = false })
    }
}
