package io.aiku.droid.ui.search

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.states.SearchEmptyState
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors



@Composable
fun SearchScreen() {
    val navigator = LocalAppNavigator.current
    var query by remember { mutableStateOf(SearchSamples.INITIAL_QUERY) }
    var scopeIndex by remember { mutableIntStateOf(0) }
    val scope = SearchScope.entries[scopeIndex]
    val needle = query.trim()
    val groups = SearchSamples.groups
        .filter { scope == SearchScope.All || it.scope == scope }
        .map { group -> group.copy(results = group.results.filter { needle.isNotEmpty() && (it.title.contains(needle, ignoreCase = true) || it.subtitle.contains(needle, ignoreCase = true)) }) }
        .filter { it.results.isNotEmpty() }

    AppScreen(
        header = {
            SearchHeader(
                query = query,
                onQueryChange = { query = it },
                scopeIndex = scopeIndex,
                onScope = { scopeIndex = it },
                onBack = { navigator.back() },
            )
        },
        activeTab = null,
        spacing = 16.dp,
    ) {
        when {
            needle.isEmpty() -> RecentSearches(onPick = { query = it })
            groups.isEmpty() -> SearchEmptyState(query = needle, onScan = { navigator.open(ScanCameraRoute) })
            else -> {
                groups.forEach { group ->
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        GroupLabel(text = stringResource(R.string.search_group, stringResource(group.label), group.results.size))
                        ListGroup(modifier = Modifier.fakeData()) {
                            group.results.forEachIndexed { index, result ->
                                if (index > 0) {
                                    RowDivider()
                                }
                                ResultRow(result = result, needle = needle, onClick = { navigator.open(result.route) })
                            }
                        }
                    }
                }
                RecentSearches(onPick = { query = it })
            }
        }
    }
}

@Composable
private fun SearchHeader(query: String, onQueryChange: (String) -> Unit, scopeIndex: Int, onScope: (Int) -> Unit, onBack: () -> Unit) {
    val colors = LocalAikuColors.current
    val label = stringResource(R.string.search_title)
    val scopes = listOf(R.string.search_scope_all, R.string.section_skos, R.string.section_locations, R.string.section_delivery_notes)
    val pill = RoundedCornerShape(999.dp)

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
                    modifier = Modifier.weight(1f).semantics { contentDescription = label },
                )
            }
        }
        Row(modifier = Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            scopes.forEachIndexed { index, scope ->
                val isActive = index == scopeIndex
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier
                        .height(34.dp)
                        .clip(pill)
                        .background(if (isActive) colors.navActive else Color.White.copy(alpha = 0.14f))
                        .then(if (isActive) Modifier.border(1.dp, colors.navRing, pill) else Modifier)
                        .selectable(selected = isActive, role = Role.Tab) { onScope(index) }
                        .padding(horizontal = 12.dp),
                ) {
                    Text(text = stringResource(scope), fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = if (isActive) colors.navAccent else colors.onNav)
                }
            }
        }
    }
}

@Composable
private fun ResultRow(result: SearchResult, needle: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        if (result.hasPhoto) {
            ProductPhoto(code = result.title)
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = result.title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = highlight(result.subtitle, needle, colors.softBorder), fontSize = 13.sp, color = colors.textTertiary)
        }
        if (result.trailing != null) {
            Text(text = result.trailing, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
        }
    }
}

@Composable
private fun RecentSearches(onPick: (String) -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        GroupLabel(text = stringResource(R.string.search_recent))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 8.dp)) {
            SearchSamples.recent.forEach { recent ->
                Text(
                    text = recent,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = colors.textSecondary,
                    modifier = Modifier
                        .clip(ChipShape)
                        .background(colors.surface)
                        .border(1.dp, colors.border, ChipShape)
                        .clickable(role = Role.Button) { onPick(recent) }
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                )
            }
        }
    }
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
