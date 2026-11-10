package io.aiku.droid.ui.states

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun StatesFullScreen(
    actions: @Composable ColumnScope.() -> Unit,
    modifier: Modifier = Modifier,
    header: (@Composable () -> Unit)? = null,
    spacing: Dp = 16.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxSize()
            .background(colors.background)
            .then(if (header == null) Modifier.statusBarsPadding() else Modifier),
    ) {
        header?.invoke()
        BoxWithConstraints(modifier = Modifier.weight(1f).fillMaxWidth()) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(spacing, Alignment.CenterVertically),
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState())
                    .heightIn(min = maxHeight)
                    .padding(horizontal = 28.dp, vertical = 24.dp),
                content = content,
            )
        }
        Column(
            verticalArrangement = Arrangement.spacedBy(10.dp),
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 16.dp),
            content = actions,
        )
    }
}

@Composable
internal fun StatesHeroIcon(
    @DrawableRes icon: Int,
    container: Color,
    tint: Color,
    modifier: Modifier = Modifier,
    size: Dp = 88.dp,
    iconSize: Dp = 40.dp,
) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(size)
            .clip(CircleShape)
            .background(container),
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = tint, modifier = Modifier.size(iconSize))
    }
}

@Composable
internal fun StatesTitle(text: String, modifier: Modifier = Modifier, fontSize: TextUnit = 22.sp) {
    Text(
        text = text,
        fontSize = fontSize,
        lineHeight = 28.sp,
        fontWeight = FontWeight.Bold,
        color = LocalAikuColors.current.text,
        textAlign = TextAlign.Center,
        modifier = modifier.semantics { heading() },
    )
}

@Composable
internal fun StatesBody(
    text: AnnotatedString,
    modifier: Modifier = Modifier,
    fontSize: TextUnit = 15.sp,
    lineHeight: TextUnit = 22.sp,
) {
    Text(
        text = text,
        fontSize = fontSize,
        lineHeight = lineHeight,
        fontWeight = FontWeight.Normal,
        color = LocalAikuColors.current.textTertiary,
        textAlign = TextAlign.Center,
        modifier = modifier.widthIn(max = 300.dp),
    )
}

@Composable
internal fun StatesButton(
    text: String,
    onClick: () -> Unit,
    style: ButtonStyle = ButtonStyle.Primary,
    @DrawableRes icon: Int? = null,
    height: Dp = 52.dp,
) {
    AikuButton(text = text, onClick = onClick, modifier = Modifier.fillMaxWidth(), style = style, icon = icon, height = height)
}

@Composable
internal fun StatesTextLink(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .heightIn(min = 44.dp)
            .clip(ChipShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 8.dp),
    ) {
        Text(text = text, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = LocalAikuColors.current.link)
    }
}

@Composable
internal fun StatesPreviewSurface(content: @Composable ColumnScope.() -> Unit) {
    Column(
        verticalArrangement = Arrangement.spacedBy(14.dp),
        modifier = Modifier
            .background(LocalAikuColors.current.surface)
            .padding(20.dp),
        content = content,
    )
}

internal fun emphasise(text: String, vararg parts: String): AnnotatedString = buildAnnotatedString {
    append(text)
    parts.filter { it.isNotBlank() }.forEach { part ->
        val start = text.indexOf(part)
        if (start >= 0) {
            addStyle(SpanStyle(fontWeight = FontWeight.Bold), start, start + part.length)
        }
    }
}

internal fun Modifier.statesDashedBorder(color: Color, cornerRadius: Dp): Modifier = drawBehind {
    val strokeWidth = 1.dp.toPx()
    drawRoundRect(
        color = color,
        topLeft = Offset(strokeWidth / 2, strokeWidth / 2),
        size = Size(size.width - strokeWidth, size.height - strokeWidth),
        cornerRadius = CornerRadius(cornerRadius.toPx()),
        style = Stroke(
            width = strokeWidth,
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 3.dp.toPx())),
        ),
    )
}
