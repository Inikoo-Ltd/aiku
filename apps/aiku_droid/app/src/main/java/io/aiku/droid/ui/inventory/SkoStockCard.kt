package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.theme.LocalAikuColors
import java.math.BigDecimal
import java.math.RoundingMode
import kotlin.math.abs
import kotlin.math.roundToInt

private const val UNIT_ROUNDING = 0.05
private const val UNIT_DECIMALS = 1
private val CardShape = RoundedCornerShape(14.dp)

internal fun skoUnits(quantity: Double, packedIn: Double?): Double? {
    val perSko = packedIn?.takeIf { it > 1.0 } ?: return null
    if (quantity < 0) return null
    val units = quantity * perSko
    val nearest = units.roundToInt().toDouble()
    return if (abs(units - nearest) < UNIT_ROUNDING) nearest else BigDecimal.valueOf(units).setScale(UNIT_DECIMALS, RoundingMode.HALF_UP).toDouble()
}

private fun plainCount(value: Double): String = BigDecimal.valueOf(value).stripTrailingZeros().toPlainString()

private fun pluralQuantityOf(value: Double): Int = if (value == value.toInt().toDouble()) value.toInt() else 2

@Composable
internal fun unitsTotalText(units: Double): String = pluralStringResource(R.plurals.inventory_stock_units, pluralQuantityOf(units), plainCount(units))

data class SkoStockFigure(val label: String, val value: String)

data class SkoStockAction(val label: String, @DrawableRes val icon: Int, val onClick: () -> Unit, val style: ButtonStyle = ButtonStyle.Outline)

@Composable
internal fun SkoStockCard(
    quantity: Double,
    quantityLabel: String?,
    packedIn: Double?,
    figures: List<SkoStockFigure>,
    modifier: Modifier = Modifier,
    actions: List<SkoStockAction> = emptyList(),
) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape),
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.padding(16.dp)) {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.weight(1f)) {
                Text(
                    text = stringResource(R.string.inventory_stat_in_locations).uppercase(),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 0.72.sp,
                    color = colors.textTertiary,
                )
                Text(text = stockText(quantity, quantityLabel), fontSize = 32.sp, fontWeight = FontWeight.Bold, color = colors.text)
                skoUnits(quantity, packedIn)?.let { units ->
                    Text(text = unitsTotalText(units), fontSize = 13.sp, color = colors.textTertiary)
                }
            }
            if (actions.isNotEmpty()) {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.width(IntrinsicSize.Max)) {
                    actions.forEach { action ->
                        AikuButton(
                            text = action.label,
                            onClick = action.onClick,
                            style = action.style,
                            icon = action.icon,
                            height = 40.dp,
                            modifier = Modifier.fillMaxWidth(),
                        )
                    }
                }
            }
        }
        figures.forEach { figure ->
            RowDivider()
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp).padding(horizontal = 16.dp, vertical = 10.dp),
            ) {
                Text(text = figure.label, fontSize = 14.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
                Text(text = figure.value, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.End)
            }
        }
    }
}
