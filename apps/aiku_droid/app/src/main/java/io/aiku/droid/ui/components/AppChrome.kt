package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.ScrollState
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private const val SAMPLE_MY_WORK_COUNT = 6
private const val SAMPLE_INBOX_COUNT = 3

@Composable
fun warehouseKicker(extra: String? = null): String {
    val warehouse = LocalWarehouse.current
    return listOf(warehouse.name, warehouse.code).filter { it.isNotBlank() }.plus(listOfNotNull(extra)).joinToString(" · ")
}

@Composable
fun moduleKicker(@StringRes module: Int): String = listOf(stringResource(module), warehouseKicker()).filter { it.isNotBlank() }.joinToString(" · ")

@Composable
fun AppScreen(
    header: @Composable () -> Unit,
    modifier: Modifier = Modifier,
    activeTab: MainTab? = MainTab.Menu,
    showBar: Boolean = true,
    footer: (@Composable ColumnScope.() -> Unit)? = null,
    bottomPanel: (@Composable () -> Unit)? = null,
    contentPadding: PaddingValues = PaddingValues(16.dp),
    spacing: Dp = 14.dp,
    scrollState: ScrollState = rememberScrollState(),
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxSize().background(colors.background)) {
        header()
        Column(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(scrollState)
                .then(if (!showBar && footer == null && bottomPanel == null) Modifier.navigationBarsPadding() else Modifier)
                .padding(contentPadding),
            verticalArrangement = Arrangement.spacedBy(spacing),
            content = content,
        )
        if (bottomPanel != null) {
            Box(modifier = if (showBar || footer != null) Modifier else Modifier.navigationBarsPadding()) { bottomPanel() }
        }
        if (footer != null) {
            ActionFooter(modifier = if (showBar) Modifier else Modifier.navigationBarsPadding(), content = footer)
        }
        if (showBar) {
            MainBar(active = activeTab)
        }
    }
}

@Composable
fun ActionFooter(modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxWidth().background(colors.surface)) {
        HorizontalDivider(thickness = 1.dp, color = colors.border)
        Column(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
            content = content,
        )
    }
}

@Composable
fun FooterStatus(title: String, modifier: Modifier = Modifier, body: String? = null) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxWidth().padding(vertical = 4.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
        if (body != null) {
            Text(text = body, fontSize = 12.sp, color = colors.textTertiary)
        }
    }
}

data class SectionTabItem(val label: String, val isActive: Boolean, val onClick: () -> Unit)

@Composable
fun AppHeader(
    title: String,
    modifier: Modifier = Modifier,
    kicker: String? = null,
    showBack: Boolean = true,
    onBack: (() -> Unit)? = null,
    tabs: List<SectionTabItem> = emptyList(),
    isSample: Boolean = false,
    actions: @Composable RowScope.() -> Unit = {},
    below: (@Composable ColumnScope.() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(top = 8.dp, bottom = 12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            if (showBack) {
                HeaderIconButton(
                    icon = R.drawable.ic_ui_back,
                    description = stringResource(R.string.nav_back),
                    onClick = onBack ?: navigator::back,
                    iconSize = 24.dp,
                )
            }
            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = if (showBack) 0.dp else 4.dp)
                    .then(if (isSample) Modifier.fakeData(cornerRadius = 6.dp).padding(horizontal = 4.dp) else Modifier),
            ) {
                if (kicker != null) {
                    Text(text = kicker, fontSize = 12.sp, color = colors.onNavSecondary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
                Text(
                    text = title,
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.onNav,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
            actions()
        }
        if (tabs.isNotEmpty()) {
            SectionTabs(tabs = tabs)
        }
        if (below != null) {
            Column(modifier = Modifier.padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), content = below)
        }
    }
}

@Composable
fun SectionTabs(tabs: List<SectionTabItem>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val pill = RoundedCornerShape(999.dp)

    Row(
        modifier = modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        tabs.forEach { tab ->
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .height(36.dp)
                    .clip(pill)
                    .background(if (tab.isActive) colors.navActive else Color.White.copy(alpha = 0.12f))
                    .then(if (tab.isActive) Modifier.border(1.dp, colors.navRing, pill) else Modifier)
                    .selectable(selected = tab.isActive, role = Role.Tab, onClick = tab.onClick)
                    .padding(horizontal = 12.dp),
            ) {
                Text(
                    text = tab.label,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (tab.isActive) colors.navAccent else colors.onNav,
                    maxLines = 1,
                )
            }
        }
    }
}

@Composable
fun HeaderIconButton(
    @DrawableRes icon: Int,
    description: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    filled: Boolean = false,
    badgeCount: Int? = null,
    iconSize: Dp = 22.dp,
) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(44.dp)
            .clip(RoundedCornerShape(8.dp))
            .background(if (filled) Color.White.copy(alpha = 0.14f) else Color.Transparent)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.onNav, modifier = Modifier.size(iconSize))
        if (badgeCount != null && badgeCount > 0) {
            CountBadge(count = badgeCount, modifier = Modifier.align(Alignment.TopEnd).offset(x = (-6).dp, y = 6.dp))
        }
    }
}

@Composable
fun CountBadge(count: Int, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .heightIn(min = 18.dp)
            .widthIn(min = 18.dp)
            .clip(CircleShape)
            .background(colors.dangerDot)
            .padding(horizontal = 5.dp),
    ) {
        Text(text = if (count > 99) "99+" else count.toString(), fontSize = 10.sp, fontWeight = FontWeight.Bold, color = Color.White, maxLines = 1)
    }
}

