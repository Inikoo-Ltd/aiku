package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.FamilyStatusCount
import io.aiku.droid.ui.theme.LocalAikuColors

private val TileShape = RoundedCornerShape(10.dp)
private const val STATUS_COLUMNS = 2

internal data class StatusLook(@DrawableRes val icon: Int, val tint: Color)

private val Red700 = Color(0xFFB91C1C)
private val Red500 = Color(0xFFEF4444)
private val Orange500 = Color(0xFFF97316)
private val Amber500 = Color(0xFFF59E0B)
private val Yellow500 = Color(0xFFEAB308)
private val Green500 = Color(0xFF22C55E)
private val Blue500 = Color(0xFF3B82F6)
private val Gray500 = Color(0xFF6B7280)

internal fun skoStatusLook(key: String): StatusLook = when (key) {
    "active" -> StatusLook(R.drawable.ic_fa_check_circle, Green500)
    "discontinuing" -> StatusLook(R.drawable.ic_fa_exclamation_circle, Yellow500)
    "discontinued" -> StatusLook(R.drawable.ic_fa_times_circle, Red500)
    else -> StatusLook(R.drawable.ic_fa_pause_circle, Gray500)
}

internal fun quantityStatusLook(key: String): StatusLook = when (key) {
    "out" -> StatusLook(R.drawable.ic_fa_times_circle, Red700)
    "w1" -> StatusLook(R.drawable.ic_fa_skull_crossbones, Red500)
    "w2" -> StatusLook(R.drawable.ic_fa_skull_crossbones, Orange500)
    "w3" -> StatusLook(R.drawable.ic_fa_exclamation_triangle, Amber500)
    "w4" -> StatusLook(R.drawable.ic_fa_exclamation_triangle, Yellow500)
    "ok" -> StatusLook(R.drawable.ic_fa_dot_circle, Green500)
    "excess" -> StatusLook(R.drawable.ic_fa_arrow_alt_circle_up, Blue500)
    else -> StatusLook(R.drawable.ic_fa_arrow_alt_circle_up, Gray500)
}

@Composable
internal fun FamilyStatusSections(skoStatus: List<FamilyStatusCount>, quantityStatus: List<FamilyStatusCount>) {
    if (skoStatus.isNotEmpty()) {
        InventorySectionHeading(text = stringResource(R.string.inventory_sko_status_heading))
        StatusGrid(items = skoStatus) { item, cellModifier -> SkoStatusTile(item = item, look = skoStatusLook(item.key), modifier = cellModifier) }
    }
    if (quantityStatus.isNotEmpty()) {
        InventorySectionHeading(text = stringResource(R.string.inventory_quantity_status_heading))
        StatusGrid(items = quantityStatus) { item, cellModifier -> QuantityStatusRow(item = item, look = quantityStatusLook(item.key), modifier = cellModifier) }
    }
}

@Composable
private fun StatusGrid(items: List<FamilyStatusCount>, cell: @Composable (FamilyStatusCount, Modifier) -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
        items.chunked(STATUS_COLUMNS).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                row.forEach { item -> cell(item, Modifier.weight(1f).fillMaxHeight()) }
                repeat(STATUS_COLUMNS - row.size) { Spacer(modifier = Modifier.weight(1f)) }
            }
        }
    }
}

@Composable
private fun SkoStatusTile(item: FamilyStatusCount, look: StatusLook, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .clip(TileShape)
            .background(colors.surface)
            .border(1.dp, colors.border, TileShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Icon(painter = painterResource(look.icon), contentDescription = null, tint = look.tint, modifier = Modifier.size(26.dp))
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = item.count.toString(), fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1)
            Text(text = item.label.ifBlank { item.key }, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
    }
}

@Composable
private fun QuantityStatusRow(item: FamilyStatusCount, look: StatusLook, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .heightIn(min = 48.dp)
            .clip(TileShape)
            .background(colors.surface)
            .border(1.dp, colors.border, TileShape)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Icon(painter = painterResource(look.icon), contentDescription = null, tint = look.tint, modifier = Modifier.size(18.dp))
        Text(
            text = item.label.ifBlank { item.key },
            fontSize = 14.sp,
            color = colors.textSecondary,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        Text(text = item.count.toString(), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
    }
}
