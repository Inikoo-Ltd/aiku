package io.aiku.droid.ui.inventory

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.actions.AdjustStockSheet
import io.aiku.droid.ui.actions.AuditLocationSheet
import io.aiku.droid.ui.actions.TransferStockSheet
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.HeaderTab
import io.aiku.droid.ui.components.HeaderTabs
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ScanSquareButton
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.tone
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.SkuFamiliesRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.theme.LocalAikuColors

internal enum class StockSheet { Transfer, Adjust, Audit }

@Composable
internal fun StockSheetHost(sheet: StockSheet?, onDismiss: () -> Unit) {
    when (sheet) {
        StockSheet.Transfer -> TransferStockSheet(onDismiss = onDismiss)
        StockSheet.Adjust -> AdjustStockSheet(onDismiss = onDismiss)
        StockSheet.Audit -> AuditLocationSheet(onDismiss = onDismiss)
        null -> Unit
    }
}

internal enum class InventorySection { Dashboard, Families, Skus }

@Composable
internal fun InventorySectionTabs(active: InventorySection) {
    val navigator = LocalAppNavigator.current

    HeaderTabs(
        tabs = listOf(
            HeaderTab(
                label = "",
                description = stringResource(R.string.tab_dashboard),
                icon = R.drawable.ic_tab_dashboard,
                isActive = active == InventorySection.Dashboard,
                onClick = { if (active != InventorySection.Dashboard) navigator.switchSection(InventoryDashboardRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.inventory_section_families),
                icon = R.drawable.ic_inventory_families,
                isActive = active == InventorySection.Families,
                onClick = { if (active != InventorySection.Families) navigator.switchSection(SkuFamiliesRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.inventory_section_skus),
                icon = R.drawable.ic_inventory_skus,
                isActive = active == InventorySection.Skus,
                onClick = { if (active != InventorySection.Skus) navigator.switchSection(SkusRoute) },
            ),
        ),
    )
}

@Composable
internal fun StockDetailScaffold(
    header: @Composable () -> Unit,
    bottomBar: @Composable () -> Unit,
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(modifier = Modifier.fillMaxSize().background(colors.background)) {
        header()
        Column(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
            content = content,
        )
        bottomBar()
    }
}

internal data class DetailTab(val label: String, @DrawableRes val icon: Int, val showLabel: Boolean = true)

@Composable
internal fun StockDetailHeader(
    kicker: String,
    @DrawableRes kickerIcon: Int,
    title: String,
    tabs: List<DetailTab>,
    moreDescription: String,
    showMore: Boolean,
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(top = 8.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            HeaderIconButton(icon = R.drawable.ic_chevron_left, description = stringResource(R.string.nav_back), onClick = navigator::back)
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Icon(painter = painterResource(kickerIcon), contentDescription = null, tint = colors.onNavSecondary, modifier = Modifier.size(14.dp))
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
            if (showMore) {
                HeaderIconButton(icon = R.drawable.ic_locations_more, description = moreDescription, onClick = {}, filled = true)
            }
        }
        Row(
            horizontalArrangement = Arrangement.spacedBy(2.dp),
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState())
                .padding(horizontal = 12.dp),
        ) {
            tabs.forEachIndexed { index, tab -> DetailTabItem(tab = tab, isActive = index == 0) }
        }
    }
}

@Composable
private fun DetailTabItem(tab: DetailTab, isActive: Boolean) {
    val colors = LocalAikuColors.current
    val tint = if (isActive) colors.navAccent else colors.onNavSecondary
    val underline = colors.navAccent

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .height(46.dp)
            .drawBehind {
                if (isActive) {
                    val stroke = 3.dp.toPx()
                    drawRect(color = underline, topLeft = Offset(0f, size.height - stroke), size = Size(size.width, stroke))
                }
            }
            .clickable(role = Role.Tab, onClick = {})
            .then(if (tab.showLabel) Modifier else Modifier.semantics { contentDescription = tab.label })
            .padding(horizontal = 10.dp),
    ) {
        Icon(painter = painterResource(tab.icon), contentDescription = null, tint = tint, modifier = Modifier.size(16.dp))
        if (tab.showLabel) {
            Text(
                text = tab.label,
                fontSize = 13.sp,
                fontWeight = if (isActive) FontWeight.SemiBold else FontWeight.Medium,
                color = tint,
                maxLines = 1,
            )
        }
    }
}

