package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

private val RowColumnWidth = 48.dp
private val DescriptionColumnWidth = 200.dp
private val TariffColumnWidth = 80.dp
private val OriginColumnWidth = 64.dp
private val QuantityColumnWidth = 48.dp
private val ValueColumnWidth = 80.dp
private const val MISSING_VALUE = "—"

@Composable
fun CustomsFileScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        CustomsFileContent(reference = reference)
    }
}

@Composable
private fun CustomsFileContent(reference: String) {
    val can = LocalWarehouse.current.can
    val toasts = LocalToasts.current
    val downloadMessage = stringResource(R.string.goodsout_extra_customs_downloading)

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = stringResource(R.string.goodsout_extra_customs_title),
                kicker = stringResource(R.string.goodsout_extra_delivery_note_reference, reference),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = if (can.pickAndPack) {
            {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_download),
                    onClick = { toasts.show(downloadMessage, ToastKind.Success) },
                    icon = R.drawable.ic_ui_document,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        } else {
            null
        },
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp)) {
            FileSetting(label = stringResource(R.string.goodsout_extra_customs_format), value = GoodsOutExtraSamples.CUSTOMS_FORMAT, modifier = Modifier.weight(1f))
            FileSetting(label = stringResource(R.string.goodsout_extra_customs_field_delimiter), value = GoodsOutExtraSamples.CUSTOMS_FIELD_DELIMITER, modifier = Modifier.weight(1f))
            FileSetting(label = stringResource(R.string.goodsout_extra_customs_text_delimiter), value = GoodsOutExtraSamples.CUSTOMS_TEXT_DELIMITER, modifier = Modifier.weight(1f))
        }
        SectionHeading(text = stringResource(R.string.goodsout_extra_preview))
        CustomsPreviewTable(modifier = Modifier.fakeData())
        HintText(text = stringResource(R.string.goodsout_extra_customs_hint))
    }
}

@Composable
private fun FileSetting(label: String, value: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = modifier
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, colors.borderStrong, ControlShape)
            .padding(horizontal = 10.dp, vertical = 8.dp),
    ) {
        Text(text = label, fontSize = 11.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
        Text(text = value, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text, maxLines = 1)
    }
}

@Composable
private fun CustomsPreviewTable(modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val widths = listOf(RowColumnWidth, DescriptionColumnWidth, TariffColumnWidth, OriginColumnWidth, QuantityColumnWidth, ValueColumnWidth)
    val headers = listOf(
        stringResource(R.string.goodsout_extra_customs_column_row),
        stringResource(R.string.goodsout_extra_customs_column_description),
        stringResource(R.string.goodsout_extra_customs_column_tariff),
        stringResource(R.string.goodsout_extra_customs_column_origin),
        stringResource(R.string.goodsout_extra_customs_column_quantity),
        stringResource(R.string.goodsout_extra_customs_column_value),
    )
    val rows = GoodsOutExtraSamples.customsRows
    val tableWidth = widths.fold(0.dp) { total, width -> total + width }

    Box(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .horizontalScroll(rememberScrollState()),
    ) {
        Column {
            Row {
                headers.forEachIndexed { index, header ->
                    TableCell(text = header, width = widths[index], color = colors.textTertiary, isBold = true)
                }
            }
            HorizontalDivider(thickness = 1.dp, color = colors.border, modifier = Modifier.width(tableWidth))
            rows.forEachIndexed { rowIndex, row ->
                if (rowIndex > 0) {
                    HorizontalDivider(thickness = 1.dp, color = colors.divider, modifier = Modifier.width(tableWidth))
                }
                Row {
                    TableCell(text = row.row.toString(), width = widths[0], color = colors.text)
                    TableCell(text = row.description, width = widths[1], color = colors.text)
                    TableCell(
                        text = row.tariffCode ?: MISSING_VALUE,
                        width = widths[2],
                        color = if (row.tariffCode == null) colors.danger else colors.text,
                        isBold = row.tariffCode == null,
                    )
                    TableCell(text = row.origin, width = widths[3], color = colors.text)
                    TableCell(text = row.quantity.toString(), width = widths[4], color = colors.text)
                    TableCell(text = row.value, width = widths[5], color = colors.text)
                }
            }
        }
    }
}

@Composable
private fun TableCell(text: String, width: Dp, color: Color, isBold: Boolean = false) {
    Text(
        text = text,
        fontSize = 12.sp,
        fontWeight = if (isBold) FontWeight.Bold else FontWeight.Normal,
        color = color,
        modifier = Modifier.width(width).padding(horizontal = 10.dp, vertical = 8.dp),
    )
}
