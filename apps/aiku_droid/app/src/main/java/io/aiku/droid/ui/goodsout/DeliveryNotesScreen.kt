package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ListRow
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PickingSessionRoute

internal fun stageTone(stage: DeliveryNoteStage): Tone = when (stage) {
    DeliveryNoteStage.ToDo -> Tone.Neutral
    DeliveryNoteStage.Waiting -> Tone.Warn
    DeliveryNoteStage.Finalised, DeliveryNoteStage.Dispatched -> Tone.Success
    DeliveryNoteStage.Picking, DeliveryNoteStage.Picked, DeliveryNoteStage.Packing, DeliveryNoteStage.Packed -> Tone.Primary
}

@Composable
fun DeliveryNotesScreen() {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var showFilter by remember { mutableStateOf(false) }
    var selectedState by remember { mutableIntStateOf(0) }
    val stateOptions = GoodsOutSamples.stateCounts.map { state ->
        val label = stringResource(state.stage.label)
        FilterOption(label = if (state.count == null) label else stringResource(R.string.goodsout_state_count, label, state.count))
    }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        AppScreen(
            header = {
                ModuleHeader(
                    section = WarehouseSection.DeliveryNotes,
                    actions = {
                        HeaderIconButton(
                            icon = R.drawable.ic_ui_search,
                            description = stringResource(R.string.goodsout_search_and_filter),
                            onClick = { showFilter = true },
                            filled = true,
                        )
                    },
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.pickAndPack) {
                {
                    AikuButton(
                        text = pluralStringResource(
                            R.plurals.goodsout_create_session_from_selected,
                            GoodsOutSamples.SELECTED_SAMPLE_COUNT,
                            GoodsOutSamples.SELECTED_SAMPLE_COUNT,
                        ),
                        onClick = { navigator.open(PickingSessionRoute(reference = GoodsOutSamples.PICKING_SESSION)) },
                        style = ButtonStyle.Soft,
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth().fakeData(cornerRadius = 8.dp),
                    )
                }
            } else {
                null
            },
        ) {
            FilterChips(
                options = stateOptions,
                selectedIndex = selectedState,
                onSelect = { selectedState = it },
                modifier = Modifier.fakeData(cornerRadius = 17.dp),
            )
            ListGroup(modifier = Modifier.fakeData()) {
                GoodsOutSamples.deliveryNotes.forEachIndexed { index, note ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ListRow(
                        title = note.reference,
                        subtitle = note.subtitle,
                        badges = { StatusBadge(text = stringResource(note.stage.label), tone = stageTone(note.stage)) },
                        showChevron = false,
                        onClick = { navigator.open(note.route) },
                    )
                }
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_delivery_notes_note))
        }
    }
    if (showFilter) {
        DeliveryNoteFilterSheet(onDismiss = { showFilter = false })
    }
}
