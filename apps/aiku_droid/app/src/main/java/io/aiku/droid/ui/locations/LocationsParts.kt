package io.aiku.droid.ui.locations

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.LocationStockType
import io.aiku.droid.data.api.LocationSummary
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.inventory.stockText
import io.aiku.droid.ui.theme.LocalAikuColors

internal const val LIST_SEPARATOR = " · "

private val TileShape = RoundedCornerShape(10.dp)

internal enum class LocationKind(@StringRes val label: Int, val tone: Tone) {
    Stocks(R.string.locations_kind_stocks, Tone.Primary),
    Fulfilment(R.string.locations_kind_fulfilment, Tone.Neutral),
    Dropshipping(R.string.locations_scope_dropshipping, Tone.Neutral),
    Broken(R.string.locations_status_broken, Tone.Danger),
}

internal fun LocationSummary.kind(): LocationKind? = when {
    isBroken -> LocationKind.Broken
    allowStocks -> LocationKind.Stocks
    allowFulfilment -> LocationKind.Fulfilment
    allowDropshipping -> LocationKind.Dropshipping
    else -> null
}

@StringRes
internal fun stockTypeLabel(type: LocationStockType?): Int? = when (type) {
    LocationStockType.Picking -> R.string.locations_type_picking
    LocationStockType.Storing -> R.string.locations_type_storing
    null -> null
}

internal fun stockTypeTone(type: LocationStockType?): Tone = if (type == LocationStockType.Picking) Tone.Primary else Tone.Neutral

@Composable
internal fun statusLabel(location: LocationSummary): String =
    stringResource(if (location.isBroken) R.string.locations_status_broken else R.string.locations_status_operational)

@Composable
internal fun capacityText(maxWeight: Double?, maxVolume: Double?): String? {
    val parts = listOfNotNull(
        maxWeight?.let { stringResource(R.string.locations_weight_kg, stockText(it)) },
        maxVolume?.let { stringResource(R.string.locations_volume_m3, stockText(it)) },
    )
    return parts.takeIf { it.isNotEmpty() }?.let { stringResource(R.string.locations_max, it.joinToString(LIST_SEPARATOR)) }
}

@Composable
internal fun slotsText(location: LocationSummary): String? = location.numberOrgStockSlots.takeIf { it > 0 }?.let { slots ->
    pluralStringResource(R.plurals.locations_slots, slots, slots, location.numberEmptyStockSlots)
}

@Composable
internal fun locationSubtitle(location: LocationSummary, showArea: Boolean = true): String {
    val slots = slotsText(location)
    val pallets = location.numberPallets.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.locations_pallets, it, it) }
    val empty = if (slots == null && pallets == null) stringResource(R.string.locations_empty) else null
    val area = location.area?.label?.takeIf { showArea && it.isNotBlank() }

    return listOfNotNull(area, slots, capacityText(location.maxWeight, location.maxVolume), pallets, empty).joinToString(LIST_SEPARATOR)
}

@Composable
internal fun LocationsHeading(text: String, modifier: Modifier = Modifier, count: String? = null) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.Bottom, modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp)) {
        Text(
            text = text.uppercase(),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            letterSpacing = 0.78.sp,
            color = colors.textTertiary,
            modifier = Modifier.weight(1f),
        )
        if (count != null) {
            Text(text = count, fontSize = 12.sp, color = colors.textTertiary, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
        }
    }
}

