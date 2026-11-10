package io.aiku.droid.ui.goodsin

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
import androidx.compose.runtime.mutableIntStateOf
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
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsout.SetPickingLocationSheet
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun PlaceItemScreen(reference: String, skuCode: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val item = GoodsInSamples.bookingItem(skuCode)
    val choices = GoodsInSamples.locationChoices
    var selectedLocation by remember { mutableStateOf(choices.first().code) }
    var quantity by remember { mutableIntStateOf(item.checked) }
    var showPickingLocationSheet by remember { mutableStateOf(false) }
    val placedToast = stringResource(R.string.goodsin_toast_placed, quantity, item.code, selectedLocation)
    val placedAllToast = stringResource(R.string.goodsin_toast_placed, item.checked, item.code, selectedLocation)

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                AppHeader(
                    isSample = true,
                    title = stringResource(R.string.goodsin_place_item, item.code),
                    kicker = stringResource(R.string.goodsin_kicker_booking_in, reference),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.bookIn) {
                {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        AikuButton(
                            text = stringResource(R.string.goodsin_place_all),
                            onClick = {
                                toasts.show(placedAllToast, kind = ToastKind.Success)
                                navigator.back()
                            },
                            style = ButtonStyle.Soft,
                            height = 52.dp,
                            modifier = Modifier.weight(1f),
                        )
                        AikuButton(
                            text = stringResource(R.string.goodsin_place_in, quantity, selectedLocation),
                            onClick = {
                                toasts.show(placedToast, kind = ToastKind.Success)
                                navigator.back()
                            },
                            height = 52.dp,
                            modifier = Modifier.weight(1f),
                        )
                    }
                }
            } else {
                null
            },
        ) {
            PlaceItemCard(
                code = item.code,
                subtitle = pluralStringResource(R.plurals.goodsin_item_checked_quantity, item.checked, item.name, item.checked),
                modifier = Modifier.fakeData(),
            )
            SectionHeading(title = stringResource(R.string.goodsin_where_does_it_go))
            Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
                choices.forEach { choice ->
                    ChoiceRow(
                        title = choice.code,
                        subtitle = choice.detail,
                        selected = selectedLocation == choice.code,
                        onClick = { selectedLocation = choice.code },
                    )
                }
                ChoiceRow(
                    title = stringResource(R.string.goodsin_another_location),
                    subtitle = stringResource(R.string.goodsin_another_location_body),
                    selected = false,
                    onClick = { navigator.open(ScanCameraRoute) },
                )
            }
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(CardShape)
                    .background(colors.surface)
                    .border(width = 1.dp, color = colors.border, shape = CardShape)
                    .padding(16.dp),
            ) {
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = stringResource(R.string.goodsin_quantity), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = stringResource(R.string.goodsin_up_to, item.checked), fontSize = 12.sp, color = colors.textTertiary)
                }
                CountStepper(value = quantity, onValueChange = { quantity = it }, min = 1, max = item.checked, valueMinWidth = 48.dp)
            }
            ListGroup(modifier = Modifier.fakeData()) {
                ActionLinkRow(
                    icon = R.drawable.ic_ui_location,
                    title = stringResource(R.string.goodsin_picking_location),
                    subtitle = GoodsInSamples.PICKING_LOCATIONS,
                    tone = ActionTone.Primary,
                    trailing = if (can.moveStock) stringResource(R.string.goodsin_change) else null,
                    onClick = if (can.moveStock) {
                        { showPickingLocationSheet = true }
                    } else {
                        null
                    },
                )
            }
            HelperNote(text = stringResource(R.string.goodsin_place_note))
        }
    }

    if (showPickingLocationSheet) {
        SetPickingLocationSheet(skuCode = item.code, onDismiss = { showPickingLocationSheet = false })
    }
}
