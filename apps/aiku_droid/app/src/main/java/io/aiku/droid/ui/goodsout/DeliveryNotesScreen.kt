package io.aiku.droid.ui.goodsout

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderSearchRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.goodsin.GoodsCaptionRow
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.DeliveryNoteDetailRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ScanHardwareRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class DeliveryNoteListState(@StringRes val label: Int, @DrawableRes val icon: Int, val sampleTotal: Int) {
    Unassigned(R.string.goodsout_state_unassigned, R.drawable.ic_goodsout_queued, 12),
    Queued(R.string.goodsout_state_queued, R.drawable.ic_goodsout_queued, 36),
    Handling(R.string.goodsout_state_handling, R.drawable.ic_goodsout_handling, 18),
}

private data class DeliveryNoteSample(
    val reference: String,
    val state: DeliveryNoteListState,
    val customer: String,
    val load: String,
    val isNextUp: Boolean = false,
)

private val deliveryNotesSample = listOf(
    DeliveryNoteSample("DN-24811", DeliveryNoteListState.Unassigned, "[Customer] · UK shop · 09 Nov", "14 items · 6.2 kg", isNextUp = true),
    DeliveryNoteSample("DN-24812", DeliveryNoteListState.Unassigned, "[Customer] · EU shop · 09 Nov", "3 items · 1.1 kg", isNextUp = true),
    DeliveryNoteSample("DN-24815", DeliveryNoteListState.Unassigned, "[Customer] · UK shop · 09 Nov", "28 items · 12.4 kg"),
    DeliveryNoteSample("DN-24816", DeliveryNoteListState.Unassigned, "[Customer] · Dropshipping · 09 Nov", "1 item · 0.3 kg"),
    DeliveryNoteSample("DN-24819", DeliveryNoteListState.Unassigned, "[Customer] · UK shop · 09 Nov", "9 items · 3.8 kg"),
    DeliveryNoteSample("DN-24820", DeliveryNoteListState.Queued, "[Customer] · UK shop · 08 Nov", "4 items · 1.6 kg"),
    DeliveryNoteSample("DN-24817", DeliveryNoteListState.Handling, "[Customer] · UK shop · 08 Nov", "14 items · 6.2 kg"),
)

@Composable
fun DeliveryNotesScreen() {
    val colors = LocalAikuColors.current
    val can = LocalWarehouse.current.can
    val navigator = LocalAppNavigator.current
    val title = stringResource(R.string.goodsout_title)
    var query by remember { mutableStateOf("") }
    var selectedFilter by remember { mutableIntStateOf(0) }

    RequireAbility(isAllowed = can.viewGoodsOut, title = title, activeTab = AppTab.GoodsOut) {
        val filterStates = DeliveryNoteListState.entries
        val filterOptions = filterStates.map { state ->
            FilterOption(label = stringResource(R.string.goodsout_filter_with_count, stringResource(state.label), state.sampleTotal))
        }
        val selectedState = filterStates[selectedFilter]
        val search = query.trim()
        val notes = deliveryNotesSample.filter { note ->
            note.state == selectedState &&
                (note.reference.contains(search, ignoreCase = true) || note.customer.contains(search, ignoreCase = true))
        }

        AppScreen(
            header = {
                AppHeader(
                    title = title,
                    below = {
                        GoodsOutSectionTabs(active = GoodsOutSection.DeliveryNotes)
                        HeaderSearchRow(
                            placeholder = stringResource(R.string.goodsout_search_placeholder),
                            value = query,
                            onValueChange = { query = it },
                            onScan = { navigator.open(ScanHardwareRoute) },
                        )
                    },
                )
            },
            activeTab = AppTab.GoodsOut,
            contentPadding = PaddingValues(start = 16.dp, top = 14.dp, end = 16.dp, bottom = 16.dp),
            spacing = 10.dp,
        ) {
            FilterChips(
                options = filterOptions,
                selectedIndex = selectedFilter,
                onSelect = { selectedFilter = it },
                modifier = Modifier.fakeData(cornerRadius = 8.dp),
            )
            GoodsCaptionRow(
                start = stringResource(R.string.goodsout_oldest_first),
                end = stringResource(R.string.goodsout_items_weight),
            )
            if (notes.isEmpty()) {
                Text(
                    text = stringResource(R.string.goodsout_no_results),
                    fontSize = 14.sp,
                    color = colors.textTertiary,
                    textAlign = TextAlign.Center,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 24.dp),
                )
            } else {
                ListGroup(modifier = Modifier.fakeData()) {
                    notes.forEachIndexed { index, note ->
                        if (index > 0) {
                            RowDivider()
                        }
                        DeliveryNoteRow(
                            note = note,
                            showStartPicking = can.pickAndPack && note.isNextUp && note.state == DeliveryNoteListState.Unassigned,
                            onOpen = { navigator.open(DeliveryNoteDetailRoute(note.reference)) },
                        )
                    }
                }
            }
            Text(
                text = stringResource(R.string.goodsout_list_footer, notes.size, selectedState.sampleTotal),
                fontSize = 13.sp,
                color = colors.textTertiary,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .align(Alignment.CenterHorizontally)
                    .fakeData(cornerRadius = 6.dp)
                    .padding(6.dp),
            )
        }
    }
}

@Composable
private fun DeliveryNoteRow(note: DeliveryNoteSample, showStartPicking: Boolean, onOpen: () -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(if (showStartPicking) 10.dp else 6.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable(role = Role.Button, onClick = onOpen)
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(
                text = note.reference,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            DeliveryNoteStateBadge(state = note.state)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(text = note.customer, fontSize = 13.sp, color = colors.textTertiary, modifier = Modifier.weight(1f))
            Text(text = note.load, fontSize = 13.sp, color = colors.textTertiary)
        }
        if (showStartPicking) {
            AikuButton(
                text = stringResource(R.string.goodsout_start_picking),
                onClick = onOpen,
                height = 44.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun DeliveryNoteStateBadge(state: DeliveryNoteListState) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        modifier = Modifier
            .clip(ChipShape)
            .background(colors.background)
            .padding(horizontal = 8.dp, vertical = 2.dp),
    ) {
        Icon(
            painter = painterResource(state.icon),
            contentDescription = null,
            tint = if (state == DeliveryNoteListState.Queued) colors.lime else colors.textSecondary,
            modifier = Modifier.size(12.dp),
        )
        Text(text = stringResource(state.label), fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary, maxLines = 1)
    }
}

@Preview(widthDp = 390, heightDp = 1240)
@Composable
private fun DeliveryNotesScreenPreview() {
    AikuDroidTheme {
        DeliveryNotesScreen()
    }
}