@Composable
internal fun LocationsFootnote(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        fontSize = 12.sp,
        lineHeight = 18.sp,
        color = LocalAikuColors.current.textQuaternary,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
internal fun LocationsStatTile(
    value: String,
    label: String,
    modifier: Modifier = Modifier,
    valueColor: Color? = null,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(TileShape)
            .background(colors.surface)
            .border(1.dp, colors.border, TileShape)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(12.dp),
    ) {
        Text(text = value, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = valueColor ?: colors.text, maxLines = 1)
        Text(text = label, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
internal fun LocationsStatRow(modifier: Modifier = Modifier, content: @Composable RowScope.() -> Unit) {
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = modifier.fillMaxWidth(), content = content)
}

@Composable
internal fun LocationsTileRow(
    @DrawableRes icon: Int,
    title: String,
    subtitle: String,
    modifier: Modifier = Modifier,
    tone: Tone = Tone.Primary,
    chip: String? = null,
    chipTone: Tone = Tone.Primary,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val tileColors = colors.tone(tone)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 60.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier.size(40.dp).clip(TileShape).background(tileColors.container),
        ) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = tileColors.content, modifier = Modifier.size(20.dp))
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = title,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (chip != null) {
                    StatusBadge(text = chip, tone = chipTone)
                }
            }
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
        }
    }
}

@Composable
internal fun LocationRow(location: LocationSummary, onOpen: () -> Unit, showArea: Boolean = true) {
    val kind = location.kind()

    LocationsTileRow(
        icon = R.drawable.ic_ui_location,
        title = location.code,
        subtitle = locationSubtitle(location, showArea = showArea),
        tone = if (kind == LocationKind.Broken) Tone.Danger else Tone.Primary,
        chip = kind?.let { stringResource(it.label) },
        chipTone = kind?.tone ?: Tone.Neutral,
        onClick = onOpen,
    )
}

@Composable
internal fun LocationsList(locations: List<LocationSummary>, onOpen: (LocationSummary) -> Unit, modifier: Modifier = Modifier, showArea: Boolean = true) {
    ListGroup(modifier = modifier) {
        locations.forEachIndexed { index, location ->
            if (index > 0) {
                RowDivider()
            }
            LocationRow(location = location, onOpen = { onOpen(location) }, showArea = showArea)
        }
    }
}

@Composable
internal fun TrailingCheckboxRow(
    label: String,
    checked: Boolean,
    onCheckedChange: ((Boolean) -> Unit)?,
    modifier: Modifier = Modifier,
    caption: String? = null,
    labelSize: TextUnit = 15.sp,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 48.dp)
            .then(
                if (onCheckedChange != null) {
                    Modifier.toggleable(value = checked, role = Role.Checkbox, onValueChange = onCheckedChange)
                } else {
                    Modifier
                },
            ),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = label, fontSize = labelSize, fontWeight = FontWeight.SemiBold, color = colors.text)
            if (caption != null) {
                Text(text = caption, fontSize = 12.sp, color = colors.textTertiary)
            }
        }
        if (onCheckedChange != null) {
            Checkbox(
                checked = checked,
                onCheckedChange = null,
                colors = CheckboxDefaults.colors(checkedColor = colors.primary, checkmarkColor = colors.onPrimary, uncheckedColor = colors.borderStrong),
            )
        } else {
            StatusBadge(
                text = stringResource(if (checked) R.string.locations_state_on else R.string.locations_state_off),
                tone = if (checked) Tone.Success else Tone.Neutral,
            )
        }
    }
}

@Composable
internal fun LocationChoice(
    title: String,
    isSelected: Boolean,
    onSelect: (() -> Unit)?,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    role: Role = Role.RadioButton,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clip(TileShape)
            .background(if (isSelected) colors.soft else colors.surface)
            .border(if (isSelected) 2.dp else 1.dp, if (isSelected) colors.primary else colors.border, TileShape)
            .then(if (onSelect != null) Modifier.selectable(selected = isSelected, role = role, onClick = onSelect) else Modifier)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            if (subtitle != null) {
                Text(text = subtitle, fontSize = 12.sp, color = colors.textTertiary)
            }
        }
        if (isSelected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.link, modifier = Modifier.size(18.dp))
        }
    }
}

@Composable
internal fun DangerNote(text: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Text(
        text = text,
        fontSize = 14.sp,
        color = colors.dangerInk,
        modifier = modifier
            .fillMaxWidth()
            .clip(TileShape)
            .background(colors.dangerSoft)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    )
}
