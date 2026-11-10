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
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse

@Composable
fun EditAddressSheet(reference: String, onDismiss: () -> Unit) {
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    val savedMessage = stringResource(R.string.goodsout_extra_address_saved)
    var contact by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_CONTACT) }
    var company by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_COMPANY) }
    var phone by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_PHONE) }
    var email by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_EMAIL) }
    var street by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_STREET) }
    var town by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_TOWN) }
    var postcode by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_POSTCODE) }
    var country by remember { mutableStateOf(GoodsOutExtraSamples.ADDRESS_COUNTRY) }

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_extra_address_title),
        subtitle = stringResource(R.string.goodsout_extra_address_subtitle, reference),
        onDismiss = onDismiss,
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
        ) {
            AddressField(label = stringResource(R.string.goodsout_extra_address_contact), value = contact, onValueChange = { contact = it })
            AddressField(label = stringResource(R.string.goodsout_extra_address_company), value = company, onValueChange = { company = it })
            AddressField(label = stringResource(R.string.goodsout_extra_address_phone), value = phone, onValueChange = { phone = it })
            AddressField(label = stringResource(R.string.goodsout_extra_address_email), value = email, onValueChange = { email = it })
            AddressField(label = stringResource(R.string.goodsout_extra_address_street), value = street, onValueChange = { street = it })
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                AddressField(
                    label = stringResource(R.string.goodsout_extra_address_town),
                    value = town,
                    onValueChange = { town = it },
                    modifier = Modifier.weight(1f),
                )
                AddressField(
                    label = stringResource(R.string.goodsout_extra_address_postcode),
                    value = postcode,
                    onValueChange = { postcode = it },
                    modifier = Modifier.weight(1f),
                )
            }
            AddressField(label = stringResource(R.string.goodsout_extra_address_country), value = country, onValueChange = { country = it })
        }
        HintText(text = stringResource(R.string.goodsout_extra_address_hint))
        SheetButtons {
            if (can.pickAndPack) {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_address_save),
                    onClick = {
                        toasts.show(savedMessage, ToastKind.Success)
                        onDismiss()
                    },
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

@Composable
private fun AddressField(label: String, value: String, onValueChange: (String) -> Unit, modifier: Modifier = Modifier) {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp), modifier = modifier) {
        FieldLabel(text = label)
        PlainTextField(value = value, onValueChange = onValueChange)
    }
}
