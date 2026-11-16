package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableDoubleStateOf
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
import io.aiku.droid.data.api.StockDeliveryItem
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.QuantityStepper
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.pluralQuantity
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.SheetGroupLabel
import io.aiku.droid.ui.theme.LocalAikuColors

internal data class CheckLook(val chip: String?, val tone: Tone?, val subtitle: String)

internal data class Difference(val chip: String, val tone: Tone, val description: String)

@Composable
internal fun expectedLine(item: StockDeliveryItem): String =
    stringResource(R.string.goodsin_item_expected, item.name, quantityText(item.skoExpected, item.skoExpectedLabel))

@Composable
internal fun checkLook(item: StockDeliveryItem): CheckLook {
    val checked = item.skoChecked
    val expectedLine = expectedLine(item)
    return when {
        checked == null -> CheckLook(chip = null, tone = null, subtitle = expectedLine)
        item.isNotReceived -> CheckLook(chip = stringResource(R.string.goodsin_not_received), tone = Tone.Danger, subtitle = stringResource(R.string.goodsin_item_checked_as_zero, item.name))
        checked < item.skoExpected -> CheckLook(chip = stringResource(R.string.goodsin_chip_checked_under), tone = Tone.Warn, subtitle = expectedLine)
        checked > item.skoExpected -> CheckLook(chip = stringResource(R.string.goodsin_chip_checked_over), tone = Tone.Orange, subtitle = expectedLine)
        else -> CheckLook(chip = stringResource(R.string.sd_state_checked), tone = Tone.Success, subtitle = expectedLine)
    }
}

@Composable
internal fun differenceOf(checked: Double, expected: Double): Difference {
    val gap = checked - expected
    return when {
        checked == 0.0 && expected > 0.0 -> Difference(
            chip = stringResource(R.string.goodsin_chip_not_received_by, quantityText(expected)),
            tone = Tone.Danger,
            description = pluralStringResource(R.plurals.goodsin_difference_under, pluralQuantity(expected), quantityText(expected)),
        )
        gap < 0 -> Difference(
            chip = stringResource(R.string.goodsin_chip_under, quantityText(-gap)),
            tone = Tone.Warn,
            description = pluralStringResource(R.plurals.goodsin_difference_under, pluralQuantity(-gap), quantityText(-gap)),
        )
        gap > 0 -> Difference(
            chip = stringResource(R.string.goodsin_chip_over, quantityText(gap)),
            tone = Tone.Orange,
            description = pluralStringResource(R.plurals.goodsin_difference_over, pluralQuantity(gap), quantityText(gap)),
        )
        else -> Difference(
            chip = stringResource(R.string.goodsin_chip_matches),
            tone = Tone.Success,
            description = stringResource(R.string.goodsin_difference_none),
        )
    }
}

@Composable
internal fun CheckedQuantitySheet(
    item: StockDeliveryItem,
    isWorking: Boolean,
    problem: String?,
    onSave: (Double) -> Unit,
    onNotReceived: () -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var checked by remember(item.id) { mutableDoubleStateOf(item.skoChecked ?: item.skoExpected) }
    val difference = differenceOf(checked = checked, expected = item.skoExpected)
    val expectedText = quantityText(item.skoExpected, item.skoExpectedLabel)

    AikuBottomSheet(
        title = stringResource(R.string.goodsin_sheet_checked_title, item.code),
        subtitle = expectedLine(item),
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = pluralStringResource(R.plurals.goodsin_save_checked, pluralQuantity(checked), quantityText(checked)),
                onClick = { onSave(checked) },
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.goodsin_check_all, expectedText),
                onClick = { onSave(item.skoExpected) },
                style = ButtonStyle.Soft,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(
                text = stringResource(R.string.goodsin_check_as_zero),
                onClick = onNotReceived,
                style = ButtonStyle.Danger,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        SheetGroupLabel(text = stringResource(R.string.goodsin_stepper_checked))
        QuantityStepper(
            value = checked,
            valueText = quantityText(checked),
            onValueChange = { checked = it },
            min = 0.0,
            max = Double.MAX_VALUE,
        )
        ListGroup {
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
    }
}
