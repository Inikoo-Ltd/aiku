package io.aiku.droid.ui.goodsin

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppTabBar
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.LocalAikuColors

internal data class GoodsDetailTab(
    val label: String,
    @DrawableRes val icon: Int,
    val isActive: Boolean = false,
    val showLabel: Boolean = true,
)

internal data class GoodsPanelAction(val label: String, @DrawableRes val icon: Int, val onClick: () -> Unit)

@Composable
internal fun GoodsDetailScaffold(
    header: @Composable () -> Unit,
    activeTab: AppTab?,
    modifier: Modifier = Modifier,
    bottomBar: (@Composable RowScope.() -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxSize().background(colors.background)) {
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
        if (bottomBar == null) {
            AppTabBar(active = activeTab)
        } else {
            Column(modifier = Modifier.fillMaxWidth().background(colors.surface)) {
                HorizontalDivider(thickness = 1.dp, color = colors.border)
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                    modifier = Modifier
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    content = bottomBar,
                )
            }
        }
    }
}

@Composable
internal fun GoodsDetailHeader(
    kicker: String,
    @DrawableRes kickerIcon: Int,
    title: String,
    tabs: List<GoodsDetailTab>,
    modifier: Modifier = Modifier,
    trailingTabs: List<GoodsDetailTab> = emptyList(),
) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(top = 8.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            modifier = Modifier.padding(horizontal = 16.dp),
        ) {
            HeaderIconButton(icon = R.drawable.ic_chevron_left, description = stringResource(R.string.nav_back), onClick = navigator::back)
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Icon(
                        painter = painterResource(kickerIcon),
                        contentDescription = null,
                        tint = colors.onNavSecondary,
                        modifier = Modifier.size(14.dp),
                    )
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
            HeaderIconButton(
                icon = R.drawable.ic_goodsin_more,
                description = stringResource(R.string.goodsin_more_actions),
                onClick = {},
                filled = true,
            )
        }
        Row(
            horizontalArrangement = Arrangement.spacedBy(2.dp),
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 12.dp, end = 12.dp, top = 12.dp),
        ) {
            tabs.forEach { GoodsDetailTabItem(tab = it) }
            if (trailingTabs.isNotEmpty()) {
                Spacer(modifier = Modifier.weight(1f))
                trailingTabs.forEach { GoodsDetailTabItem(tab = it) }
            }
        }
    }
}

@Composable
private fun GoodsDetailTabItem(tab: GoodsDetailTab) {
    val colors = LocalAikuColors.current
    val tint = if (tab.isActive) colors.navAccent else colors.onNavSecondary
    val indicator = colors.navAccent

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .height(46.dp)
            .then(
                if (tab.isActive) {
                    Modifier.drawBehind {
                        val thickness = 3.dp.toPx()
                        drawRect(color = indicator, topLeft = Offset(0f, size.height - thickness), size = Size(size.width, thickness))
                    }
                } else {
                    Modifier
                },
            )
            .clickable(role = Role.Tab, onClickLabel = tab.label, onClick = {})
            .padding(horizontal = if (tab.showLabel) 10.dp else 9.dp),
    ) {
        Icon(
            painter = painterResource(tab.icon),
            contentDescription = if (tab.showLabel) null else tab.label,
            tint = tint,
            modifier = Modifier.size(16.dp),
        )
        if (tab.showLabel) {
            Text(
                text = tab.label,
                fontSize = 13.sp,
                fontWeight = if (tab.isActive) FontWeight.SemiBold else FontWeight.Medium,
                color = tint,
                maxLines = 1,
            )
        }
    }
}

