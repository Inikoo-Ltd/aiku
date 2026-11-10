package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PackByScanRoute
import io.aiku.droid.ui.navigation.PickingSessionRoute

private const val DROPSHIPPING_TAB = 0

@Composable
fun PickingSessionsScreen() {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        PickingSessionsContent()
    }
}

@Composable
private fun PickingSessionsContent() {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val staffName = LocalStaff.current.displayName
    var selectedTab by remember { mutableIntStateOf(DROPSHIPPING_TAB) }
    val showsDropshipping = selectedTab == DROPSHIPPING_TAB || !can.viewFulfilment

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.PickingSessions) },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        if (can.viewFulfilment) {
            SegmentTabs(
                options = listOf(
                    stringResource(R.string.goodsout_extra_sessions_dropshipping),
                    stringResource(R.string.goodsout_extra_sessions_fulfilment),
                ),
                selectedIndex = selectedTab,
                onSelect = { selectedTab = it },
            )
        }
        if (showsDropshipping) {
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutExtraSamples.pickingSessions.forEachIndexed { index, session ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ExtraRow(
                        title = session.reference,
                        subtitle = sessionSubtitle(session = session, staffName = staffName),
                        chip = stringResource(session.state.label),
                        chipTone = session.state.tone,
                        onClick = {
                            if (session.state == SessionState.PickingFinished) {
                                navigator.open(PackByScanRoute(session.reference))
                            } else {
                                navigator.open(PickingSessionRoute(session.reference))
                            }
                        },
                    )
                }
            }
        } else {
            EmptyState(
                icon = R.drawable.ic_ui_trolley,
                title = stringResource(R.string.goodsout_extra_sessions_fulfilment_empty_title),
                body = stringResource(R.string.goodsout_extra_sessions_fulfilment_empty_body),
            )
        }
        HintText(text = stringResource(R.string.goodsout_extra_sessions_hint))
    }
}

@Composable
private fun sessionSubtitle(session: SamplePickingSession, staffName: String): String {
    val details = when (val progress = session.progress) {
        is SessionProgress.Picking -> listOf(
            pluralStringResource(R.plurals.goodsout_extra_delivery_notes, progress.deliveryNotes, progress.deliveryNotes),
            pluralStringResource(R.plurals.goodsout_extra_trolleys, progress.trolleys, progress.trolleys),
            stringResource(R.string.goodsout_extra_percent_picked, progress.percentPicked),
        )
        is SessionProgress.NotStarted -> listOf(
            pluralStringResource(R.plurals.goodsout_extra_delivery_notes, progress.deliveryNotes, progress.deliveryNotes),
            stringResource(R.string.goodsout_extra_not_started),
        )
        is SessionProgress.Waiting -> listOf(
            pluralStringResource(R.plurals.goodsout_extra_delivery_notes_waiting, progress.waitingDeliveryNotes, progress.waitingDeliveryNotes),
        )
        is SessionProgress.Packing -> listOf(stringResource(R.string.goodsout_extra_packing_percent, progress.percentPacked))
    }
    return (listOf(session.pickerName ?: staffName) + details).joinToString(KICKER_SEPARATOR)
}
