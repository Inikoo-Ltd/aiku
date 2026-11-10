package io.aiku.droid.ui.locations

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData

@Composable
fun AuditLocationSheet(locationCode: String, onDismiss: () -> Unit) {
    val toasts = LocalToasts.current
    val savedText = stringResource(R.string.locations_audit_saved, locationCode)

    AikuBottomSheet(
        title = stringResource(R.string.locations_audit_title, locationCode),
        onDismiss = onDismiss,
        subtitle = stringResource(R.string.locations_audit_subtitle),
    ) {
        ListGroup(modifier = Modifier.fakeData()) {
            LocationsSamples.auditLines.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                ItemRow(
                    code = line.code,
                    subtitle = stringResource(R.string.locations_audit_was, line.was),
                    done = line.counted.toString(),
                )
            }
        }
        LocationsFootnote(text = stringResource(R.string.locations_audit_help))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().padding(top = 4.dp)) {
            AikuButton(
                text = stringResource(R.string.locations_audit_save),
                onClick = {
                    toasts.show(text = savedText, kind = ToastKind.Success)
                    onDismiss()
                },
                modifier = Modifier.fillMaxWidth(),
                height = 52.dp,
            )
            AikuButton(
                text = stringResource(R.string.dialog_cancel),
                onClick = onDismiss,
                modifier = Modifier.fillMaxWidth(),
                style = ButtonStyle.Outline,
                height = 52.dp,
            )
        }
    }
}
