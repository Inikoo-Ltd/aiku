package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
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
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun PlaceItemNoPickingLocationScreen(reference: String, skuCode: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val item = GoodsInSamples.bookingItem(skuCode)
    val location = GoodsInSamples.NEW_LOCATION
    var setAsPickingLocation by remember { mutableStateOf(true) }
    val placedToast = stringResource(R.string.goodsin_toast_placed, item.checked, item.code, location)
    val shape = RoundedCornerShape(10.dp)

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
                    AikuButton(
                        text = stringResource(R.string.goodsin_place_in, item.checked, location),
                        onClick = {
                            toasts.show(placedToast, kind = ToastKind.Success)
                            navigator.back()
                        },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            PlaceItemCard(
                code = item.code,
                subtitle = pluralStringResource(R.plurals.goodsin_item_checked_quantity, item.checked, item.name, item.checked),
                chip = stringResource(R.string.goodsin_no_picking_location),
                chipTone = Tone.Warn,
                modifier = Modifier.fakeData(),
            )
            ScanPromptCard(
                title = stringResource(R.string.goodsin_scan_location),
                body = stringResource(R.string.goodsin_scan_location_body),
                code = location,
                detail = GoodsInSamples.NEW_LOCATION_DETAIL,
                modifier = Modifier.fakeData(),
                onClick = { navigator.open(ScanCameraRoute) },
            )
            if (can.moveStock) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 56.dp)
                        .clip(shape)
                        .background(colors.warnSoft)
                        .border(width = 1.dp, color = colors.warnBorder, shape = shape)
                        .toggleable(value = setAsPickingLocation, role = Role.Checkbox, onValueChange = { setAsPickingLocation = it })
                        .padding(start = 14.dp, end = 4.dp),
                ) {
                    Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        Text(text = stringResource(R.string.goodsin_set_as_picking), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
                        Text(text = stringResource(R.string.goodsin_set_as_picking_body), fontSize = 12.sp, color = colors.warnInk)
                    }
                    Checkbox(
                        checked = setAsPickingLocation,
                        onCheckedChange = null,
                        colors = CheckboxDefaults.colors(checkedColor = colors.primary, checkmarkColor = colors.onPrimary, uncheckedColor = colors.borderStrong),
                    )
                }
                HelperNote(text = stringResource(R.string.goodsin_set_as_picking_note))
            }
        }
    }
}