internal data class StockAction(val label: String, @DrawableRes val icon: Int, val onClick: () -> Unit)

@Composable
internal fun StockActionBar(onScan: () -> Unit, secondary: StockAction, primary: StockAction) {
    val colors = LocalAikuColors.current

    Column(modifier = Modifier.fillMaxWidth().background(colors.surface)) {
        Box(modifier = Modifier.fillMaxWidth().height(1.dp).background(colors.border))
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 16.dp),
        ) {
            ScanSquareButton(onClick = onScan, size = 52.dp)
            ActionBarButton(action = secondary, isPrimary = false, modifier = Modifier.weight(1f))
            ActionBarButton(action = primary, isPrimary = true, modifier = Modifier.weight(1f))
        }
    }
}

@Composable
private fun ActionBarButton(action: StockAction, isPrimary: Boolean, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val container = if (isPrimary) colors.primary else colors.soft
    val content = if (isPrimary) colors.onPrimary else colors.onScan
    val border = if (isPrimary) colors.primaryBorder else colors.softBorder

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
        modifier = modifier
            .heightIn(min = 52.dp)
            .clip(ControlShape)
            .background(container)
            .border(1.dp, border, ControlShape)
            .clickable(role = Role.Button, onClick = action.onClick)
            .padding(horizontal = 8.dp, vertical = 6.dp),
    ) {
        Icon(painter = painterResource(action.icon), contentDescription = null, tint = content, modifier = Modifier.size(18.dp))
        Text(
            text = action.label,
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold,
            color = content,
            textAlign = TextAlign.Center,
            lineHeight = 18.sp,
        )
    }
}

internal data class StockMovement(val title: String, val detail: String, val change: String, val tone: Tone)

@Composable
internal fun MovementList(movements: List<StockMovement>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(horizontal = 16.dp, vertical = 4.dp),
    ) {
        movements.forEachIndexed { index, movement ->
            if (index > 0) {
                RowDivider()
            }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth().padding(vertical = 12.dp)) {
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = movement.title, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
                    Text(text = movement.detail, fontSize = 12.sp, color = colors.textTertiary)
                }
                Text(text = movement.change, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.tone(movement.tone).content)
            }
        }
    }
}

@Composable
internal fun StockRow(
    title: String,
    onClick: (() -> Unit)?,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    badges: @Composable RowScope.() -> Unit = {},
    quantity: String? = null,
    quantitySize: TextUnit = 16.sp,
    quantityTone: Tone? = null,
    caption: String? = null,
    trailing: @Composable RowScope.() -> Unit = {},
    verticalPadding: Dp = 12.dp,
    showChevron: Boolean = true,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 16.dp, vertical = verticalPadding),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = title,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                badges()
            }
            if (subtitle != null) {
                Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
        if (quantity != null) {
            Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = quantity,
                    fontSize = quantitySize,
                    fontWeight = FontWeight.Bold,
                    color = quantityTone?.let { colors.tone(it).content } ?: colors.text,
                )
                if (caption != null) {
                    Text(text = caption, fontSize = 11.sp, color = colors.textTertiary)
                }
            }
        }
        trailing()
        if (showChevron && onClick != null) {
            Icon(painter = painterResource(R.drawable.ic_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
        }
    }
}

internal data class StripValue(val value: String, val label: String)

@Composable
internal fun StatStrip(values: List<StripValue>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        modifier = modifier
            .fillMaxWidth()
            .height(IntrinsicSize.Min)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape),
    ) {
        values.forEachIndexed { index, item ->
            if (index > 0) {
                Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(colors.border))
            }
            Column(modifier = Modifier.weight(1f).padding(14.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = item.value, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1)
                Text(text = item.label, fontSize = 12.sp, color = colors.textTertiary, maxLines = 1)
            }
        }
    }
}

@Composable
internal fun ListMetaRow(start: String, modifier: Modifier = Modifier, end: @Composable RowScope.() -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp),
    ) {
        MetaText(text = start, modifier = Modifier.weight(1f))
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp), content = end)
    }
}

@Composable
internal fun MetaText(text: String, modifier: Modifier = Modifier) {
    Text(text = text, fontSize = 12.sp, color = LocalAikuColors.current.textTertiary, maxLines = 1, modifier = modifier)
}

@Composable
internal fun ListFooter(text: String, modifier: Modifier = Modifier) {
    Text(text = text, fontSize = 13.sp, color = LocalAikuColors.current.textTertiary, modifier = modifier.padding(6.dp))
}
