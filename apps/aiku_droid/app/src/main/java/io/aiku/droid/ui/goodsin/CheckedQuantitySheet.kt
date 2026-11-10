package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun CheckedQuantitySheet(reference: String, skuCode: String, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    val item = GoodsInSamples.checkItem(skuCode)
    var checked by remember(skuCode) { mutableIntStateOf(item.checked ?: 0) }
    val difference = differenceOf(checked = checked, delivered = item.delivered)
    val savedToast = pluralStringResource(R.plurals.goodsin_toast_checked, checked, item.code, checked)
    val allCheckedToast = pluralStringResource(R.plurals.goodsin_toast_checked, item.delivered, item.code, item.delivered)
    val notReceivedToast = stringResource(R.string.goodsin_toast_not_received, item.code)

    AikuBottomSheet(
        title = stringResource(R.string.goodsin_sheet_checked_title, item.code),
        subtitle = stringResource(R.string.goodsin_item_delivered, item.name, item.delivered),
        onDismiss = onDismiss,
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .fakeData(cornerRadius = 10.dp)
                .border(width = 1.dp, color = colors.border, shape = RoundedCornerShape(10.dp))
                .padding(horizontal = 14.dp, vertical = 12.dp),
        ) {
            Text(
                text = stringResource(R.string.goodsin_stepper_checked),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            CountStepper(value = checked, onValueChange = { checked = it })
        }
        ListGroup(modifier = Modifier.fakeData()) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.fillMaxWidth().heightIn(min = 60.dp).padding(horizontal = 14.dp, vertical = 10.dp),
            ) {
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                    Text(text = stringResource(R.string.goodsin_difference), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = difference.description, fontSize = 13.sp, color = colors.textTertiary)
                }
                StatusBadge(text = difference.chip, tone = difference.tone)
            }
        }
        HelperNote(text = stringResource(R.string.goodsin_sheet_checked_note))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.padding(top = 4.dp)) {
            AikuButton(
                text = pluralStringResource(R.plurals.goodsin_save_checked, checked, checked),
                onClick = {
                    toasts.show(if (checked == 0) notReceivedToast else savedToast, kind = ToastKind.Success)
                    onDismiss()
                },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.goodsin_check_all, item.delivered),
                onClick = {
                    toasts.show(allCheckedToast, kind = ToastKind.Success)
                    onDismiss()
                },
                style = ButtonStyle.Soft,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.goodsin_check_as_zero),
                onClick = {
                    toasts.show(notReceivedToast, kind = ToastKind.Success)
                    onDismiss()
                },
                style = ButtonStyle.Danger,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}
