package io.aiku.droid.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.MutableTransitionState
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.systemBars
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.windowInsetsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.onClick
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.min
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.compose.ui.window.DialogWindowProvider
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors

data class FilterSheetOption(val label: String, val dot: Color? = null)

data class FilterSheetSection(
    val label: String,
    val options: List<FilterSheetOption>,
    val isSingleChoice: Boolean = false,
    val isAtMostOne: Boolean = false,
    val isSort: Boolean = false,
    val initiallySelected: Set<Int> = emptySet(),
    val isSample: Boolean = false,
    val extraCount: Int = 0,
    val extra: (@Composable () -> Unit)? = null,
) {
    fun toggled(current: Set<Int>, optionIndex: Int): Set<Int> = when {
        isSingleChoice -> setOf(optionIndex)
        optionIndex in current -> current - optionIndex
        isAtMostOne -> setOf(optionIndex)
        else -> current + optionIndex
    }
}

private const val PANEL_WIDTH_FRACTION = 0.88f
private val PanelMaxWidth = 420.dp
private val PanelShape = RoundedCornerShape(topStart = 16.dp, bottomStart = 16.dp)
private const val SORT_MENU_WIDTH_FRACTION = 0.78f

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

    RightPanel(onDismiss = onDismiss) { close ->
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            modifier = Modifier.fillMaxWidth().padding(start = 20.dp, end = 12.dp, top = 14.dp, bottom = 6.dp),
        ) {
            Icon(painter = painterResource(R.drawable.ic_ui_filter), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
            Text(text = title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
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
        }
        Column(
            verticalArrangement = Arrangement.spacedBy(20.dp),
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(start = 20.dp, end = 20.dp, top = 14.dp, bottom = 20.dp),
        ) {
            header?.invoke(this)
            sections.forEachIndexed { sectionIndex, section ->
                val selected = selection.getOrElse(sectionIndex) { emptySet() }
                val chosen = selected.size + section.extraCount
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
                                chosen == 0 -> stringResource(R.string.filter_any)
                                else -> pluralStringResource(R.plurals.filter_selected, chosen, chosen)
                            },
                            fontSize = 12.sp,
                            color = colors.textQuaternary,
                        )
                    }
                    if (section.isSort && section.options.isNotEmpty()) {
                        SortSelect(
                            options = section.options,
                            selectedIndex = selected.firstOrNull(),
                            onSelect = { optionIndex ->
                                onSelectionChange(selection.mapIndexed { index, current -> if (index == sectionIndex) setOf(optionIndex) else current })
                            },
                        )
                    } else if (section.options.isNotEmpty()) {
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
                    section.extra?.invoke()
                }
            }
        }
        HorizontalDivider(thickness = 1.dp, color = colors.border)
        Column(
            verticalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.fillMaxWidth().padding(start = 20.dp, end = 20.dp, top = 12.dp, bottom = 16.dp),
        ) {
            Text(text = summary, fontSize = 12.sp, color = colors.textTertiary, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
            AikuButton(
                text = showText,
                onClick = { close(onShow) },
                icon = R.drawable.ic_ui_next,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun RightPanel(onDismiss: () -> Unit, content: @Composable ColumnScope.(close: (() -> Unit) -> Unit) -> Unit) {
    val colors = LocalAikuColors.current
    val visibility = remember { MutableTransitionState(false) }
    var afterClose by remember { mutableStateOf<(() -> Unit)?>(null) }
    val close: (() -> Unit) -> Unit = { then ->
        if (afterClose == null) {
            afterClose = then
            visibility.targetState = false
        }
    }
    val pendingAfterClose = afterClose

    LaunchedEffect(Unit) { visibility.targetState = true }
    if (pendingAfterClose != null && visibility.isIdle && !visibility.currentState) {
        LaunchedEffect(pendingAfterClose) { pendingAfterClose() }
    }

    Dialog(
        onDismissRequest = { close(onDismiss) },
        properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false),
    ) {
        val dialogWindow = (LocalView.current.parent as? DialogWindowProvider)?.window
        SideEffect { dialogWindow?.setDimAmount(0f) }

        BoxWithConstraints(modifier = Modifier.fillMaxSize()) {
            val panelWidth = min(maxWidth * PANEL_WIDTH_FRACTION, PanelMaxWidth)

            AnimatedVisibility(visibleState = visibility, enter = fadeIn(), exit = fadeOut()) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(colors.scrim)
                        .pointerInput(Unit) { detectTapGestures { close(onDismiss) } }
                        .semantics {
                            onClick {
                                close(onDismiss)
                                true
                            }
                        },
                )
            }
            AnimatedVisibility(
                visibleState = visibility,
                enter = slideInHorizontally { it },
                exit = slideOutHorizontally { it },
                modifier = Modifier.align(Alignment.CenterEnd),
            ) {
                Column(
                    modifier = Modifier
                        .width(panelWidth)
                        .fillMaxHeight()
                        .clip(PanelShape)
                        .background(colors.surface)
                        .windowInsetsPadding(WindowInsets.systemBars)
                        .imePadding(),
                ) {
                    content(close)
                }
            }
        }
    }
}

@Composable
private fun SortSelect(options: List<FilterSheetOption>, selectedIndex: Int?, onSelect: (Int) -> Unit) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)
    var isOpen by remember { mutableStateOf(false) }
    val selectedLabel = selectedIndex?.let { options.getOrNull(it)?.label }

    Box(modifier = Modifier.fillMaxWidth()) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .clip(shape)
                .background(colors.surface)
                .border(1.dp, if (isOpen) colors.primary else colors.borderStrong, shape)
                .clickable(role = Role.DropdownList) { isOpen = true }
                .padding(horizontal = 12.dp),
        ) {
            Text(
                text = selectedLabel ?: stringResource(R.string.filter_choose_one),
                fontSize = 16.sp,
                fontWeight = FontWeight.SemiBold,
                color = if (selectedLabel == null) colors.textQuaternary else colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            Icon(painter = painterResource(R.drawable.ic_ui_chevron_down), contentDescription = null, tint = colors.textTertiary, modifier = Modifier.size(18.dp))
        }
        DropdownMenu(
            expanded = isOpen,
            onDismissRequest = { isOpen = false },
            containerColor = colors.surface,
            shape = shape,
            modifier = Modifier.fillMaxWidth(SORT_MENU_WIDTH_FRACTION),
        ) {
            options.forEachIndexed { index, option ->
                val isSelected = index == selectedIndex
                DropdownMenuItem(
                    text = {
                        Text(
                            text = option.label,
                            fontSize = 15.sp,
                            fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Normal,
                            color = if (isSelected) colors.link else colors.text,
                        )
                    },
                    trailingIcon = if (isSelected) {
                        { Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.link, modifier = Modifier.size(18.dp)) }
                    } else {
                        null
                    },
                    onClick = {
                        isOpen = false
                        onSelect(index)
                    },
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