@Composable
internal fun GoodsStateStepper(steps: List<String>, currentIndex: Int, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(modifier = modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
        steps.forEachIndexed { index, label ->
            val isDone = index < currentIndex
            val isCurrent = index == currentIndex
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.weight(1f),
            ) {
                GoodsStepMarker(number = index + 1, isDone = isDone, isCurrent = isCurrent)
                Text(
                    text = label,
                    fontSize = 11.sp,
                    fontWeight = when {
                        isCurrent -> FontWeight.Bold
                        isDone -> FontWeight.SemiBold
                        else -> FontWeight.Normal
                    },
                    color = when {
                        isCurrent -> colors.text
                        isDone -> colors.textSecondary
                        else -> colors.textTertiary
                    },
                    textAlign = TextAlign.Center,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                )
            }
        }
    }
}

@Composable
private fun GoodsStepMarker(number: Int, isDone: Boolean, isCurrent: Boolean) {
    val colors = LocalAikuColors.current
    val ring = colors.softBorder
    val marker = Modifier.size(28.dp)

    when {
        isDone -> Box(contentAlignment = Alignment.Center, modifier = marker.clip(CircleShape).background(colors.primary)) {
            Icon(
                painter = painterResource(R.drawable.ic_goodsin_check),
                contentDescription = null,
                tint = colors.onPrimary,
                modifier = Modifier.size(14.dp),
            )
        }
        isCurrent -> Box(
            contentAlignment = Alignment.Center,
            modifier = marker
                .drawBehind { drawCircle(color = ring, radius = size.minDimension / 2 + 3.dp.toPx()) }
                .clip(CircleShape)
                .background(colors.navActive),
        ) {
            Text(text = number.toString(), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.navAccent)
        }
        else -> Box(
            contentAlignment = Alignment.Center,
            modifier = marker
                .clip(CircleShape)
                .background(colors.background)
                .border(1.dp, colors.borderStrong, CircleShape),
        ) {
            Text(text = number.toString(), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.textTertiary)
        }
    }
}

@Composable
internal fun GoodsScanPanel(
    title: String,
    status: String,
    primaryAction: GoodsPanelAction,
    secondaryAction: GoodsPanelAction,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.primary)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .size(44.dp)
                    .clip(ControlShape)
                    .background(colors.scan),
            ) {
                Icon(
                    painter = painterResource(R.drawable.ic_goodsin_scanner),
                    contentDescription = null,
                    tint = colors.onScan,
                    modifier = Modifier.size(22.dp),
                )
            }
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.onPrimary)
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Box(
                        modifier = Modifier
                            .size(8.dp)
                            .clip(CircleShape)
                            .background(colors.success),
                    )
                    Text(text = status, fontSize = 12.sp, color = colors.onNavSecondary)
                }
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            GoodsPanelButton(
                action = primaryAction,
                container = colors.scan,
                content = colors.onScan,
                border = null,
                modifier = Modifier.weight(1f),
            )
            GoodsPanelButton(
                action = secondaryAction,
                container = Color.Transparent,
                content = colors.onNav,
                border = colors.onPrimary.copy(alpha = 0.35f),
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun GoodsPanelButton(
    action: GoodsPanelAction,
    container: Color,
    content: Color,
    border: Color?,
    modifier: Modifier = Modifier,
) {
    Row(
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .heightIn(min = 44.dp)
            .clip(ControlShape)
            .background(container)
            .then(if (border != null) Modifier.border(1.dp, border, ControlShape) else Modifier)
            .clickable(role = Role.Button, onClick = action.onClick)
            .padding(horizontal = 8.dp),
    ) {
        Icon(painter = painterResource(action.icon), contentDescription = null, tint = content, modifier = Modifier.size(16.dp))
        Spacer(modifier = Modifier.width(8.dp))
        Text(text = action.label, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = content, maxLines = 1)
    }
}

@Composable
internal fun GoodsQuantityStepper(
    quantity: Int,
    onQuantityChange: (Int) -> Unit,
    decreaseDescription: String,
    increaseDescription: String,
    modifier: Modifier = Modifier,
    total: Int? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .fillMaxWidth()
            .height(50.dp)
            .goodsFocusGlow(color = colors.primary.copy(alpha = 0.25f), cornerRadius = 8.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, colors.primary, ControlShape),
    ) {
        GoodsStepperButton(symbol = stringResource(R.string.goodsin_minus), description = decreaseDescription, onClick = { onQuantityChange(quantity - 1) })
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(colors.border))
        Row(
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.weight(1f),
        ) {
            Text(
                text = quantity.toString(),
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.alignByBaseline(),
            )
            if (total != null) {
                Text(
                    text = stringResource(R.string.goodsin_of_total, total),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium,
                    color = colors.textTertiary,
                    modifier = Modifier.alignByBaseline().padding(start = 4.dp),
                )
            }
        }
        Box(modifier = Modifier.width(1.dp).fillMaxHeight().background(colors.border))
        GoodsStepperButton(symbol = stringResource(R.string.goodsin_plus), description = increaseDescription, onClick = { onQuantityChange(quantity + 1) })
    }
}

