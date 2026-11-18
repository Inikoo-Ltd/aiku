package io.aiku.droid.ui.search

import androidx.compose.foundation.LocalIndication
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.relocation.BringIntoViewRequester
import androidx.compose.foundation.relocation.bringIntoViewRequester
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.SearchGroup
import io.aiku.droid.data.api.SearchRow
import io.aiku.droid.data.api.SearchType
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ScrollFadeWidth
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.SkeletonListRow
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.horizontalScrollFade
import io.aiku.droid.ui.components.rememberNavFocusIndication
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.scan.route
import io.aiku.droid.ui.states.SearchEmptyState
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun SearchScreen(search: SearchViewModel) {
    val state by search.state.collectAsStateWithLifecycle()
    val navigator = LocalAppNavigator.current

    LifecycleEventEffect(Lifecycle.Event.ON_START) { search.onStart() }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = search::signInAgain)
        return
    }

    SearchContent(
        state = state,
        onQueryChange = search::onQueryChange,
        onSubmit = search::searchNow,
        onScope = search::selectScope,
        onPickRecent = search::pickRecent,
        onShowMore = search::showMore,
        onRetry = search::retry,
        onOpen = { row ->
            search.rememberSearch()
            navigator.open(row.destination().route())
        },
    )
}

@Composable
private fun SearchContent(
    state: SearchUiState,
    onQueryChange: (String) -> Unit,
    onSubmit: () -> Unit,
    onScope: (SearchType?) -> Unit,
    onPickRecent: (String) -> Unit,
    onShowMore: (SearchType) -> Unit,
    onRetry: () -> Unit,
    onOpen: (SearchRow) -> Unit,
) {
    val navigator = LocalAppNavigator.current
    val problem = state.problem

    AppScreen(
        header = {
            SearchHeader(
                query = state.query,
                onQueryChange = onQueryChange,
                onSubmit = onSubmit,
                scopes = if (state.types.isEmpty()) emptyList() else listOf(null) + state.types,
                selected = state.scope,
                onScope = onScope,
                onBack = { navigator.back() },
            )
        },
        activeTab = null,
        spacing = 16.dp,
    ) {
        when {
            problem != null -> GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.search_failed), onRetry = onRetry)
            state.isQueryTooShort -> if (state.shownRecent.isEmpty()) SearchHint() else RecentSearches(recent = state.shownRecent, onPick = onPickRecent)
            state.isEmpty -> SearchEmptyState(
                query = state.needle,
                suggestions = state.shownRecent,
                onSuggestion = onPickRecent,
                onScan = { navigator.open(ScanRoute) },
            )
            !state.hasResults -> SkeletonListCard(rows = 4)
            else -> SearchResultsList(state = state, onShowMore = onShowMore, onRetry = onRetry, onOpen = onOpen)
        }
    }
}

@Composable
private fun SearchResultsList(state: SearchUiState, onShowMore: (SearchType) -> Unit, onRetry: () -> Unit, onOpen: (SearchRow) -> Unit) {
    val needle = state.needle
    val isPaging = state.scope != null

    state.exact?.let { exact ->
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            GroupLabel(text = stringResource(R.string.search_exact_match), case = SearchLabelCase)
            ListGroup {
                ResultRow(row = exact, needle = needle, onClick = { onOpen(exact) })
            }
        }
    }
    state.groups.forEach { group ->
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            GroupLabel(text = groupLabel(group), case = SearchLabelCase)
            ListGroup {
                group.rows.forEachIndexed { index, row ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ResultRow(row = row, needle = needle, onClick = { onOpen(row) })
                }
                when {
                    isPaging && state.isLoadingMore -> {
                        RowDivider()
                        SkeletonListRow()
                    }
                    group.hasMore && (!isPaging || state.moreProblem == null) -> {
                        if (group.rows.isNotEmpty()) {
                            RowDivider()
                        }
                        ShowMoreRow(onClick = { onShowMore(group.type) })
                    }
                }
            }
        }
    }
    state.moreProblem?.let { problem ->
        GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.search_more_failed), onRetry = onRetry)
    }
}

@Composable
private fun groupLabel(group: SearchGroup): String {
    val label = group.label.ifBlank { stringResource(searchTypeLabel(group.type)) }
    return if (group.hasMore) {
        stringResource(R.string.search_group_more, label, group.rows.size)
    } else {
        stringResource(R.string.search_group, label, group.rows.size)
    }
}

