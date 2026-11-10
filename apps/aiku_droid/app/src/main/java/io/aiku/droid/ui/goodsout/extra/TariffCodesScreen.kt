package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.CustomsFileRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.NotDesignedRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun TariffCodesScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        TariffCodesContent(reference = reference)
    }
}

@Composable
private fun TariffCodesContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var languageIndex by remember { mutableIntStateOf(0) }
    val lines = GoodsOutExtraSamples.tariffLines
    val missingCodes = lines.filter { it.tariffCode == null }.map { it.skoCode }
    val editTradeUnitTitle = stringResource(R.string.goodsout_extra_edit_trade_unit)

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = stringResource(R.string.goodsout_extra_tariff_title),
                kicker = stringResource(R.string.goodsout_extra_delivery_note_reference, reference),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = if (can.pickAndPack) {
            {
                AikuButton(
                    text = stringResource(R.string.goodsout_extra_export_customs_file),
                    onClick = { navigator.open(CustomsFileRoute(reference)) },
                    icon = R.drawable.ic_ui_document,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        } else {
            null
        },
    ) {
        if (missingCodes.isNotEmpty()) {
            MissingTariffBanner(codes = missingCodes.joinToString(", "), modifier = Modifier.fakeData())
        }
        FieldLabel(text = stringResource(R.string.goodsout_extra_description_language))
        SegmentTabs(
            options = GoodsOutExtraSamples.descriptionLanguages,
            selectedIndex = languageIndex,
            onSelect = { languageIndex = it },
            fontSize = 12.sp,
            segmentHeight = 34.dp,
            modifier = Modifier.fakeData(cornerRadius = 10.dp),
        )
        ListGroup(modifier = Modifier.fakeData()) {
            lines.forEachIndexed { index, line ->
                if (index > 0) {
                    RowDivider()
                }
                val tariffCode = line.tariffCode
                ExtraRow(
                    title = line.skoCode,
                    subtitle = if (tariffCode == null) {
                        stringResource(R.string.goodsout_extra_tariff_missing_line, line.origin)
                    } else {
                        stringResource(R.string.goodsout_extra_tariff_line, tariffCode, line.origin, line.name.orEmpty())
                    },
                    leading = { ProductPhoto(code = line.skoCode) },
                    chip = if (tariffCode == null) stringResource(R.string.goodsout_extra_tariff_missing) else null,
                    chipTone = Tone.Danger,
                    trailing = {
                        if (tariffCode == null) {
                            RowLink(text = editTradeUnitTitle, onClick = { navigator.open(NotDesignedRoute(editTradeUnitTitle)) })
                        } else if (line.isDangerous) {
                            StatusBadge(text = stringResource(R.string.goodsout_extra_dangerous_goods), tone = Tone.Warn)
                        }
                    },
                )
            }
        }
        HintText(text = stringResource(R.string.goodsout_extra_tariff_hint))
    }
}

@Composable
private fun MissingTariffBanner(codes: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val title = stringResource(R.string.goodsout_extra_tariff_missing_title)
    val body = stringResource(R.string.goodsout_extra_tariff_missing_body, codes)

    Text(
        text = buildAnnotatedString {
            withStyle(SpanStyle(fontWeight = FontWeight.Bold)) { append(title) }
            append(" ")
            append(body)
        },
        fontSize = 13.sp,
        lineHeight = 19.sp,
        color = colors.dangerInk,
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.dangerSoft)
            .border(1.dp, colors.dangerBorder, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    )
}
