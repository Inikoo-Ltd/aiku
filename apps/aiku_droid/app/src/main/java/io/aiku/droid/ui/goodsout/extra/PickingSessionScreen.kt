package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PickByLocationRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class SessionView(@StringRes val label: Int) {
    Items(R.string.goodsout_extra_session_view_items),
    ByLocation(R.string.goodsout_extra_session_view_by_location),
    ByDelivery(R.string.goodsout_extra_session_view_by_delivery),
}

@Composable
fun PickingSessionScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        PickingSessionContent(reference = reference)
    }
}

@Composable
private fun PickingSessionContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val staffName = LocalStaff.current.displayName
    var view by remember { mutableStateOf(SessionView.ByLocation) }

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = reference,
                kicker = listOf(stringResource(R.string.goodsout_extra_picking_session), GoodsOutExtraSamples.DROPSHIPPING).joinToString(KICKER_SEPARATOR),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
        footer = {
            if (can.pickAndPack) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    AikuButton(
                        text = stringResource(R.string.goodsout_extra_add_delivery_notes),
                        onClick = { navigator.open(DeliveryNotesRoute) },
                        style = ButtonStyle.Outline,
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                    AikuButton(
                        text = stringResource(R.string.goodsout_extra_start_picking),
                        onClick = { navigator.open(PickByLocationRoute(reference)) },
                        height = 52.dp,
                        modifier = Modifier.weight(1f),
                    )
                }
            } else {
                FooterStatus(
                    title = stringResource(SessionState.InProcess.label),
                    body = stringResource(R.string.goodsout_extra_session_status_body, staffName),
                )
            }
        },
    ) {
        SessionSummaryCard(staffName = staffName, modifier = Modifier.fakeData())
        SectionHeading(text = stringResource(R.string.section_trolleys))
        ListGroup(modifier = Modifier.fakeData()) {
            GoodsOutExtraSamples.sessionTrolleys.forEachIndexed { index, trolley ->
                if (index > 0) {
                    RowDivider()
                }
                ExtraRow(
                    title = trolley.trolley,
                    subtitle = pluralStringResource(R.plurals.goodsout_extra_delivery_notes, trolley.deliveryNotes, trolley.deliveryNotes),
                    leading = { ExtraIconTile(icon = R.drawable.ic_ui_trolley, tone = Tone.Primary) },
                )
            }
        }
        SegmentTabs(
            options = SessionView.entries.map { stringResource(it.label) },
            selectedIndex = view.ordinal,
            onSelect = { view = SessionView.entries[it] },
            fontSize = 12.sp,
        )
        ListGroup(modifier = Modifier.fakeData()) {
            when (view) {
                SessionView.Items -> GoodsOutExtraSamples.sessionLocations.forEachIndexed { index, pick ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ItemRow(
                        code = pick.skoCode,
                        subtitle = listOf(
                            pick.location,
                            pluralStringResource(R.plurals.goodsout_extra_delivery_notes, pick.deliveryNotes, pick.deliveryNotes),
                        ).joinToString(KICKER_SEPARATOR),
                        done = pick.quantity.toString(),
                    )
                }
                SessionView.ByLocation -> GoodsOutExtraSamples.sessionLocations.forEachIndexed { index, pick ->
                    if (index > 0) {
                        RowDivider()
                    }
                    LocationPickRow(pick = pick, tone = Tone.Primary)
                }
                SessionView.ByDelivery -> GoodsOutExtraSamples.sessionNotes.forEachIndexed { index, note ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ExtraRow(
                        title = note.deliveryNote,
                        subtitle = note.trolley,
                        leading = { ExtraIconTile(icon = R.drawable.ic_ui_trolley, tone = Tone.Primary) },
                    )
                }
            }
        }
    }
}

@Composable
internal fun LocationPickRow(pick: SampleLocationPick, tone: Tone) {
    ExtraRow(
        title = pick.location,
        subtitle = stringResource(
            R.string.goodsout_extra_location_pick,
            pick.skoCode,
            pick.quantity,
            pluralStringResource(R.plurals.goodsout_extra_delivery_notes, pick.deliveryNotes, pick.deliveryNotes),
        ),
        leading = { ExtraIconTile(icon = R.drawable.ic_ui_location, tone = tone) },
    )
}

@Composable
private fun SessionSummaryCard(staffName: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            ExtraIconTile(icon = R.drawable.ic_ui_person, tone = Tone.Primary)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = stringResource(R.string.goodsout_extra_picker), fontSize = 12.sp, color = colors.textTertiary)
                Text(
                    text = stringResource(R.string.goodsout_extra_picker_you, staffName),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.text,
                )
            }
            StatusBadge(text = stringResource(SessionState.InProcess.label), tone = SessionState.InProcess.tone)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            StatColumn(
                value = GoodsOutExtraSamples.SESSION_DELIVERY_NOTES.toString(),
                label = stringResource(R.string.goodsout_extra_stat_delivery_notes),
                modifier = Modifier.weight(1f),
            )
            StatColumn(
                value = GoodsOutExtraSamples.SESSION_ITEMS.toString(),
                label = stringResource(R.string.goodsout_extra_stat_items),
                modifier = Modifier.weight(1f),
            )
            StatColumn(
                value = GoodsOutExtraSamples.SESSION_TROLLEYS.toString(),
                label = stringResource(R.string.goodsout_extra_stat_trolleys),
                modifier = Modifier.weight(1f),
            )
        }
    }
}
