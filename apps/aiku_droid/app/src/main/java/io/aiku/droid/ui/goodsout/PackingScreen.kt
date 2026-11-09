package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.runtime.toMutableStateList
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsin.GoodsDetailScaffold
import io.aiku.droid.ui.goodsin.GoodsStateStepper
import io.aiku.droid.ui.goodsin.goodsFocusGlow
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private data class PackingParcel(val itemCount: Int, val weightKg: String?, val boxSize: String)

private val packingParcelsSample = listOf(
    PackingParcel(itemCount = 8, weightKg = "3.4", boxSize = "Medium"),
    PackingParcel(itemCount = 5, weightKg = null, boxSize = "Small"),
)

private val packingNewParcelSample = PackingParcel(itemCount = 0, weightKg = null, boxSize = "Small")

private const val PACKING_SHIPPER_SAMPLE = "[Shipper A] · next day"
private const val PACKING_STATE_SAMPLE = 1

@Composable
fun PackingScreen(reference: String) {
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val parcels = remember { packingParcelsSample.toMutableStateList() }

    RequireAbility(isAllowed = can.viewGoodsOut && can.pickAndPack, title = reference, activeTab = AppTab.GoodsOut) {
        GoodsDetailScaffold(
            header = {
                AppHeader(
                    title = reference,
                    kicker = stringResource(R.string.goodsout_packing_kicker),
                    showMenu = false,
                )
            },
            activeTab = AppTab.GoodsOut,
            bottomBar = {
                AikuButton(
                    text = pluralStringResource(R.plurals.goodsout_set_as_packed, parcels.size, parcels.size),
                    onClick = { navigator.open(DeliveryNotesRoute) },
                    icon = R.drawable.ic_goodsout_packed,
                    height = 52.dp,
                    modifier = Modifier
                        .weight(1f)
                        .fakeData(cornerRadius = 8.dp),
                )
            },
        ) {
            val steps = listOf(
                R.string.goodsout_state_handling,
                R.string.goodsout_state_packed,
                R.string.goodsout_state_finalised,
                R.string.goodsout_state_dispatched,
            ).map { stringResource(it) }
            AikuCard(modifier = Modifier.fakeData(), padding = 14.dp) {
                GoodsStateStepper(steps = steps, currentIndex = PACKING_STATE_SAMPLE)
            }
            PackingParcels(
                parcels = parcels,
                onAddParcel = { parcels.add(packingNewParcelSample) },
            )
            PackingShipperCard()
        }
    }
}

@Composable
private fun PackingParcels(parcels: List<PackingParcel>, onAddParcel: () -> Unit) {
    val colors = LocalAikuColors.current
    val itemsPicked = parcels.sumOf { it.itemCount }
    val activeIndex = parcels.indexOfFirst { it.weightKg == null }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fakeData()) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 4.dp),
        ) {
            Text(
                text = stringResource(R.string.goodsout_parcels),
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            Text(
                text = pluralStringResource(R.plurals.goodsout_items_picked, itemsPicked, itemsPicked),
                fontSize = 13.sp,
                color = colors.textTertiary,
            )
        }
        parcels.forEachIndexed { index, parcel ->
            PackingParcelCard(number = index + 1, parcel = parcel, isActive = index == activeIndex)
        }
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .clip(ControlShape)
                .packingDashedBorder(color = colors.faint, cornerRadius = 8.dp)
                .clickable(role = Role.Button, onClick = onAddParcel),
        ) {
            Text(
                text = stringResource(R.string.goodsout_add_parcel),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.textSecondary,
            )
        }
    }
}

@Composable
private fun PackingParcelCard(number: Int, parcel: PackingParcel, isActive: Boolean) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .then(if (isActive) Modifier.goodsFocusGlow(color = colors.primary.copy(alpha = 0.2f), cornerRadius = 12.dp) else Modifier)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, if (isActive) colors.primary else colors.border, CardShape)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = stringResource(R.string.goodsout_parcel, number),
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            Text(
                text = pluralStringResource(R.plurals.goodsout_parcel_items, parcel.itemCount, parcel.itemCount),
                fontSize = 13.sp,
                fontWeight = FontWeight.Medium,
                color = colors.textTertiary,
            )
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            PackingField(
                label = stringResource(R.string.goodsout_weight_kg),
                value = parcel.weightKg,
                placeholder = stringResource(R.string.goodsout_enter_weight),
                isFocused = isActive && parcel.weightKg == null,
                modifier = Modifier.weight(1f),
            )
            PackingField(
                label = stringResource(R.string.goodsout_box_size),
                value = parcel.boxSize,
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun PackingField(
    label: String,
    value: String?,
    modifier: Modifier = Modifier,
    placeholder: String = "",
    isFocused: Boolean = false,
) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(6.dp), modifier = modifier) {
        Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.Medium, color = colors.textSecondary)
        Box(
            contentAlignment = Alignment.CenterStart,
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp)
                .clip(ControlShape)
                .border(1.dp, if (isFocused) colors.primary else colors.borderStrong, ControlShape)
                .padding(horizontal = 12.dp),
        ) {
            if (value != null) {
                Text(text = value, fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = colors.text, maxLines = 1)
            } else {
                Text(text = placeholder, fontSize = 16.sp, color = colors.faint, maxLines = 1)
            }
        }
    }
}

@Composable
private fun PackingShipperCard() {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .fakeData()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(14.dp),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_goodsout_shipper),
            contentDescription = null,
            tint = colors.link,
            modifier = Modifier.size(22.dp),
        )
        Column(modifier = Modifier.weight(1f)) {
            Text(text = stringResource(R.string.goodsout_shipper), fontSize = 12.sp, color = colors.textTertiary)
            Text(text = PACKING_SHIPPER_SAMPLE, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
        }
        Text(
            text = stringResource(R.string.goodsout_change),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.link,
            modifier = Modifier
                .clip(ChipShape)
                .clickable(role = Role.Button, onClick = {})
                .padding(horizontal = 6.dp, vertical = 8.dp),
        )
    }
}

private fun Modifier.packingDashedBorder(color: Color, cornerRadius: Dp): Modifier = drawBehind {
    val strokeWidth = 1.dp.toPx()
    drawRoundRect(
        color = color,
        topLeft = Offset(strokeWidth / 2, strokeWidth / 2),
        size = Size(size.width - strokeWidth, size.height - strokeWidth),
        cornerRadius = CornerRadius(cornerRadius.toPx()),
        style = Stroke(width = strokeWidth, pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 4.dp.toPx()))),
    )
}

@Preview(widthDp = 390, heightDp = 1100)
@Composable
private fun PackingScreenPreview() {
    AikuDroidTheme {
        PackingScreen(reference = "DN-24817")
    }
}
