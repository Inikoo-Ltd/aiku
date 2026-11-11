package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SearchField
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ShipmentsRoute
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ManualShipmentScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current
    var search by remember { mutableStateOf("") }
    var selectedShipper by remember { mutableIntStateOf(0) }
    var trackingNumber by remember { mutableStateOf(GoodsOutSamples.MANUAL_TRACKING_NUMBER) }
    val shippers = GoodsOutSamples.otherShippers
    val trackingLabel = stringResource(R.string.goodsout_tracking_number)

    RequireAbility(ability = Ability.PickAndPack, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ShipmentHeader(reference = reference, title = stringResource(R.string.goodsout_add_shipment)) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = {
                AikuButton(
                    text = stringResource(R.string.goodsout_add_shipment),
                    onClick = { navigator.open(ShipmentsRoute(reference = reference)) },
                    icon = R.drawable.ic_ui_truck,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Packed))
            SearchField(
                value = search,
                onValueChange = { search = it },
                placeholder = stringResource(R.string.goodsout_select_shipping),
                modifier = Modifier.fillMaxWidth(),
            )
            Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
                shippers
                    .withIndex()
                    .filter { search.isBlank() || it.value.name.contains(search, ignoreCase = true) }
                    .forEach { (index, shipper) ->
                        ChoiceRow(
                            title = shipper.name,
                            subtitle = shipper.detail,
                            selected = index == selectedShipper,
                            onClick = { selectedShipper = index },
                        )
                    }
            }
            if (shippers[selectedShipper].needsTrackingNumber) {
                Text(
                    text = trackingLabel,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.text,
                    modifier = Modifier.padding(horizontal = 4.dp),
                )
                val trackingInteraction = remember { MutableInteractionSource() }
                val isTrackingFocused by trackingInteraction.collectIsFocusedAsState()
                val trackingFocusProgress = animateFocusProgress(isTrackingFocused)
                BasicTextField(
                    value = trackingNumber,
                    onValueChange = { trackingNumber = it },
                    singleLine = true,
                    textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, color = colors.text),
                    cursorBrush = SolidColor(colors.primary),
                    interactionSource = trackingInteraction,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp)
                        .fakeData(cornerRadius = 8.dp)
                        .focusHalo(progress = trackingFocusProgress, color = colors.focus, cornerRadius = 8.dp)
                        .clip(ControlShape)
                        .background(colors.surface)
                        .border(2.dp, lerp(colors.primary, colors.focus, trackingFocusProgress), ControlShape)
                        .semantics { contentDescription = trackingLabel },
                    decorationBox = { inner ->
                        Box(contentAlignment = Alignment.CenterStart, modifier = Modifier.padding(horizontal = 14.dp)) {
                            inner()
                        }
                    },
                )
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_manual_shipment_note))
        }
    }
}
