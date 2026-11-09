package io.aiku.droid.ui.search

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
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
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.SkuDetailRoute
import io.aiku.droid.ui.navigation.SkuFamilyDetailRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private val PillShape = RoundedCornerShape(percent = 50)

private data class SkuResult(val code: String, val name: String, val quantity: String)

private data class FamilyResult(val code: String, val name: String, val skuCount: Int)

private val SampleSkuResults = listOf(
    SkuResult(code = "SKU-00412", name = "Lavender essential oil 10ml", quantity = "1,240"),
    SkuResult(code = "SKU-00734", name = "Bath bomb – lavender 180g", quantity = "210"),
    SkuResult(code = "SKU-01190", name = "Soy candle – lavender", quantity = "96"),
)

private val SampleFamilyResults = listOf(
    FamilyResult(code = "LV", name = "Lavender range", skuCount = 14),
)

private val SampleRecentSearches = listOf("DN-24817", "A-12-03", "bath bomb")

@Composable
fun SearchScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    var query by rememberSaveable { mutableStateOf("") }
    var selectedFilter by rememberSaveable { mutableIntStateOf(0) }
    val filters = listOfNotNull(
        stringResource(R.string.search_filter_all),
        stringResource(R.string.search_filter_skus).takeIf { can.viewInventory },
        stringResource(R.string.search_filter_locations).takeIf { can.viewLocations },
        stringResource(R.string.search_filter_delivery_notes).takeIf { can.viewGoodsOut },
    )

    AppScreen(
        header = {
            SearchHeader(
                query = query,
                onQueryChange = { query = it },
                filters = filters,
                selectedFilter = selectedFilter,
                onSelectFilter = { selectedFilter = it },
            )
        },
    ) {
        if (can.viewInventory) {
            ResultSection(title = stringResource(R.string.search_section_skus, SampleSkuResults.size)) {
                SampleSkuResults.forEachIndexed { index, sku ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ResultRow(
                        title = AnnotatedString(sku.code),
                        subtitle = highlightMatches(sku.name, query, colors.softBorder),
                        trailing = sku.quantity,
                        onClick = { navigator.open(SkuDetailRoute(sku.code)) },
                    )
                }
            }
            ResultSection(title = stringResource(R.string.search_section_families, SampleFamilyResults.size)) {
                SampleFamilyResults.forEachIndexed { index, family ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ResultRow(
                        title = highlightMatches(family.name, query, colors.softBorder),
                        subtitle = AnnotatedString(stringResource(R.string.search_family_summary, family.code, family.skuCount)),
                        onClick = { navigator.open(SkuFamilyDetailRoute(family.code)) },
                    )
                }
            }
        }
        RecentSearches(onSelect = { query = it })
    }
}

@Composable
private fun SearchHeader(
    query: String,
    onQueryChange: (String) -> Unit,
    filters: List<String>,
    selectedFilter: Int,
    onSelectFilter: (Int) -> Unit,
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            HeaderIconButton(icon = R.drawable.ic_chevron_left, description = stringResource(R.string.nav_back), onClick = navigator::back)
            SearchInput(query = query, onQueryChange = onQueryChange, modifier = Modifier.weight(1f))
        }
        Row(
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState()),
        ) {
            filters.forEachIndexed { index, label ->
                HeaderFilterChip(label = label, isSelected = index == selectedFilter, onClick = { onSelectFilter(index) })
            }
        }
    }
}

@Composable
private fun SearchInput(query: String, onQueryChange: (String) -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val interactionSource = remember { MutableInteractionSource() }
    val isFocused by interactionSource.collectIsFocusedAsState()
    val label = stringResource(R.string.search_label)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .height(46.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .then(if (isFocused) Modifier.border(3.dp, colors.navAccent.copy(alpha = 0.6f), ControlShape) else Modifier)
            .padding(horizontal = 12.dp),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_search),
            contentDescription = null,
            tint = colors.textQuaternary,
            modifier = Modifier.size(20.dp),
        )
        BasicTextField(
            value = query,
            onValueChange = onQueryChange,
            singleLine = true,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
            interactionSource = interactionSource,
            modifier = Modifier
                .weight(1f)
                .semantics { contentDescription = label },
            decorationBox = { innerTextField ->
                Box(contentAlignment = Alignment.CenterStart) {
                    if (query.isEmpty()) {
                        Text(
                            text = stringResource(R.string.search_placeholder),
                            fontSize = 16.sp,
                            color = colors.textQuaternary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                        )
                    }
                    innerTextField()
                }
            },
        )
    }
}

@Composable
private fun HeaderFilterChip(label: String, isSelected: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .height(34.dp)
            .clip(PillShape)
            .background(if (isSelected) colors.navActive else colors.onNav.copy(alpha = 0.14f))
            .then(if (isSelected) Modifier.border(1.dp, colors.navRing, PillShape) else Modifier)
            .clickable(role = Role.Tab, onClick = onClick)
            .padding(horizontal = 12.dp),
    ) {
        Text(
            text = label,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (isSelected) colors.navAccent else colors.onNav,
            maxLines = 1,
        )
    }
}

@Composable
private fun ResultSection(title: String, content: @Composable ColumnScope.() -> Unit) {
    Column(
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fakeData(),
    ) {
        GroupLabel(text = title)
        ListGroup(content = content)
    }
}

@Composable
private fun ResultRow(
    title: AnnotatedString,
    subtitle: AnnotatedString,
    onClick: () -> Unit,
    trailing: String? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = title,
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            Text(
                text = subtitle,
                fontSize = 13.sp,
                color = colors.textTertiary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
        }
        if (trailing != null) {
            Text(text = trailing, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
        }
    }
}

@Composable
private fun RecentSearches(onSelect: (String) -> Unit) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        GroupLabel(text = stringResource(R.string.search_recent))
        Row(
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .fakeData(cornerRadius = 18.dp)
                .horizontalScroll(rememberScrollState()),
        ) {
            SampleRecentSearches.forEach { term ->
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier
                        .heightIn(min = 36.dp)
                        .clip(PillShape)
                        .background(colors.surface)
                        .border(1.dp, colors.border, PillShape)
                        .clickable(role = Role.Button) { onSelect(term) }
                        .padding(horizontal = 12.dp),
                ) {
                    Text(text = term, fontSize = 13.sp, color = colors.text, maxLines = 1)
                }
            }
        }
    }
}

private fun highlightMatches(text: String, query: String, highlight: Color): AnnotatedString {
    val term = query.trim()
    return buildAnnotatedString {
        append(text)
        if (term.isNotEmpty()) {
            var start = text.indexOf(term, ignoreCase = true)
            while (start >= 0) {
                addStyle(SpanStyle(background = highlight), start, start + term.length)
                start = text.indexOf(term, startIndex = start + term.length, ignoreCase = true)
            }
        }
    }
}

@Preview(heightDp = 900)
@Composable
private fun SearchScreenPreview() {
    AikuDroidTheme {
        SearchScreen()
    }
}
