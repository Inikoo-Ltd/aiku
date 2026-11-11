package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.deliveryNoteSteps
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.navigation.AddShipmentRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun DeliveryNotePackingScreen(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var showBay by remember { mutableStateOf(false) }
    val parcels = remember { mutableStateListOf<SampleParcel>().apply { addAll(GoodsOutSamples.packingParcels) } }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                DeliveryNoteHeader(
                    reference = reference,
                    kicker = stringResource(R.string.goodsout_kicker_delivery_note, GoodsOutSamples.CHANNEL_WHOLESALE),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.pickAndPack) {
                {
                    AikuButton(
                        text = stringResource(R.string.goodsout_set_as_packed),
                        onClick = { navigator.open(AddShipmentRoute(reference = reference)) },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = deliveryNoteSteps(current = DeliveryNoteStage.Packing))
            PackerMustAdd(items = GoodsOutSamples.packerMustAdd)
            ListGroup(modifier = Modifier.fakeData()) {
                IconInfoRow(
                    icon = R.drawable.ic_ui_area,
                    title = GoodsOutSamples.PICKED_BAY,
                    subtitle = stringResource(R.string.goodsout_picked_bay),
                    actionLabel = if (can.pickAndPack) stringResource(R.string.goodsout_change) else null,
                    onClick = if (can.pickAndPack) ({ showBay = true }) else null,
                )
            }
            if (can.pickAndPack) {
                ScanHintCard(
                    title = stringResource(R.string.goodsout_scan_to_pack),
                    caption = stringResource(R.string.goodsout_scan_put_in_box),
                    value = GoodsOutSamples.PACKING_PARCEL,
                    detail = GoodsOutSamples.PACKING_PARCEL_DETAIL,
                    modifier = Modifier.fakeData(),
                )
            }
            GoodsOutHeading(
                title = stringResource(R.string.goodsout_lines),
                trailing = GoodsOutSamples.PACKED_SUMMARY,
                isTrailingSample = true,
            )
            DeliveryNoteLines(lines = GoodsOutSamples.packingLines)
            GoodsOutHeading(
                title = stringResource(R.string.goodsout_parcels_count, parcels.size),
                trailing = stringResource(R.string.goodsout_parcel_units),
            )
            ListGroup(modifier = Modifier.fakeData()) {
                parcels.forEachIndexed { index, parcel ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ParcelRow(
                        name = stringResource(R.string.goodsout_parcel_name, index + 1),
                        parcel = parcel,
                        isEditable = can.pickAndPack,
                        onChange = { parcels[index] = it },
                    )
                }
                if (can.pickAndPack) {
                    RowDivider()
                    Text(
                        text = stringResource(R.string.goodsout_add_parcel),
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = LocalAikuColors.current.link,
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(min = 48.dp)
                            .clickable(role = Role.Button) { parcels.add(SampleParcel("", weight = "", length = "", width = "", height = "")) }
                            .padding(horizontal = 14.dp, vertical = 14.dp),
                    )
                }
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_packing_note))
        }
    }
    if (showBay) {
        ChangePickedBaySheet(reference = reference, onDismiss = { showBay = false })
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun PackerMustAdd(items: List<String>) {
    val colors = LocalAikuColors.current

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .fillMaxWidth()
            .fakeData()
            .clip(CardShape)
            .background(colors.warnSoft)
            .border(1.dp, colors.warnBorder, CardShape)
            .padding(12.dp),
    ) {
        Text(
            text = stringResource(R.string.goodsout_packer_must_add),
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            color = colors.warnInk,
            modifier = Modifier.align(Alignment.CenterVertically),
        )
        items.forEach { item ->
            Text(
                text = item,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.warnInk,
                modifier = Modifier.clip(ChipShape).background(colors.surface).padding(horizontal = 8.dp, vertical = 3.dp),
            )
        }
    }
}

@Composable
private fun ParcelRow(name: String, parcel: SampleParcel, isEditable: Boolean, onChange: (SampleParcel) -> Unit) {
    val colors = LocalAikuColors.current
    val hasDimensions = parcel.length.isNotBlank() && parcel.width.isNotBlank() && parcel.height.isNotBlank()

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            if (hasDimensions) {
                Text(
                    text = stringResource(R.string.goodsout_parcel_dimensions, parcel.length, parcel.width, parcel.height),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                )
            } else {
                Text(text = stringResource(R.string.goodsout_dimensions_missing), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = colors.warn)
            }
        }
        ParcelField(
            value = parcel.weight,
            placeholder = stringResource(R.string.goodsout_parcel_kg),
            isMissing = parcel.weight.isBlank(),
            isEditable = isEditable,
            onValueChange = { onChange(parcel.copy(weight = it)) },
        )
        ParcelField(
            value = parcel.length,
            placeholder = stringResource(R.string.goodsout_parcel_length),
            isMissing = parcel.length.isBlank(),
            isEditable = isEditable,
            onValueChange = { onChange(parcel.copy(length = it)) },
        )
        ParcelField(
            value = parcel.width,
            placeholder = stringResource(R.string.goodsout_parcel_width),
            isMissing = parcel.width.isBlank(),
            isEditable = isEditable,
            onValueChange = { onChange(parcel.copy(width = it)) },
        )
        ParcelField(
            value = parcel.height,
            placeholder = stringResource(R.string.goodsout_parcel_height),
            isMissing = parcel.height.isBlank(),
            isEditable = isEditable,
            onValueChange = { onChange(parcel.copy(height = it)) },
        )
    }
}

@Composable
private fun ParcelField(value: String, placeholder: String, isMissing: Boolean, isEditable: Boolean, onValueChange: (String) -> Unit) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(8.dp)
    val parcelInteraction = remember { MutableInteractionSource() }
    val isParcelFocused by parcelInteraction.collectIsFocusedAsState()
    val parcelFocusProgress = animateFocusProgress(isParcelFocused)

    BasicTextField(
        value = value,
        onValueChange = { input -> onValueChange(input.filter { it.isDigit() || it == '.' }.take(5)) },
        enabled = isEditable,
        singleLine = true,
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
        textStyle = TextStyle(fontFamily = Inter, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text, textAlign = TextAlign.Center),
        cursorBrush = SolidColor(colors.primary),
        interactionSource = parcelInteraction,
        modifier = Modifier
            .width(56.dp)
            .height(44.dp)
            .focusHalo(progress = parcelFocusProgress, color = colors.focus, cornerRadius = 8.dp)
            .clip(shape)
            .border(1.dp, lerp(if (isMissing) colors.warn else colors.borderStrong, colors.focus, parcelFocusProgress), shape)
            .semantics { contentDescription = placeholder },
        decorationBox = { inner ->
            Box(contentAlignment = Alignment.Center) {
                if (value.isEmpty()) {
                    Text(text = placeholder, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.faint)
                }
                inner()
            }
        },
    )
}
