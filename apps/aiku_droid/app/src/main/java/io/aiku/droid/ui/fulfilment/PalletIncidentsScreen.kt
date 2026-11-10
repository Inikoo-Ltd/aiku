package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PalletRoute

@Composable
fun PalletIncidentsScreen() {
    RequireFulfilment(section = R.string.section_stored_pallets) {
        PalletIncidentsContent()
    }
}

@Composable
private fun PalletIncidentsContent() {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val toResolve = palletIncidentsSample.first()
    val backToStoringMessage = stringResource(R.string.fulfilment_incidents_storing_toast, toResolve.reference)

    AppScreen(
        header = {
            ModuleHeader(
                section = WarehouseSection.StoredPallets,
                title = stringResource(R.string.fulfilment_state_incidents),
                kicker = stringResource(R.string.section_stored_pallets),
                onBack = { navigator.back() },
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        ListGroup(modifier = Modifier.fakeData()) {
            palletIncidentsSample.forEachIndexed { index, incident ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = incident.reference,
                    subtitle = incident.summary,
                    chip = stringResource(incident.kind.label),
                    chipTone = incident.kind.tone,
                    showPhoto = false,
                )
            }
        }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_incidents_footnote))
        if (can.editFulfilment) {
            AikuButton(
                text = stringResource(R.string.fulfilment_incidents_set_storing, toResolve.reference),
                onClick = {
                    toasts.show(backToStoringMessage, ToastKind.Success)
                    navigator.open(PalletRoute(toResolve.reference))
                },
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
            )
        }
    }
}
