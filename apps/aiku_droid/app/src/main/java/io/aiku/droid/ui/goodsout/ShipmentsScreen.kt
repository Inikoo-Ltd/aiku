package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AddShipmentRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNoteFinalisedRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ShipmentsScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val shipments = remember { mutableStateListOf<SampleChoice>().apply { addAll(GoodsOutSamples.shipments) } }
    val leaflets = GoodsOutSamples.leaflets
    val allInsertsPrinted = GoodsOutSamples.leafletsPrinted.all { it }
    val deletedText = stringResource(R.string.goodsout_shipment_deleted)
    val undoLabel = stringResource(R.string.goodsout_undo)

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ShipmentHeader(reference = reference, title = stringResource(R.string.goodsout_shipments)) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = {
                if (allInsertsPrinted) {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        AikuButton(
                            text = stringResource(R.string.goodsout_finalise),
                            onClick = { navigator.switchSection(DeliveryNoteFinalisedRoute(reference = reference)) },
                            style = ButtonStyle.Outline,
                            height = 52.dp,
                            modifier = Modifier.weight(1f),
                        )
                        AikuButton(
                            text = stringResource(R.string.goodsout_finalise_and_dispatch),
                            onClick = { navigator.switchSection(DeliveryNoteDispatchedRoute(reference = reference)) },
                            height = 52.dp,
                            modifier = Modifier.weight(1f),
                        )
                    }
                } else {
                    FooterStatus(title = stringResource(R.string.goodsout_print_every_insert))
                }
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Packed))
            if (shipments.isNotEmpty()) {
                ListGroup(modifier = Modifier.fakeData()) {
                    shipments.forEachIndexed { index, shipment ->
                        if (index > 0) {
                            RowDivider()
                        }
                        IconInfoRow(
                            icon = R.drawable.ic_ui_truck,
                            title = shipment.title,
                            subtitle = shipment.subtitle,
                            tone = Tone.Success,
                            chip = stringResource(R.string.goodsout_label_queued),
                            actionLabel = stringResource(R.string.goodsout_delete),
                            actionColor = colors.danger,
                            onAction = {
                                shipments.remove(shipment)
                                toasts.show(deletedText, ToastKind.Success, actionLabel = undoLabel, onAction = { shipments.add(index.coerceAtMost(shipments.size), shipment) })
                            },
                        )
                    }
                }
            }
            AikuButton(
                text = stringResource(R.string.goodsout_add_another_shipment),
                onClick = { navigator.open(AddShipmentRoute(reference = reference)) },
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            GoodsOutHeading(title = stringResource(R.string.goodsout_print))
            SampleInfoGroup(rows = leaflets, icon = R.drawable.ic_ui_print, tone = Tone.Success, chip = stringResource(R.string.goodsout_printed))
            GoodsOutFootNote(text = stringResource(R.string.goodsout_shipments_note))
        }
    }
}
