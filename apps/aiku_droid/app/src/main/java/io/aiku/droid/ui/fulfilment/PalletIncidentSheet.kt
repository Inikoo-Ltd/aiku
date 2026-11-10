package io.aiku.droid.ui.fulfilment

import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.PalletIncidentsRoute
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun PalletIncidentSheet(reference: String, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    var kind by remember { mutableStateOf(PalletIncidentKind.Damaged) }
    var note by remember { mutableStateOf(INCIDENT_NOTE_SAMPLE) }
    val isLost = kind == PalletIncidentKind.Lost
    val savedMessage = stringResource(
        if (isLost) R.string.fulfilment_incident_lost_toast else R.string.fulfilment_incident_damaged_toast,
        reference,
    )
    val noteLabel = stringResource(R.string.fulfilment_incident_note)

    AikuBottomSheet(title = stringResource(R.string.fulfilment_incident_title), onDismiss = onDismiss) {
        Text(
            text = dotJoined(reference, palletFactsSample.location),
            fontSize = 14.sp,
            color = colors.textTertiary,
            modifier = Modifier.fakeData(cornerRadius = 4.dp).padding(horizontal = 4.dp, vertical = 2.dp),
        )
        ChoiceRow(
            title = stringResource(R.string.fulfilment_state_damaged),
            subtitle = stringResource(R.string.fulfilment_incident_damaged_hint),
            selected = kind == PalletIncidentKind.Damaged,
            onClick = { kind = PalletIncidentKind.Damaged },
        )
        ChoiceRow(
            title = stringResource(R.string.fulfilment_state_lost),
            subtitle = stringResource(R.string.fulfilment_incident_lost_hint),
            selected = isLost,
            onClick = { kind = PalletIncidentKind.Lost },
        )
        Text(text = noteLabel, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
        BasicTextField(
            value = note,
            onValueChange = { note = it },
            textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, lineHeight = 22.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            minLines = 3,
            maxLines = 6,
            modifier = Modifier
                .fakeData(cornerRadius = 8.dp)
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .clip(ControlShape)
                .background(colors.surface)
                .border(1.dp, colors.borderStrong, ControlShape)
                .padding(horizontal = 12.dp, vertical = 10.dp)
                .semantics { contentDescription = noteLabel },
        )
        FulfilmentFootnote(text = stringResource(R.string.fulfilment_incident_footnote))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            AikuButton(
                text = stringResource(if (isLost) R.string.fulfilment_incident_set_lost else R.string.fulfilment_incident_set_damaged),
                onClick = {
                    onDismiss()
                    toasts.show(savedMessage, ToastKind.Success)
                    navigator.open(PalletIncidentsRoute)
                },
                style = ButtonStyle.Danger,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
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
