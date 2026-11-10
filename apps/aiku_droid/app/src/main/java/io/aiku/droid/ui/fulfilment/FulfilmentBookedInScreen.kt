package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StoredPalletsRoute

@Composable
fun FulfilmentBookedInScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        val navigator = LocalAppNavigator.current

        AppScreen(
            header = {
                FulfilmentHeader(
                    title = reference,
                    kicker = dotJoined(stringResource(R.string.fulfilment_delivery_kicker), BOOKED_IN_CUSTOMER_SAMPLE),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            StateStepper(
                steps = fulfilmentDeliverySteps(current = FulfilmentDeliveryState.BookedIn),
                modifier = Modifier.fakeData(cornerRadius = 14.dp),
            )
            FulfilmentSuccessHero(
                title = stringResource(R.string.fulfilment_booked_in_title, reference),
                body = pluralStringResource(R.plurals.fulfilment_booked_in_body, BOOKED_IN_PALLET_COUNT_SAMPLE, BOOKED_IN_PALLET_COUNT_SAMPLE),
                modifier = Modifier.fakeData(),
            )
            AikuButton(
                text = stringResource(R.string.fulfilment_booked_in_view_pallets),
                onClick = { navigator.open(StoredPalletsRoute) },
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.fulfilment_booked_in_next_delivery),
                onClick = { navigator.switchSection(FulfilmentDeliveriesRoute) },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
