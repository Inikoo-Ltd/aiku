package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryState
import io.aiku.droid.data.api.PalletState
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.fulfilment.inbound.DeliveryPalletList
import io.aiku.droid.ui.fulfilment.inbound.InboundLoading
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryStatus
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryUiState
import io.aiku.droid.ui.fulfilment.inbound.PalletDeliveryViewModel
import io.aiku.droid.ui.fulfilment.inbound.deliveryKicker
import io.aiku.droid.ui.fulfilment.inbound.rememberPalletDelivery
import io.aiku.droid.ui.goodsin.SectionHeading
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.navigation.StoredPalletsRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

@Composable
fun FulfilmentBookedInScreen(container: AppContainer, warehouseId: Long, id: Long, reference: String) {
    val delivery = rememberPalletDelivery(container, warehouseId, id)
    val state by delivery.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { delivery.onStart() }

    RequireFulfilment(section = R.string.section_fulfilment_deliveries) {
        val loaded = state.delivery
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = delivery::signInAgain)
            loaded != null -> BookedInContent(viewModel = delivery, state = state, delivery = loaded)
            else -> InboundLoading(
                title = reference,
                kicker = stringResource(R.string.fulfilment_delivery_kicker),
                problem = state.problem,
                failedTitle = stringResource(R.string.fulfilment_inbound_delivery_failed, reference),
                onRetry = delivery::retry,
            )
        }
    }
}

@Composable
private fun BookedInContent(viewModel: PalletDeliveryViewModel, state: PalletDeliveryUiState, delivery: PalletDelivery) {
    val navigator = LocalAppNavigator.current
    val storing = delivery.pallets.filter { it.state != PalletState.NOT_RECEIVED }
    val notReceived = delivery.pallets.filter { it.state == PalletState.NOT_RECEIVED }

    AppScreen(
        header = { AppHeader(title = delivery.reference, kicker = deliveryKicker(delivery)) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.fulfilment_inbound_delivery_refresh_failed), onRetry = viewModel::retry) }
        PalletDeliveryStatus(delivery = delivery)
        if (delivery.state == PalletDeliveryState.BOOKED_IN) {
            FulfilmentSuccessHero(
                title = stringResource(R.string.fulfilment_booked_in_title, delivery.reference),
                body = pluralStringResource(R.plurals.fulfilment_booked_in_body, storing.size, storing.size),
            )
        }
        SectionHeading(title = stringResource(R.string.fulfilment_pallets), caption = countText(storing.size))
        DeliveryPalletList(pallets = storing, onOpen = { navigator.open(PalletRoute(id = it.id, reference = it.reference)) })
        if (notReceived.isNotEmpty()) {
            SectionHeading(
                title = stringResource(R.string.fulfilment_state_not_received),
                caption = pluralStringResource(R.plurals.fulfilment_pallet_count, notReceived.size, notReceived.size),
            )
            DeliveryPalletList(pallets = notReceived, onOpen = { navigator.open(PalletRoute(id = it.id, reference = it.reference)) })
        }
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
            icon = R.drawable.ic_ui_next,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
