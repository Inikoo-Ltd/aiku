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
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.FulfilmentReturnsRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun ReturnDispatchedScreen(reference: String) {
    RequireFulfilment(section = R.string.section_fulfilment_returns) {
        val navigator = LocalAppNavigator.current

        AppScreen(
            header = {
                FulfilmentHeader(
                    title = reference,
                    kicker = dotJoined(
                        stringResource(R.string.fulfilment_return_kicker),
                        stringResource(FulfilmentReturnType.Pallets.label),
                        RETURN_DISPATCHED_CUSTOMER_SAMPLE,
                    ),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            StateStepper(
                steps = fulfilmentReturnSteps(current = FulfilmentReturnState.Dispatched),
                modifier = Modifier.fakeData(cornerRadius = 14.dp),
            )
            FulfilmentSuccessHero(
                title = stringResource(R.string.fulfilment_return_dispatched_title, reference),
                body = pluralStringResource(
                    R.plurals.fulfilment_return_dispatched_body,
                    RETURN_DISPATCHED_PALLET_COUNT_SAMPLE,
                    RETURN_DISPATCHED_PALLET_COUNT_SAMPLE,
                ),
                modifier = Modifier.fakeData(),
            )
            FulfilmentFootnote(text = stringResource(R.string.fulfilment_return_dispatched_footnote))
            AikuButton(
                text = stringResource(R.string.fulfilment_return_next),
                onClick = { navigator.switchSection(FulfilmentReturnsRoute) },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
