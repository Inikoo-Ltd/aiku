package io.aiku.droid.ui.warehouse

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SectionTitle
import io.aiku.droid.ui.components.Stat
import io.aiku.droid.ui.components.StatGrid
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private data class AreaLocation(
    val code: String,
    val skuCount: Int,
    val volume: String,
    val stockValue: String,
    val isEmpty: Boolean = false,
)

private val SampleAreaLocations = listOf(
    AreaLocation(code = "A-01-01", skuCount = 5, volume = "0.6", stockValue = "£1,210"),
    AreaLocation(code = "A-03-02", skuCount = 6, volume = "0.8", stockValue = "£2,140"),
    AreaLocation(code = "A-07-01", skuCount = 3, volume = "0.4", stockValue = "£980"),
    AreaLocation(code = "A-09-04", skuCount = 0, volume = "0", stockValue = "£0", isEmpty = true),
)

@Composable
fun AreaDetailScreen(code: String) {
    val can = LocalWarehouse.current.can
    val sampleArea = WarehouseSampleAreas.firstOrNull { it.code == code }
    val area = sampleArea ?: WarehouseSampleAreas.first().copy(code = code)
    val title = sampleArea?.name ?: stringResource(R.string.warehouse_area_title, code)

    RequireAbility(isAllowed = can.viewLocations, title = title) {
        AppScreen(
            header = {
                AppHeader(
                    title = title,
                    kicker = stringResource(R.string.warehouse_area_kicker),
                    showMenu = false,
                )
            },
        ) {
            AreaSummaryCard(area)
            AreaLocationsSection()
        }
    }
}

@Composable
private fun AreaSummaryCard(area: WarehouseSampleArea) {
    val colors = LocalAikuColors.current

    AikuCard(modifier = Modifier.fakeData(), spacing = 12.dp) {
        StatGrid(
            stats = listOf(
                Stat(label = stringResource(R.string.warehouse_area_stat_locations), value = area.locationCount),
                Stat(label = stringResource(R.string.warehouse_area_stat_empty), value = area.emptyCount),
                Stat(label = stringResource(R.string.warehouse_area_stat_stock_value), value = area.stockValue),
            ),
        )
        area.usedPercent?.let { usedPercent ->
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Row(modifier = Modifier.fillMaxWidth()) {
                    Text(
                        text = stringResource(R.string.warehouse_capacity_used),
                        fontSize = 12.sp,
                        color = colors.textTertiary,
                        modifier = Modifier.weight(1f),
                    )
                    Text(
                        text = stringResource(R.string.warehouse_percent, usedPercent),
                        fontSize = 12.sp,
                        color = if (area.isNearlyFull) colors.warn else colors.textTertiary,
                    )
                }
                ProgressBar(
                    fraction = usedPercent / 100f,
                    height = 8.dp,
                    tone = if (area.isNearlyFull) Tone.Warn else Tone.Primary,
                )
            }
        }
    }
}

@Composable
private fun AreaLocationsSection() {
    val navigator = LocalAppNavigator.current

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        SectionTitle(text = stringResource(R.string.warehouse_area_locations_title), icon = R.drawable.ic_section_locations)
        ListGroup(modifier = Modifier.fakeData()) {
            SampleAreaLocations.forEachIndexed { index, location ->
                if (index > 0) {
                    RowDivider()
                }
                ListRow(
                    title = location.code,
                    subtitle = if (location.isEmpty) {
                        stringResource(R.string.warehouse_location_put_away)
                    } else {
                        stringResource(R.string.warehouse_location_contents, location.skuCount, location.volume)
                    },
                    badges = {
                        if (location.isEmpty) {
                            StatusBadge(text = stringResource(R.string.warehouse_location_empty), tone = Tone.Neutral)
                        }
                    },
                    trailingValue = location.stockValue,
                    showChevron = false,
                    onClick = { navigator.open(LocationDetailRoute(location.code)) },
                )
            }
        }
    }
}

@Preview(heightDp = 900)
@Composable
private fun AreaDetailScreenPreview() {
    AikuDroidTheme {
        AreaDetailScreen(code = "A")
    }
}
