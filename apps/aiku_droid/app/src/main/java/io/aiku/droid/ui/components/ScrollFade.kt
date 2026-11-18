package io.aiku.droid.ui.components

import androidx.compose.foundation.ScrollState
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp

val ScrollFadeWidth = 28.dp

internal fun fadeStrength(hiddenPixels: Int, fadePixels: Float): Float =
    if (fadePixels <= 0f) 0f else (hiddenPixels / fadePixels).coerceIn(0f, 1f)

fun Modifier.horizontalScrollFade(scrollState: ScrollState, color: Color, width: Dp = ScrollFadeWidth): Modifier = drawWithContent {
    drawContent()
    val edge = width.toPx().coerceAtMost(size.width / 2)
    val hiddenBefore = fadeStrength(scrollState.value, edge)
    val hiddenAfter = fadeStrength(scrollState.maxValue - scrollState.value, edge)
    val isRtl = layoutDirection == LayoutDirection.Rtl
    val left = if (isRtl) hiddenAfter else hiddenBefore
    val right = if (isRtl) hiddenBefore else hiddenAfter
    if (left > 0f) {
        drawRect(
            brush = Brush.horizontalGradient(listOf(color.copy(alpha = color.alpha * left), color.copy(alpha = 0f)), startX = 0f, endX = edge),
            size = Size(edge, size.height),
        )
    }
    if (right > 0f) {
        drawRect(
            brush = Brush.horizontalGradient(listOf(color.copy(alpha = 0f), color.copy(alpha = color.alpha * right)), startX = size.width - edge, endX = size.width),
            topLeft = Offset(size.width - edge, 0f),
            size = Size(edge, size.height),
        )
    }
}
