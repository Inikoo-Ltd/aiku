package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryAddressFields
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.goodsout.ActionProblem

private const val COUNTRY_CODE_LENGTH = 2

@Composable
internal fun EditAddressSheet(
    reference: String,
    initial: DeliveryAddressFields?,
    problem: String?,
    isWorking: Boolean,
    onSave: (DeliveryAddressFields) -> Unit,
    onDismiss: () -> Unit,
    saveLabel: String = stringResource(R.string.goodsout_address_save_and_retry),
    hint: String = stringResource(R.string.goodsout_address_fix_hint),
    showsContact: Boolean = true,
) {
    var address by remember { mutableStateOf(initial ?: DeliveryAddressFields()) }

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_address_title),
        subtitle = stringResource(R.string.goodsout_extra_address_subtitle, reference),
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = saveLabel,
                onClick = { onSave(address) },
                isLoading = isWorking,
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
        },
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(14.dp), modifier = Modifier.fillMaxWidth()) {
            if (showsContact) {
                AddressField(label = stringResource(R.string.goodsout_extra_address_contact), value = address.contactName, onValueChange = { address = address.copy(contactName = it) })
                AddressField(label = stringResource(R.string.goodsout_extra_address_company), value = address.companyName, onValueChange = { address = address.copy(companyName = it) })
                AddressField(label = stringResource(R.string.goodsout_extra_address_phone), value = address.phone, onValueChange = { address = address.copy(phone = it) })
                AddressField(label = stringResource(R.string.goodsout_extra_address_email), value = address.email, onValueChange = { address = address.copy(email = it) })
            }
            AddressField(label = stringResource(R.string.goodsout_extra_address_street), value = address.addressLine1, onValueChange = { address = address.copy(addressLine1 = it) })
            AddressField(label = stringResource(R.string.goodsout_address_line_2), value = address.addressLine2, onValueChange = { address = address.copy(addressLine2 = it) })
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                AddressField(
                    label = stringResource(R.string.goodsout_extra_address_town),
                    value = address.locality,
                    onValueChange = { address = address.copy(locality = it) },
                    modifier = Modifier.weight(1f),
                )
                AddressField(
                    label = stringResource(R.string.goodsout_extra_address_postcode),
                    value = address.postalCode,
                    onValueChange = { address = address.copy(postalCode = it) },
                    modifier = Modifier.weight(1f),
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                AddressField(
                    label = stringResource(R.string.goodsout_address_region),
                    value = address.administrativeArea,
                    onValueChange = { address = address.copy(administrativeArea = it) },
                    modifier = Modifier.weight(1f),
                )
                AddressField(
                    label = stringResource(R.string.goodsout_address_country_code),
                    value = address.countryCode,
                    onValueChange = { text -> address = address.copy(countryCode = text.filter { it.isLetter() }.take(COUNTRY_CODE_LENGTH).uppercase()) },
                    modifier = Modifier.weight(1f),
                )
            }
        }
        HintText(text = hint)
    }
}

@Composable
private fun AddressField(label: String, value: String?, onValueChange: (String) -> Unit, modifier: Modifier = Modifier) {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp), modifier = modifier) {
        FieldLabel(text = label)
        PlainTextField(value = value.orEmpty(), onValueChange = onValueChange)
    }
}