@Composable
fun AvatarButton(
    initials: String,
    description: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(44.dp)
            .clip(CircleShape)
            .background(colors.navActive)
            .border(2.dp, Color.White.copy(alpha = 0.25f), CircleShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Text(text = initials, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.navAccent, maxLines = 1)
    }
}

fun initialsOf(name: String): String =
    name.split(' ', '.', '_', '-').filter { it.isNotBlank() }.take(2).joinToString("") { it.first().uppercase() }

@Composable
fun SearchScanField(
    placeholder: String,
    modifier: Modifier = Modifier,
    value: String = "",
    onValueChange: (String) -> Unit = {},
    onScan: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val scanDescription = stringResource(R.string.nav_scan)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .fillMaxWidth()
            .height(46.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, colors.borderStrong, ControlShape)
            .padding(start = 14.dp, end = 6.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_search), contentDescription = null, tint = colors.textQuaternary, modifier = Modifier.size(20.dp))
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            modifier = Modifier.weight(1f).semantics { contentDescription = placeholder },
            decorationBox = { inner ->
                Box(contentAlignment = Alignment.CenterStart) {
                    if (value.isEmpty()) {
                        Text(text = placeholder, fontSize = 15.sp, color = colors.textQuaternary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                    inner()
                }
            },
        )
        if (onScan != null || LocalWarehouses.current.isNotEmpty()) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .size(36.dp)
                    .clip(ControlShape)
                    .background(colors.scan)
                    .clickable(role = Role.Button, onClickLabel = scanDescription, onClick = onScan ?: { navigator.open(ScanCameraRoute) })
                    .semantics { contentDescription = scanDescription },
            ) {
                Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(18.dp))
            }
        }
    }
}

@Composable
fun ScanSquareButton(onClick: () -> Unit, modifier: Modifier = Modifier, size: Dp = 48.dp) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.nav_scan)

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(size)
            .clip(RoundedCornerShape(8.dp))
            .background(colors.scan)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(24.dp))
    }
}

private data class BarItem(val tab: MainTab, @StringRes val label: Int, @DrawableRes val icon: Int, val sampleBadge: Int? = null)

@Composable
fun MainBar(active: MainTab?, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val showScan = LocalWarehouses.current.isNotEmpty()

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .fillMaxWidth()
            .background(colors.nav)
            .navigationBarsPadding()
            .padding(horizontal = 8.dp, vertical = 8.dp),
    ) {
        BarTab(BarItem(MainTab.Home, R.string.tab_home, R.drawable.ic_ui_home), isActive = active == MainTab.Home, onClick = { navigator.openTab(MainTab.Home) })
        BarTab(BarItem(MainTab.MyWork, R.string.tab_my_work, R.drawable.ic_ui_my_work, SAMPLE_MY_WORK_COUNT), isActive = active == MainTab.MyWork, onClick = { navigator.openTab(MainTab.MyWork) })
        if (showScan) {
            Box(modifier = Modifier.weight(1f), contentAlignment = Alignment.Center) {
                ScanTabButton(onClick = { navigator.openTab(MainTab.Scan) })
            }
        }
        BarTab(BarItem(MainTab.Inbox, R.string.tab_inbox, R.drawable.ic_ui_inbox, SAMPLE_INBOX_COUNT), isActive = active == MainTab.Inbox, onClick = { navigator.openTab(MainTab.Inbox) })
        BarTab(BarItem(MainTab.Menu, R.string.tab_menu, R.drawable.ic_ui_menu), isActive = active == MainTab.Menu, onClick = { navigator.openTab(MainTab.Menu) })
    }
}

@Composable
private fun RowScope.BarTab(item: BarItem, isActive: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val tint = if (isActive) colors.navAccent else colors.onNavSecondary
    val shape = RoundedCornerShape(8.dp)

    Box(
        modifier = Modifier
            .weight(1f)
            .padding(horizontal = 4.dp)
            .heightIn(min = if (isActive) 52.dp else 48.dp)
            .clip(shape)
            .background(if (isActive) colors.navActive else Color.Transparent)
            .then(if (isActive) Modifier.border(1.dp, colors.navRing, shape) else Modifier)
            .selectable(selected = isActive, role = Role.Tab, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Icon(painter = painterResource(item.icon), contentDescription = null, tint = tint, modifier = Modifier.size(24.dp))
            Text(text = stringResource(item.label), fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = tint, maxLines = 1)
        }
        if (item.sampleBadge != null) {
            CountBadge(
                count = item.sampleBadge,
                modifier = Modifier.align(Alignment.TopCenter).offset(x = 15.dp, y = (-1).dp).fakeData(cornerRadius = 12.dp).padding(3.dp),
            )
        }
    }
}

@Composable
private fun ScanTabButton(onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.nav_scan)
    val shape = RoundedCornerShape(12.dp)

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .offset(y = (-20).dp)
            .size(60.dp)
            .shadow(elevation = 8.dp, shape = shape, ambientColor = colors.shadow, spotColor = colors.shadow)
            .clip(shape)
            .background(colors.scan)
            .border(4.dp, colors.divider, shape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(28.dp))
    }
}

@Composable
fun BoardStub(board: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = modifier.fillMaxWidth().fakeData().padding(24.dp),
    ) {
        Text(text = board, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.textTertiary, textAlign = TextAlign.Center)
    }
}
