package io.aiku.droid.ui.mywork

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.FilterChips
import io.aiku.droid.ui.components.FilterOption
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun MyWorkScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val staff = LocalStaff.current
    var filterIndex by remember { mutableIntStateOf(0) }
    val modules = listOf(null, MyWorkModule.Warehouse, MyWorkModule.Tasks)
    val items = MyWorkSamples.items.filter { modules[filterIndex] == null || it.module == modules[filterIndex] }
    val filters = listOf(
        FilterOption(stringResource(R.string.mywork_filter_all, MyWorkSamples.items.size)),
        FilterOption(stringResource(R.string.mywork_filter_warehouse, MyWorkSamples.items.count { it.module == MyWorkModule.Warehouse })),
        FilterOption(stringResource(R.string.mywork_filter_tasks, MyWorkSamples.items.count { it.module == MyWorkModule.Tasks })),
    )

    AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.tab_my_work),
                kicker = stringResource(R.string.mywork_kicker, staff.displayName.ifBlank { staff.username }),
                showBack = false,
                actions = {
                    HeaderIconButton(icon = R.drawable.ic_ui_search, description = stringResource(R.string.search_title), onClick = { navigator.open(SearchRoute) }, filled = true)
                },
            )
        },
        activeTab = MainTab.MyWork,
        spacing = 10.dp,
    ) {
        FilterChips(options = filters, selectedIndex = filterIndex, onSelect = { filterIndex = it }, modifier = Modifier.fakeData(cornerRadius = 17.dp))
        Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fakeData()) {
            items.forEach { item ->
                WorkRow(item = item, onClick = { navigator.open(item.route) })
            }
        }
        Text(
            text = stringResource(R.string.mywork_note),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(start = 4.dp, end = 4.dp, top = 6.dp),
        )
    }
}

@Composable
private fun WorkRow(item: MyWorkItem, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val bar = when (item.tone) {
        Tone.Warn -> colors.warn
        Tone.Primary -> colors.primary
        else -> colors.borderStrong
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 72.dp)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Box(modifier = Modifier.width(6.dp).height(44.dp).clip(RoundedCornerShape(999.dp)).background(bar))
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = item.reference, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                StatusBadge(text = item.state, tone = item.tone)
            }
            Text(text = item.subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        Text(
            text = stringResource(item.module.label),
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.textTertiary,
            modifier = Modifier.border(1.dp, colors.borderStrong, ChipShape).padding(horizontal = 6.dp, vertical = 2.dp),
        )
    }
}
