package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

private data class StateCount(val label: String, val value: Int, val container: Color, val content: Color)

@Composable
fun GoodsInBacklogScreen() {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val sources = GoodsInSamples.hubSources.filter { !it.isFulfilment || can.viewFulfilment }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.GoodsInBacklog, title = stringResource(R.string.goodsin_hub_title)) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            sources.forEach { source ->
                SourceCard(source = source, onOpen = { navigator.switchSection(source.route) })
            }
            HelperNote(text = stringResource(R.string.goodsin_hub_note))
        }
    }
}

@Composable
private fun SourceCard(source: SourceCounts, onOpen: () -> Unit) {
    val colors = LocalAikuColors.current
    val counts = listOf(
        StateCount(label = stringResource(R.string.goodsin_state_arriving), value = source.arriving, container = colors.background, content = colors.textSecondary),
        StateCount(label = stringResource(R.string.sd_state_received), value = source.received, container = colors.soft, content = colors.link),
        StateCount(label = stringResource(R.string.sd_state_checked), value = source.checked, container = colors.soft, content = colors.link),
        StateCount(label = stringResource(R.string.sd_state_booking_in), value = source.bookingIn, container = colors.soft, content = colors.link),
        StateCount(label = stringResource(R.string.goodsin_booked_in_7d), value = source.bookedInWeek, container = colors.successSoft, content = colors.success),
    )

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .fakeData()
            .clip(CardShape)
            .background(colors.surface)
            .border(width = 1.dp, color = colors.border, shape = CardShape)
            .clickable(role = Role.Button, onClick = onOpen)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
            Text(text = stringResource(source.source), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
            Icon(painter = painterResource(R.drawable.ic_ui_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
        }
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            counts.forEach { count ->
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(2.dp),
                    modifier = Modifier
                        .weight(1f)
                        .clip(ControlShape)
                        .background(count.container)
                        .padding(horizontal = 2.dp, vertical = 8.dp),
                ) {
                    Text(text = count.value.toString(), fontSize = 17.sp, fontWeight = FontWeight.Bold, color = count.content)
                    Text(
                        text = count.label,
                        fontSize = 10.sp,
                        lineHeight = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = colors.textTertiary,
                        textAlign = TextAlign.Center,
                    )
                }
            }
        }
    }
}
