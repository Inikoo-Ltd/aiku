package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnShipmentRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ReturnPickedScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        ReturnPickedContent(reference = reference)
    }
}

@Composable
private fun ReturnPickedContent(reference: String) {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    var customerCollects by remember { mutableStateOf(false) }
    val pickedPallets = returnPickedPalletsSample

    AppScreen(
        header = {
            FulfilmentHeader(
                title = reference,
                kicker = dotJoined(
                    stringResource(R.string.fulfilment_return_kicker),
                    stringResource(FulfilmentReturnType.Pallets.label),
                    RETURN_PICKED_CUSTOMER_SAMPLE,
                ),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (!can.editFulfilment) {
                FooterStatus(
                    title = stringResource(FulfilmentReturnState.Picked.label),
                    body = stringResource(R.string.fulfilment_return_ready_to_dispatch),
                )
            } else if (customerCollects) {
                AikuButton(
                    text = stringResource(R.string.fulfilment_return_dispatch),
                    onClick = { navigator.switchSection(ReturnDispatchedRoute(reference)) },
                    icon = R.drawable.ic_ui_truck,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            } else {
                AikuButton(
                    text = stringResource(R.string.fulfilment_return_create_shipment),
                    onClick = { navigator.open(ReturnShipmentRoute(reference)) },
                    icon = R.drawable.ic_ui_truck,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
    ) {
        StateStepper(
            steps = fulfilmentReturnSteps(current = FulfilmentReturnState.Picked),
            modifier = Modifier.fakeData(cornerRadius = 14.dp),
        )
        AikuCard(modifier = Modifier.fakeData()) {
            Text(text = stringResource(R.string.fulfilment_return_all_picked), fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(
                text = pluralStringResource(
                    R.plurals.fulfilment_return_picked_pallets,
                    pickedPallets.size,
                    pickedPallets.size,
                    pickedPallets.joinToString(", "),
                ),
                fontSize = 14.sp,
                color = colors.textTertiary,
            )
        }
        if (can.editFulfilment) {
            FulfilmentSectionHeading(text = stringResource(R.string.fulfilment_return_how_it_leaves))
            ChoiceRow(
                title = stringResource(R.string.fulfilment_return_shipment),
                subtitle = stringResource(R.string.fulfilment_return_shipment_hint),
                selected = !customerCollects,
                onClick = { customerCollects = false },
            )
            ChoiceRow(
                title = stringResource(R.string.fulfilment_return_collects),
                subtitle = stringResource(R.string.fulfilment_return_collects_hint),
                selected = customerCollects,
                onClick = { customerCollects = true },
            )
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_return_picked_footnote))
    }
}