@Composable
private fun GoodsStepperButton(symbol: String, description: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .width(56.dp)
            .fillMaxHeight()
            .background(colors.surfaceMuted)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Text(text = symbol, fontSize = 22.sp, color = colors.textSecondary)
    }
}

@Composable
internal fun GoodsSectionHeading(
    title: String,
    @DrawableRes icon: Int,
    modifier: Modifier = Modifier,
    trailing: @Composable () -> Unit = {},
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 4.dp),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(18.dp))
        Spacer(modifier = Modifier.width(8.dp))
        Text(text = title, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
        trailing()
    }
}

@Composable
internal fun GoodsLineRow(
    code: String,
    description: String,
    quantity: String,
    modifier: Modifier = Modifier,
    leading: @Composable () -> Unit = {},
    descriptionColor: Color = LocalAikuColors.current.textTertiary,
    quantityColor: Color = LocalAikuColors.current.text,
    isQuantityStrong: Boolean = true,
    background: Color = Color.Transparent,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .background(background)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        leading()
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(text = description, fontSize = 13.sp, color = descriptionColor, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
        Text(
            text = quantity,
            fontSize = 15.sp,
            fontWeight = if (isQuantityStrong) FontWeight.Bold else FontWeight.SemiBold,
            color = quantityColor,
        )
    }
}

@Composable
internal fun GoodsQueueRow(
    title: String,
    subtitle: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    actionLabel: String? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
        if (actionLabel != null) {
            Text(text = actionLabel, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.link)
        }
    }
}

@Composable
internal fun GoodsCaptionRow(start: String, end: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 4.dp),
    ) {
        Text(text = start, fontSize = 12.sp, color = colors.textTertiary, modifier = Modifier.weight(1f))
        Text(text = end, fontSize = 12.sp, color = colors.textTertiary, textAlign = TextAlign.End)
    }
}

@Composable
internal fun GoodsScanActionButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = modifier
            .height(52.dp)
            .clip(ControlShape)
            .background(colors.scan)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 16.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(22.dp))
        Text(text = text, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.onScan, maxLines = 1)
    }
}

@Composable
internal fun goodsHighlightedCount(text: String, count: Int): AnnotatedString {
    val colors = LocalAikuColors.current
    val countText = count.toString()

    return buildAnnotatedString {
        append(text)
        val start = text.indexOf(countText)
        if (start >= 0) {
            addStyle(SpanStyle(color = colors.text, fontWeight = FontWeight.Bold), start, start + countText.length)
        }
    }
}

internal fun Modifier.goodsFocusGlow(color: Color, cornerRadius: Dp, spread: Dp = 3.dp): Modifier = drawBehind {
    val spreadPx = spread.toPx()
    drawRoundRect(
        color = color,
        topLeft = Offset(-spreadPx, -spreadPx),
        size = Size(size.width + spreadPx * 2, size.height + spreadPx * 2),
        cornerRadius = CornerRadius(cornerRadius.toPx() + spreadPx),
    )
}

internal fun Modifier.goodsActiveEdge(color: Color, width: Dp = 3.dp): Modifier = drawBehind {
    drawRect(color = color, size = Size(width.toPx(), size.height))
}
