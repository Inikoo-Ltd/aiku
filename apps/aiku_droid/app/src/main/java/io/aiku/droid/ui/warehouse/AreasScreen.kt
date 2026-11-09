package io.aiku.droid.ui.warehouse

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.AreaDetailRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun AreasScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val title = stringResource(R.string.warehouse_areas_title)

    RequireAbility(isAllowed = can.viewLocations, title = title) {
        AppScreen(
            header = {
                AppHeader(
                    title = title,
                    kicker = stringResource(R.string.warehouse_kicker_locations),
                    below = { WarehouseSectionTabs(active = WarehouseSection.Areas) },
                )
            },
            spacing = 10.dp,
        ) {
            Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
                Text(
                    text = stringResource(R.string.warehouse_sorted_by_code),
                    fontSize = 12.sp,
                    color = colors.textTertiary,
                    modifier = Modifier.weight(1f),
                )
                Text(text = stringResource(R.string.warehouse_locations_stock_value), fontSize = 12.sp, color = colors.textTertiary)
            }
            ListGroup(modifier = Modifier.fakeData()) {
                WarehouseSampleAreas.forEachIndexed { index, area ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val locations = stringResource(R.string.warehouse_location_count, area.locationCount)
                    ListRow(
                        title = area.name,
                        subtitle = area.usedPercent?.let { percent ->
                            stringResource(R.string.warehouse_area_summary, locations, stringResource(R.string.warehouse_percent_used, percent))
                        } ?: locations,
                        leading = { AreaCodeTile(area) },
                        trailingValue = area.stockValue,
                        onClick = { navigator.open(AreaDetailRoute(area.code)) },
                    )
                }
            }
        }
    }
}

@Composable
private fun AreaCodeTile(area: WarehouseSampleArea) {
    val toneColors = LocalAikuColors.current.tone(if (area.isNearlyFull) Tone.Warn else Tone.Primary)

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(40.dp)
            .clip(ControlShape)
            .background(toneColors.container),
    ) {
        Text(text = area.code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = toneColors.content, maxLines = 1)
    }
}

@Preview(heightDp = 1000)
@Composable
private fun AreasScreenPreview() {
    AikuDroidTheme {
        AreasScreen()
    }
}
