package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.StoredItemAuditRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun PalletScreen(reference: String) {
    RequireFulfilment(section = R.string.section_stored_pallets) {
        PalletContent(reference = reference)
    }
}

@Composable
private fun PalletContent(reference: String) {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    var showIncident by remember { mutableStateOf(false) }
    val moveMessage = stringResource(R.string.fulfilment_pallet_move_toast, reference)
    val facts = palletFactsSample

    AppScreen(
        header = {
            FulfilmentHeader(
                title = reference,
                kicker = dotJoined(stringResource(R.string.fulfilment_type_pallet), PALLET_CUSTOMER_SAMPLE),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        AikuCard(modifier = Modifier.fakeData()) {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                PalletFact(
                    label = stringResource(R.string.fulfilment_pallet_state),
                    value = stringResource(facts.state.label),
                    valueTone = facts.state.tone,
                    modifier = Modifier.weight(1f),
                )
                PalletFact(
                    label = stringResource(R.string.fulfilment_pallet_type),
                    value = stringResource(facts.type.label),
                    modifier = Modifier.weight(1f),
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                PalletFact(
                    label = stringResource(R.string.fulfilment_pallet_location),
                    value = facts.location,
                    modifier = Modifier.weight(1f),
                )
                PalletFact(
                    label = stringResource(R.string.fulfilment_pallet_customer_reference),
                    value = facts.customerReference,
                    modifier = Modifier.weight(1f),
                )
            }
        }
        FulfilmentSectionHeading(
            text = stringResource(R.string.fulfilment_stored_items),
            sampleCaption = palletStoredItemsSample.size.toString(),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            palletStoredItemsSample.forEachIndexed { index, item ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(code = item.code, subtitle = item.description, done = item.quantity.toString())
            }
        }
        if (can.editFulfilment) {
            FulfilmentSectionHeading(text = stringResource(R.string.fulfilment_pallet_actions))
            ListGroup {
                FulfilmentRow(
                    title = stringResource(R.string.fulfilment_pallet_move),
                    subtitle = stringResource(R.string.fulfilment_pallet_move_hint),
                    leading = { IconTile(icon = R.drawable.ic_ui_location_label, tone = Tone.Primary) },
                    onClick = { toasts.show(moveMessage, ToastKind.Info) },
                )
                RowDivider()
                FulfilmentRow(
                    title = stringResource(R.string.fulfilment_pallet_audit),
                    subtitle = stringResource(R.string.fulfilment_pallet_audit_hint),
                    leading = { IconTile(icon = R.drawable.ic_ui_check, tone = Tone.Primary) },
                    onClick = { navigator.open(StoredItemAuditRoute(PALLET_AUDIT_SAMPLE)) },
                )
                RowDivider()
                FulfilmentRow(
                    title = stringResource(R.string.fulfilment_pallet_incident),
                    subtitle = stringResource(R.string.fulfilment_pallet_incident_hint),
                    leading = { IconTile(icon = R.drawable.ic_ui_pause, tone = Tone.Danger) },
                    onClick = { showIncident = true },
                )
            }
        }
    }

    if (showIncident) {
        PalletIncidentSheet(reference = reference, onDismiss = { showIncident = false })
    }
}

@Composable
private fun PalletFact(label: String, value: String, modifier: Modifier = Modifier, valueTone: Tone? = null) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary)
        Text(
            text = value,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = valueTone?.let { colors.tone(it).content } ?: colors.text,
        )
    }
}
