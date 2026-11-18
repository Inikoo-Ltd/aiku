package io.aiku.droid.ui.fulfilment.inbound

import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletLocationTarget
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.inventory.SheetButtons
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun MovePalletSheet(
    pallet: Pallet,
    finder: FulfilmentLocationFinder,
    isWorking: Boolean,
    problem: String?,
    onMove: (PalletLocationTarget) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val finderState by finder.state.collectAsStateWithLifecycle()
    val target = finderState.target
    val moveScanned: (String) -> Unit = { code -> code.trim().takeIf { it.isNotEmpty() }?.let { onMove(PalletLocationTarget.Scanned(it)) } }

    ScanEffect(enabled = !isWorking) { scan -> moveScanned(scan.code) }

    AikuBottomSheet(
        title = stringResource(R.string.fulfilment_inbound_move_title, pallet.reference),
        subtitle = pallet.locationCode?.let { stringResource(R.string.fulfilment_inbound_now_in, it) } ?: stringResource(R.string.fulfilment_no_location_yet),
        onDismiss = onDismiss,
    ) {
        Text(text = stringResource(R.string.fulfilment_pallet_move_toast, pallet.reference), fontSize = 14.sp, color = colors.textTertiary)
        PalletLocationPicker(state = finderState, onQueryChange = finder::search, onPick = finder::pick, onScanned = moveScanned)
        problem?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        SheetButtons(
            confirmLabel = target?.let { stringResource(R.string.fulfilment_inbound_move_to, it.code) }.orEmpty(),
            onConfirm = { target?.let(onMove) },
            onCancel = onDismiss,
            isWorking = isWorking,
            showConfirm = target != null,
        )
    }
}
