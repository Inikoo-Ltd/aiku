package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
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
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SegmentedControl
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.NotDesignedRoute
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private data class BacklogTile(val label: String, val count: Int, val background: Color, val content: Color)

@Composable
fun GoodsOutBacklogScreen() {
    val navigator = LocalAppNavigator.current
    val segments = listOf(
        stringResource(R.string.goodsout_backlog_delivery_notes),
        stringResource(R.string.goodsout_backlog_sessions),
        stringResource(R.string.goodsout_backlog_pickers),
        stringResource(R.string.goodsout_backlog_packers),
    )

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                ModuleHeader(
                    section = WarehouseSection.GoodsOutBacklog,
                    title = stringResource(R.string.goodsout_backlog_title),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            SegmentedControl(
                options = segments,
                selectedIndex = 0,
                onSelect = { index -> if (index != 0) navigator.open(NotDesignedRoute(title = segments[index])) },
            )
            GoodsOutSamples.backlogChannels.forEach { channel ->
                BacklogChannelCard(channel = channel, onOpen = { navigator.switchSection(DeliveryNotesRoute) })
            }
            GoodsOutHeading(title = stringResource(R.string.goodsout_backlog_pickers_now))
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutSamples.pickersNow.forEachIndexed { index, picker ->
                    if (index > 0) {
                        RowDivider()
                    }
                    IconInfoRow(
                        icon = R.drawable.ic_ui_person,
                        title = picker.name,
                        subtitle = picker.detail,
                        tone = Tone.Primary,
                        onClick = picker.sessionReference?.let { reference -> { navigator.open(PickingSessionRoute(reference = reference)) } },
                    )
                }
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_backlog_note))
        }
    }
}

@Composable
private fun BacklogChannelCard(channel: SampleBacklogChannel, onOpen: () -> Unit) {
    val colors = LocalAikuColors.current
    val neutral = colors.background to colors.textSecondary
    val active = colors.soft to colors.link
    val waiting = if (channel.waiting > 0) colors.warnSoft to colors.warn else neutral
    val finalised = colors.successSoft to colors.success
    val tiles = listOf(
        BacklogTile(stringResource(R.string.dn_state_to_do), channel.toDo, neutral.first, neutral.second),
        BacklogTile(stringResource(R.string.dn_state_picking), channel.picking, active.first, active.second),
        BacklogTile(stringResource(R.string.dn_state_waiting), channel.waiting, waiting.first, waiting.second),
        BacklogTile(stringResource(R.string.dn_state_picked), channel.picked, active.first, active.second),
        BacklogTile(stringResource(R.string.dn_state_packing), channel.packing, active.first, active.second),
        BacklogTile(stringResource(R.string.dn_state_packed), channel.packed, active.first, active.second),
        BacklogTile(stringResource(R.string.dn_state_finalised), channel.finalised, finalised.first, finalised.second),
        BacklogTile(stringResource(R.string.goodsout_backlog_total), channel.total, neutral.first, neutral.second),
    )

    AikuCard(modifier = Modifier.fakeData(), padding = 14.dp, spacing = 10.dp) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 44.dp)
                .clip(RoundedCornerShape(6.dp))
                .clickable(role = Role.Button, onClick = onOpen),
        ) {
            Text(text = channel.name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
            Icon(painter = painterResource(R.drawable.ic_ui_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
        }
        tiles.chunked(4).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                row.forEach { tile ->
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(2.dp),
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(tile.background)
                            .clickable(role = Role.Button, onClick = onOpen)
                            .padding(horizontal = 2.dp, vertical = 8.dp),
                    ) {
                        Text(text = tile.count.toString(), fontSize = 17.sp, fontWeight = FontWeight.Bold, color = tile.content)
                        Text(text = tile.label, fontSize = 10.sp, fontWeight = FontWeight.SemiBold, color = colors.textTertiary, maxLines = 1)
                    }
                }
            }
        }
    }
}
