package io.aiku.droid.ui.inventory

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.OrgStockLocation
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.recordDate
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.locations.stockTypeLabel
import io.aiku.droid.ui.locations.stockTypeTone
import io.aiku.droid.ui.theme.LocalAikuColors

private val IconShape = RoundedCornerShape(10.dp)

@Composable
internal fun SkoLocationCard(location: OrgStockLocation, packedIn: Double?, onClick: (() -> Unit)?, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val units = skoUnits(location.quantity, packedIn)
    val footer = listOfNotNull(defaultChannelsText(location), stockLevelsText(location))

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
        ) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(40.dp).clip(IconShape).background(colors.soft)) {
                Icon(painter = painterResource(R.drawable.ic_ui_location), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = location.location.code, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(text = auditedText(location), fontSize = 13.sp, color = colors.textTertiary)
            }
            stockTypeLabel(location.stockType)?.let { label ->
                StatusBadge(text = stringResource(label), tone = stockTypeTone(location.stockType))
            }
        }
        RowDivider()
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp),
        ) {
            Text(
                text = stockText(location.quantity, location.quantityLabel),
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            units?.let { Text(text = unitsTotalText(it), fontSize = 13.sp, color = colors.textTertiary) }
        }
        if (footer.isNotEmpty()) {
            RowDivider()
            Column(
                verticalArrangement = Arrangement.spacedBy(4.dp),
                modifier = Modifier.fillMaxWidth().background(colors.soft.copy(alpha = 0.35f)).padding(horizontal = 14.dp, vertical = 10.dp),
            ) {
                footer.forEach { line -> Text(text = line, fontSize = 13.sp, color = colors.textSecondary) }
            }
        }
    }
}

@Composable
private fun auditedText(location: OrgStockLocation): String =
    recordDate(location.auditedAt, organisationZone())?.let { stringResource(R.string.inventory_audited_on, it) } ?: stringResource(R.string.inventory_never_audited)

@Composable
private fun defaultChannelsText(location: OrgStockLocation): String? {
    val channels = listOfNotNull(
        if (location.isDefaultWholesale) stringResource(R.string.inventory_channel_wholesale) else null,
        if (location.isDefaultDropshipping) stringResource(R.string.inventory_channel_dropshipping) else null,
    )
    return channels.takeIf { it.isNotEmpty() }?.let { stringResource(R.string.inventory_default_for, it.joinToString(LIST_SEPARATOR)) }
}

@Composable
private fun stockLevelsText(location: OrgStockLocation): String? {
    val min = location.minStock
    val max = location.maxStock
    val replenish = location.replenishmentStock
    return when {
        min != null && max != null -> stringResource(R.string.inventory_min_max, stockText(min), stockText(max))
        min != null -> stringResource(R.string.inventory_min, stockText(min))
        max != null -> stringResource(R.string.inventory_max, stockText(max))
        replenish != null -> stringResource(R.string.inventory_replenish, stockText(replenish))
        else -> null
    }
}
