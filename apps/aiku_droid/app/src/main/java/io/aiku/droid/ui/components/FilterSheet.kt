package io.aiku.droid.ui.components

import androidx.annotation.PluralsRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlin.math.roundToInt

data class FilterSheetOption(val label: String, val dot: Color? = null)

data class FilterSheetSection(
    val label: String,
    val options: List<FilterSheetOption>,
    val isSingleChoice: Boolean = false,
    val isAtMostOne: Boolean = false,
    val isSort: Boolean = false,
    val initiallySelected: Set<Int> = emptySet(),
    val isSample: Boolean = false,
) {
    fun toggled(current: Set<Int>, optionIndex: Int): Set<Int> = when {
        isSingleChoice -> setOf(optionIndex)
        optionIndex in current -> current - optionIndex
        isAtMostOne -> setOf(optionIndex)
        else -> current + optionIndex
    }
}

private const val RESULT_SHRINK_PER_FILTER = 0.9f

@Composable
fun FilterSheet(
    title: String,
    sections: List<FilterSheetSection>,
    sampleTotal: Int,
    @PluralsRes showLabel: Int,
    onDismiss: () -> Unit,
) {
    var selection by remember(sections) { mutableStateOf(sections.map { it.initiallySelected }) }
    val filterCount = selection.zip(sections).sumOf { (selected, section) -> if (section.isSingleChoice) 0 else selected.size }
    val results = maxOf(1, (sampleTotal / (1 + filterCount * RESULT_SHRINK_PER_FILTER)).roundToInt())

    FilterSheet(
        title = title,
        sections = sections,
        selection = selection,
        onSelectionChange = { selection = it },
        activeFilterCount = filterCount,
        showText = pluralStringResource(showLabel, results, results),
        onShow = onDismiss,
        onReset = { selection = sections.map { if (it.isSingleChoice) setOf(0) else emptySet() } },
        onDismiss = onDismiss,
    )
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun FilterSheet(
    title: String,
    sections: List<FilterSheetSection>,
    selection: List<Set<Int>>,
    onSelectionChange: (List<Set<Int>>) -> Unit,
    activeFilterCount: Int,
    showText: String,
    onShow: () -> Unit,
    onReset: () -> Unit,
    onDismiss: () -> Unit,
    header: (@Composable ColumnScope.() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val sortIndex = sections.indexOfFirst { it.isSort }
    val sortLabel = sortIndex.takeIf { it >= 0 }?.let { index -> selection.getOrNull(index)?.firstOrNull()?.let { sections[index].options[it].label } }
    val filtersText = if (activeFilterCount == 0) stringResource(R.string.filter_none) else pluralStringResource(R.plurals.filter_count, activeFilterCount, activeFilterCount)
    val summary = if (sortLabel != null) stringResource(R.string.filter_summary_sorted, filtersText, sortLabel.lowercase()) else filtersText

    AikuBottomSheet(
        title = title,
        onDismiss = onDismiss,
        icon = R.drawable.ic_ui_filter,
        headerAction = {
            Text(
                text = stringResource(R.string.filter_reset),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.link,
                modifier = Modifier
                    .clip(ChipShape)
                    .clickable(role = Role.Button, onClick = onReset)
                    .padding(horizontal = 8.dp, vertical = 10.dp),
            )
        },
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(20.dp)) {
            header?.invoke(this)
            sections.forEachIndexed { sectionIndex, section ->
                val selected = selection.getOrElse(sectionIndex) { emptySet() }
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(verticalAlignment = Alignment.Bottom) {
                        Text(
                            text = section.label.uppercase(),
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = 0.5.sp,
                            color = colors.textTertiary,
                            modifier = Modifier.weight(1f),
                        )
                        Text(
                            text = when {
                                section.isSingleChoice -> stringResource(R.string.filter_choose_one)
                                selected.isEmpty() -> stringResource(R.string.filter_any)
                                else -> pluralStringResource(R.plurals.filter_selected, selected.size, selected.size)
                            },
                            fontSize = 12.sp,
                            color = colors.textQuaternary,
                        )
                    }
                    FlowRow(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = if (section.isSample) Modifier.fakeData(cornerRadius = 20.dp) else Modifier,
                    ) {
                        section.options.forEachIndexed { optionIndex, option ->
                            FilterSheetChip(
                                option = option,
                                isOn = optionIndex in selected,
                                onToggle = {
                                    onSelectionChange(
                                        selection.mapIndexed { index, current -> if (index == sectionIndex) section.toggled(current, optionIndex) else current },
                                    )
                                },
                            )
                        }
                    }
                }
            }
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = summary, fontSize = 12.sp, color = colors.textTertiary, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
                AikuButton(
                    text = showText,
                    onClick = onShow,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}

@Composable
private fun FilterSheetChip(option: FilterSheetOption, isOn: Boolean, onToggle: () -> Unit) {
    val colors = LocalAikuColors.current
    val pill = RoundedCornerShape(999.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .heightIn(min = 40.dp)
            .clip(pill)
            .background(if (isOn) colors.soft else colors.surface)
            .border(if (isOn) 2.dp else 1.dp, if (isOn) colors.primary else colors.borderStrong, pill)
            .toggleable(value = isOn, role = Role.Checkbox, onValueChange = { onToggle() })
            .padding(horizontal = 14.dp),
    ) {
        if (option.dot != null) {
            Box(modifier = Modifier.size(8.dp).clip(CircleShape).background(option.dot))
        }
        Text(text = option.label, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = if (isOn) colors.link else colors.textSecondary)
    }
}