@Composable
private fun SearchHeader(
    query: String,
    onQueryChange: (String) -> Unit,
    onSubmit: () -> Unit,
    scopes: List<SearchType?>,
    selected: SearchType?,
    onScope: (SearchType?) -> Unit,
    onBack: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val label = stringResource(R.string.search_title)
    val focusRequester = remember { FocusRequester() }
    val isEmptyOnOpen = remember { query.isEmpty() }

    LaunchedEffect(Unit) {
        if (isEmptyOnOpen) {
            focusRequester.requestFocus()
        }
    }

    CompositionLocalProvider(LocalIndication provides rememberNavFocusIndication()) {
        Column(
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .background(colors.nav)
                .statusBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 14.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                HeaderIconButton(icon = R.drawable.ic_ui_back, description = stringResource(R.string.nav_back), onClick = onBack, iconSize = 24.dp)
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                    modifier = Modifier
                        .weight(1f)
                        .height(46.dp)
                        .clip(ControlShape)
                        .background(colors.surface)
                        .border(3.dp, colors.scan.copy(alpha = 0.6f), ControlShape)
                        .padding(horizontal = 12.dp),
                ) {
                    Icon(painter = painterResource(R.drawable.ic_ui_search_alt), contentDescription = null, tint = colors.textQuaternary, modifier = Modifier.size(20.dp))
                    BasicTextField(
                        value = query,
                        onValueChange = onQueryChange,
                        singleLine = true,
                        textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, color = colors.text),
                        cursorBrush = SolidColor(colors.primary),
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                        keyboardActions = KeyboardActions(onSearch = { onSubmit() }),
                        modifier = Modifier
                            .weight(1f)
                            .focusRequester(focusRequester)
                            .semantics { contentDescription = label },
                        decorationBox = { field ->
                            Box(contentAlignment = Alignment.CenterStart) {
                                if (query.isEmpty()) {
                                    Text(text = stringResource(R.string.search_placeholder), fontSize = 16.sp, color = colors.textQuaternary, maxLines = 1)
                                }
                                field()
                            }
                        },
                    )
                }
            }
            if (scopes.isNotEmpty()) {
                val chipScroll = rememberScrollState()
                Row(
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScrollFade(scrollState = chipScroll, color = colors.nav)
                        .horizontalScroll(chipScroll),
                ) {
                    scopes.forEach { scope ->
                        ScopeChip(
                            label = stringResource(scope?.let(::searchTypeLabel) ?: R.string.search_scope_all),
                            isActive = scope == selected,
                            onClick = { onScope(scope) },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun ScopeChip(label: String, isActive: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val pill = RoundedCornerShape(999.dp)
    val viewRequester = remember { BringIntoViewRequester() }
    val chipSize = remember { mutableStateOf(IntSize.Zero) }
    val fadePixels = with(LocalDensity.current) { ScrollFadeWidth.toPx() }

    LaunchedEffect(isActive) {
        if (isActive) {
            val size = chipSize.value
            viewRequester.bringIntoView(Rect(left = -fadePixels, top = 0f, right = size.width + fadePixels, bottom = size.height.toFloat()))
        }
    }

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .height(34.dp)
            .bringIntoViewRequester(viewRequester)
            .onSizeChanged { chipSize.value = it }
            .clip(pill)
            .background(if (isActive) colors.navActive else Color.White.copy(alpha = 0.14f))
            .then(if (isActive) Modifier.border(1.dp, colors.navRing, pill) else Modifier)
            .selectable(
                selected = isActive,
                interactionSource = remember { MutableInteractionSource() },
                indication = rememberNavFocusIndication(cornerRadius = 17.dp),
                role = Role.Tab,
                onClick = onClick,
            )
            .padding(horizontal = 12.dp),
    ) {
        Text(
            text = label,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (isActive) colors.navAccent else colors.onNav,
        )
    }
}

@Composable
private fun ResultRow(row: SearchRow, needle: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val quantity = row.quantityLabel()

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        if (row.type.hasPhoto) {
            ProductPhoto(code = row.title, imageUrl = row.imageUrl)
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = highlight(row.title, needle, colors.softBorder),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (row.type.hasStateBadge && row.stateLabel.isNotBlank()) {
                    StatusBadge(text = row.stateLabel, tone = searchStateTone(row))
                }
            }
            if (row.subtitle.isNotBlank()) {
                Text(
                    text = highlight(row.subtitle, needle, colors.softBorder),
                    fontSize = 13.sp,
                    color = colors.textTertiary,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
        if (quantity != null) {
            Text(
                text = withBoldPart(pluralStringResource(quantity.plural, quantity.count, quantity.text), quantity.text, colors.text),
                fontSize = 13.sp,
                color = colors.textTertiary,
                maxLines = 1,
            )
        }
    }
}

@Composable
private fun ShowMoreRow(onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 48.dp)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Text(text = stringResource(R.string.search_show_more), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
    }
}

@Composable
private fun SearchHint() {
    val colors = LocalAikuColors.current

    Text(
        text = stringResource(R.string.search_hint),
        fontSize = 13.sp,
        lineHeight = 19.sp,
        color = colors.textTertiary,
        modifier = Modifier.padding(horizontal = 4.dp),
    )
}

@Composable
private fun RecentSearches(recent: List<String>, onPick: (String) -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        GroupLabel(text = stringResource(R.string.search_recent), case = SearchLabelCase)
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            recent.forEach { query ->
                Text(
                    text = query,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.textSecondary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier
                        .clip(ChipShape)
                        .background(colors.surface)
                        .border(1.dp, colors.border, ChipShape)
                        .clickable(role = Role.Button) { onPick(query) }
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                )
            }
        }
    }
}

private fun withBoldPart(text: String, part: String, partColor: Color): AnnotatedString = buildAnnotatedString {
    val start = if (part.isEmpty()) -1 else text.indexOf(part)
    if (start < 0) {
        append(text)
        return@buildAnnotatedString
    }
    append(text.substring(0, start))
    withStyle(SpanStyle(fontWeight = FontWeight.Bold, color = partColor)) { append(part) }
    append(text.substring(start + part.length))
}

private fun highlight(text: String, needle: String, mark: Color): AnnotatedString = buildAnnotatedString {
    val start = if (needle.isEmpty()) -1 else text.indexOf(needle, ignoreCase = true)
    if (start < 0) {
        append(text)
        return@buildAnnotatedString
    }
    append(text.substring(0, start))
    withStyle(SpanStyle(background = mark)) { append(text.substring(start, start + needle.length)) }
    append(text.substring(start + needle.length))
}
