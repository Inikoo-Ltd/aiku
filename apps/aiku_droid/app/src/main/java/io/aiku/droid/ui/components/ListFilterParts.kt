package io.aiku.droid.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.clocking.HrDatePickerDialog
import io.aiku.droid.ui.clocking.HrPickerField
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.LocalDate
import java.time.ZoneOffset

private const val YEARS_BACK = 10
private const val SUMMARY_SEPARATOR = " · "

private enum class DateEnd { From, To }

internal fun dateText(date: LocalDate): String = recordDate(date.toString(), ZoneOffset.UTC) ?: date.toString()

@Composable
fun dateRangeText(range: DateRange): String? {
    val from = range.from?.let { dateText(it) }
    val to = range.to?.let { dateText(it) }
    return when {
        from != null && to != null && range.from == range.to -> from
        from != null && to != null -> stringResource(R.string.filter_date_range, from, to)
        from != null -> stringResource(R.string.filter_date_since, from)
        to != null -> stringResource(R.string.filter_date_until, to)
        else -> null
    }
}

@Composable
fun DateRangeFields(range: DateRange, onChange: (DateRange) -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    var picking by remember { mutableStateOf<DateEnd?>(null) }
    val shown = dateRangeText(range)

    Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = modifier.fillMaxWidth()) {
        HrPickerField(
            label = stringResource(R.string.filter_date_from),
            value = range.from?.let { dateText(it) },
            placeholder = stringResource(R.string.filter_date_any),
            icon = R.drawable.ic_fa_calendar,
            onClick = { picking = DateEnd.From },
        )
        HrPickerField(
            label = stringResource(R.string.filter_date_to),
            value = range.to?.let { dateText(it) },
            placeholder = stringResource(R.string.filter_date_any),
            icon = R.drawable.ic_fa_calendar,
            onClick = { picking = DateEnd.To },
        )
        if (shown != null) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = shown, fontSize = 13.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
                LinkAction(text = stringResource(R.string.goodsout_filters_clear), onClick = { onChange(DateRange()) })
            }
        }
    }

    picking?.let { end ->
        val today = LocalDate.now()
        HrDatePickerDialog(
            initial = if (end == DateEnd.From) range.from ?: range.to else range.to ?: range.from,
            yearRange = (today.year - YEARS_BACK)..(today.year + 1),
            isSelectable = { date ->
                when (end) {
                    DateEnd.From -> range.to?.let { !date.isAfter(it) } ?: true
                    DateEnd.To -> range.from?.let { !date.isBefore(it) } ?: true
                }
            },
            onPick = { date -> onChange(if (end == DateEnd.From) range.withFrom(date) else range.withTo(date)) },
            onDismiss = { picking = null },
        )
    }
}

@Composable
fun <S> ListFilterPanel(
    title: String,
    filter: ListFilter<S>,
    sorts: List<SortOption<S>>,
    total: Int?,
    onApply: (ListFilter<S>) -> Unit,
    onDismiss: () -> Unit,
    dateLabel: String? = null,
    totalFor: (ListFilter<S>) -> Int? = { null },
) {
    var draft by remember { mutableStateOf(filter) }
    val shownTotal = if (draft == filter) total ?: totalFor(draft) else totalFor(draft)
    val dateSection = dateLabel?.let { label ->
        FilterSheetSection(
            label = label,
            options = emptyList(),
            extraCount = if (draft.dates.isSet) 1 else 0,
            extra = { DateRangeFields(range = draft.dates, onChange = { draft = draft.copy(dates = it) }) },
        )
    }
    val sortSection = FilterSheetSection(
        label = stringResource(R.string.goodsout_filter_sort),
        options = sorts.map { FilterSheetOption(label = it.label) },
        isSingleChoice = true,
        isSort = true,
    )
    val sections = listOfNotNull(dateSection, sortSection)
    val sortSelection = setOf(sorts.indexOfFirst { it.sort == draft.sort }.coerceAtLeast(0))

    FilterSheet(
        title = title,
        sections = sections,
        selection = sections.map { if (it.isSort) sortSelection else emptySet() },
        onSelectionChange = { selection -> selection.last().firstOrNull()?.let { draft = draft.copy(sort = sorts[it].sort) } },
        activeFilterCount = if (draft.dates.isSet) 1 else 0,
        showText = shownTotal?.let { pluralStringResource(R.plurals.filter_show_results, it, it) } ?: stringResource(R.string.filter_show_results_unknown),
        onShow = { onApply(draft) },
        onReset = { draft = ListFilter() },
        onDismiss = onDismiss,
    )
}

@Composable
fun <S> listFilterSummary(
    filter: ListFilter<S>,
    sorts: List<SortOption<S>>,
    dateLabel: String? = null,
    onlineOnlySorts: Set<S> = emptySet(),
): List<String> = listOfNotNull(
    dateRangeText(filter.dates)?.let { range -> dateLabel?.let { stringResource(R.string.filter_labelled, it, range) } ?: range },
    filter.sort?.let { sort ->
        sorts.firstOrNull { it.sort == sort }?.label?.let { label ->
            stringResource(R.string.goodsout_sorted_by, label.lowercase()).let { sorted ->
                if (sort in onlineOnlySorts) stringResource(R.string.filter_online_only, sorted) else sorted
            }
        }
    },
)

@Composable
fun ActiveFiltersLine(parts: List<String>, onClear: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = modifier.fillMaxWidth().padding(start = 4.dp),
    ) {
        Text(text = parts.joinToString(SUMMARY_SEPARATOR), fontSize = 13.sp, color = colors.textSecondary, modifier = Modifier.weight(1f))
        LinkAction(text = stringResource(R.string.goodsout_filters_clear), onClick = onClear)
    }
}

@Composable
fun FilterSquareButton(count: Int, onClick: () -> Unit, modifier: Modifier = Modifier, size: Dp = 48.dp) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.filter_and_sort)
    val isActive = count > 0

    Box(modifier = modifier.size(size)) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(size)
                .clip(ControlShape)
                .background(if (isActive) colors.soft else colors.surface)
                .border(1.dp, if (isActive) colors.primary else colors.borderStrong, ControlShape)
                .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
                .semantics { contentDescription = description },
        ) {
            Icon(painter = painterResource(R.drawable.ic_ui_filter), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        }
        if (isActive) {
            CountBadge(count = count, modifier = Modifier.align(Alignment.TopEnd).offset(x = 4.dp, y = (-4).dp))
        }
    }
}

@Composable
fun SearchWithFilter(
    filterCount: Int,
    onFilter: () -> Unit,
    modifier: Modifier = Modifier,
    buttonSize: Dp = 48.dp,
    search: @Composable (Modifier) -> Unit,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = modifier.fillMaxWidth(),
    ) {
        search(Modifier.weight(1f))
        FilterSquareButton(count = filterCount, onClick = onFilter, size = buttonSize)
    }
}

@Composable
private fun LinkAction(text: String, onClick: () -> Unit) {
    Text(
        text = text,
        fontSize = 14.sp,
        fontWeight = FontWeight.SemiBold,
        color = LocalAikuColors.current.link,
        modifier = Modifier
            .heightIn(min = 44.dp)
            .clip(ChipShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 8.dp, vertical = 12.dp),
    )
}
