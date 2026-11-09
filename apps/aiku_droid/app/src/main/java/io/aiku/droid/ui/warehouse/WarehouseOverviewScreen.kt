package io.aiku.droid.ui.warehouse

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.IconTile
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SectionTitle
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.AreaDetailRoute
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private val SummaryTileShape = RoundedCornerShape(10.dp)
private const val OverviewAreaPreviewCount = 3

@Composable
fun WarehouseOverviewScreen() {
    val warehouse = LocalWarehouse.current

    RequireAbility(isAllowed = warehouse.can.viewLocations, title = stringResource(R.string.warehouse_title)) {
        AppScreen(
            header = {
                AppHeader(
                    title = warehouse.name,
                    kicker = stringResource(R.string.warehouse_kicker_locations),
                    below = { WarehouseSectionTabs(active = WarehouseSection.Overview) },
                )
            },
        ) {
            WarehouseSummaryCard()
            AreasPreviewSection()
        }
    }
}

@Composable
private fun WarehouseSummaryCard() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val warehouse = LocalWarehouse.current
    val summary = WarehouseSampleOverview

    AikuCard(spacing = 12.dp) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            IconTile(icon = R.drawable.ic_warehouse, tone = Tone.Primary, size = 44.dp, iconSize = 22.dp)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = warehouse.name,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
                Text(text = stringResource(R.string.warehouse_code, warehouse.code), fontSize = 13.sp, color = colors.textTertiary)
            }
        }
        Column(
            verticalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.fakeData(cornerRadius = 10.dp),
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                SummaryTile(
                    value = WarehouseSampleAreas.size.toString(),
                    label = stringResource(R.string.warehouse_stat_areas),
                    onClick = { navigator.open(AreasRoute) },
                )
                SummaryTile(
                    value = summary.locationCount,
                    label = stringResource(R.string.warehouse_stat_locations),
                    onClick = { navigator.open(LocationsRoute) },
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                SummaryTile(
                    value = summary.emptyLocationCount,
                    label = stringResource(R.string.warehouse_stat_empty_locations),
                    onClick = { navigator.open(LocationsRoute) },
                )
                SummaryTile(
                    value = summary.brokenLocationCount,
                    label = stringResource(R.string.warehouse_stat_broken),
                    onClick = { navigator.open(LocationsRoute) },
                    isAlert = true,
                )
            }
        }
    }
}

@Composable
private fun RowScope.SummaryTile(value: String, label: String, onClick: () -> Unit, isAlert: Boolean = false) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = Modifier
            .weight(1f)
            .clip(SummaryTileShape)
            .then(if (isAlert) Modifier.background(colors.dangerSoft) else Modifier)
            .border(1.dp, if (isAlert) colors.dangerBorder else colors.border, SummaryTileShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(12.dp),
    ) {
        Text(
            text = value,
            fontSize = 22.sp,
            lineHeight = 28.sp,
            fontWeight = FontWeight.Bold,
            color = if (isAlert) colors.danger else colors.text,
        )
        Text(
            text = label,
            fontSize = 12.sp,
            lineHeight = 16.sp,
            color = if (isAlert) colors.dangerInk else colors.textTertiary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
private fun AreasPreviewSection() {
    val navigator = LocalAppNavigator.current

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        SectionTitle(
            text = stringResource(R.string.warehouse_areas_title),
            actionLabel = stringResource(R.string.warehouse_view_all),
            onAction = { navigator.open(AreasRoute) },
        )
        ListGroup(modifier = Modifier.fakeData()) {
            WarehouseSampleAreas.take(OverviewAreaPreviewCount).forEachIndexed { index, area ->
                if (index > 0) {
                    RowDivider()
                }
                AreaUsageRow(area = area, onClick = { navigator.open(AreaDetailRoute(area.code)) })
            }
        }
    }
}

@Composable
private fun AreaUsageRow(area: WarehouseSampleArea, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val usedPercent = area.usedPercent ?: 0

    Column(
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                text = area.name,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            Text(
                text = stringResource(R.string.warehouse_location_count, area.locationCount),
                fontSize = 13.sp,
                color = colors.textTertiary,
            )
        }
        ProgressBar(fraction = usedPercent / 100f, tone = if (area.isNearlyFull) Tone.Warn else Tone.Primary)
        Text(
            text = if (area.isNearlyFull) {
                stringResource(R.string.warehouse_percent_used_nearly_full, usedPercent)
            } else {
                stringResource(R.string.warehouse_percent_used, usedPercent)
            },
            fontSize = 12.sp,
            lineHeight = 16.sp,
            color = if (area.isNearlyFull) colors.warn else colors.textTertiary,
        )
    }
}

@Preview(heightDp = 1000)
@Composable
private fun WarehouseOverviewScreenPreview() {
    AikuDroidTheme {
        WarehouseOverviewScreen()
    }
}
