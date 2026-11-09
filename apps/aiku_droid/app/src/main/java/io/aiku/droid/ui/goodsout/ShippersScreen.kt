package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsin.GoodsCaptionRow
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private data class ShipperSample(
    val name: String,
    val collection: String,
    val parcelsReady: Int,
    val collectsToday: Boolean,
)

private val shippersSample = listOf(
    ShipperSample("[Shipper A]", "Collection 16:00 · Dispatch bay 1", parcelsReady = 48, collectsToday = true),
    ShipperSample("[Shipper B]", "Collection 17:30 · Dispatch bay 2", parcelsReady = 21, collectsToday = true),
    ShipperSample("[Pallet carrier]", "No collection today", parcelsReady = 0, collectsToday = false),
)

@Composable
fun ShippersScreen() {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val title = stringResource(R.string.goodsout_title)

    RequireAbility(isAllowed = can.viewGoodsOut, title = title, activeTab = AppTab.GoodsOut) {
        AppScreen(
            header = { AppHeader(title = title, below = { GoodsOutSectionTabs(active = GoodsOutSection.Shippers) }) },
            activeTab = AppTab.GoodsOut,
            spacing = 10.dp,
        ) {
            GoodsCaptionRow(
                start = stringResource(R.string.goodsout_todays_collections),
                end = stringResource(R.string.goodsout_parcels_ready),
            )
            ListGroup(modifier = Modifier.fakeData()) {
                shippersSample.forEachIndexed { index, shipper ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ShipperRow(shipper = shipper)
                }
            }
            Text(
                text = stringResource(R.string.goodsout_shippers_note),
                fontSize = 12.sp,
                lineHeight = 18.sp,
                color = colors.textTertiary,
                modifier = Modifier.padding(4.dp),
            )
        }
    }
}

@Composable
private fun ShipperRow(shipper: ShipperSample) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        IconTile(
            icon = R.drawable.ic_goodsout_shipper,
            tone = if (shipper.collectsToday) Tone.Primary else Tone.Neutral,
            size = 40.dp,
            iconSize = 20.dp,
        )
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = shipper.name,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(text = shipper.collection, fontSize = 13.sp, color = colors.textTertiary)
        }
        Text(
            text = shipper.parcelsReady.toString(),
            fontSize = 17.sp,
            fontWeight = FontWeight.Bold,
            color = if (shipper.collectsToday) colors.text else colors.textQuaternary,
        )
    }
}

@Preview(widthDp = 390, heightDp = 900)
@Composable
private fun ShippersScreenPreview() {
    AikuDroidTheme {
        ShippersScreen()
    }
}
