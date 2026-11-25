package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.fulfilment.inbound.fulfilmentInboundActionLook
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private const val INCIDENT_NOTE_MAX_LENGTH = 1000

@Composable
fun PalletIncidentSheet(
    pallet: Pallet,
    states: List<PalletIncidentState>,
    isWorking: Boolean,
    problem: String?,
    onSave: (PalletIncidentState, String?) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var chosen by remember { mutableStateOf(states.firstOrNull()) }
    var note by remember { mutableStateOf("") }
    val noteLabel = stringResource(R.string.fulfilment_incident_note)
    val noteInteraction = remember { MutableInteractionSource() }
    val isNoteFocused by noteInteraction.collectIsFocusedAsState()
    val noteFocusProgress = animateFocusProgress(isNoteFocused)
    val selected = chosen

    AikuBottomSheet(
        title = stringResource(R.string.fulfilment_incident_title),
        subtitle = dotJoined(pallet.reference, pallet.locationCode),
        onDismiss = onDismiss,
    ) {
        if (PalletIncidentState.Damaged in states) {
            ChoiceRow(
                title = stringResource(R.string.fulfilment_state_damaged),
                subtitle = stringResource(R.string.fulfilment_incident_damaged_hint),
                selected = selected == PalletIncidentState.Damaged,
                onClick = { chosen = PalletIncidentState.Damaged },
            )
        }
        if (PalletIncidentState.Lost in states) {
            ChoiceRow(
                title = stringResource(R.string.fulfilment_state_lost),
                subtitle = stringResource(R.string.fulfilment_incident_lost_hint),
                selected = selected == PalletIncidentState.Lost,
                onClick = { chosen = PalletIncidentState.Lost },
            )
        }
        Text(text = noteLabel, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
        BasicTextField(
            value = note,
            onValueChange = { note = it.take(INCIDENT_NOTE_MAX_LENGTH) },
            textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, lineHeight = 22.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            minLines = 3,
            maxLines = 6,
            interactionSource = noteInteraction,
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .focusHalo(progress = noteFocusProgress, color = colors.focus, cornerRadius = 8.dp)
                .clip(ControlShape)
                .background(colors.surface)
                .border(1.dp, lerp(colors.borderStrong, colors.focus, noteFocusProgress), ControlShape)
                .padding(horizontal = 12.dp, vertical = 10.dp)
                .semantics { contentDescription = noteLabel },
        )
        problem?.let { Text(text = it, fontSize = 13.sp, color = colors.danger) }
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_inbound_incident_footnote))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            if (selected != null) {
                AikuButton(
                    text = stringResource(if (selected == PalletIncidentState.Lost) R.string.fulfilment_incident_set_lost else R.string.fulfilment_incident_set_damaged),
                    onClick = { onSave(selected, note) },
                    style = fulfilmentInboundActionLook(selected.actionKey).style,
                    icon = fulfilmentInboundActionLook(selected.actionKey).icon,
                    isLoading = isWorking,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

private val PalletIncidentState.actionKey: String
    get() = if (this == PalletIncidentState.Lost) FulfilmentInboundActionKey.LOST else FulfilmentInboundActionKey.DAMAGED

