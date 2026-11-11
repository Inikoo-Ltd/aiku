package io.aiku.droid.ui.components

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.TweenSpec
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.IndicationNodeFactory
import androidx.compose.foundation.interaction.FocusInteraction
import androidx.compose.foundation.interaction.InteractionSource
import androidx.compose.material3.ripple
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.ContentDrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.node.DelegatableNode
import androidx.compose.ui.node.DelegatingNode
import androidx.compose.ui.node.DrawModifierNode
import androidx.compose.ui.node.invalidateDraw
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.min
import kotlinx.coroutines.launch

private val FocusRingWidth = 2.dp
private val FocusGapWidth = 1.5.dp
private val FocusRingCorner = 10.dp
private val CompactControlSize = 64.dp
private val SquareTolerance = 8.dp
private val SlimControlHeight = 40.dp
private val FocusHaloWidth = 3.dp
private const val FOCUS_HALO_ALPHA = 0.25f
private const val FOCUS_IN_MILLIS = 160
private const val FOCUS_OUT_MILLIS = 120
private const val FOCUS_RING_START_SCALE = 0.5f

fun focusAnimationSpec(isFocusing: Boolean): TweenSpec<Float> =
    tween(durationMillis = if (isFocusing) FOCUS_IN_MILLIS else FOCUS_OUT_MILLIS, easing = FastOutSlowInEasing)

@Composable
fun animateFocusProgress(isFocused: Boolean): Float {
    val progress by animateFloatAsState(
        targetValue = if (isFocused) 1f else 0f,
        animationSpec = focusAnimationSpec(isFocused),
        label = "focus",
    )
    return progress
}

data class AikuFocusIndication(
    val ringColor: Color,
    val gapColor: Color,
    val ripple: IndicationNodeFactory,
    val cornerRadius: Dp? = null,
    val bottomInset: Dp = 0.dp,
) : IndicationNodeFactory {
    override fun create(interactionSource: InteractionSource): DelegatableNode =
        FocusRingNode(interactionSource, this, ripple.create(interactionSource))
}

@Composable
fun rememberNavFocusIndication(cornerRadius: Dp? = null, bottomInset: Dp = 0.dp): IndicationNodeFactory {
    val colors = LocalAikuColors.current
    return remember(colors.navAccent, colors.nav, cornerRadius, bottomInset) {
        AikuFocusIndication(ringColor = colors.navAccent, gapColor = colors.nav, ripple = ripple(), cornerRadius = cornerRadius, bottomInset = bottomInset)
    }
}

@Composable
fun rememberTabIndication(cornerRadius: Dp): IndicationNodeFactory {
    val colors = LocalAikuColors.current
    return remember(colors.focus, colors.background, cornerRadius) {
        AikuFocusIndication(ringColor = colors.focus, gapColor = colors.background, ripple = ripple(), cornerRadius = cornerRadius)
    }
}

private class FocusRingNode(
    private val interactionSource: InteractionSource,
    private val style: AikuFocusIndication,
    rippleNode: DelegatableNode,
) : DelegatingNode(), DrawModifierNode {
    private val activeFocus = mutableListOf<FocusInteraction.Focus>()
    private val progress = Animatable(0f)
    private var isVisible = false

    init {
        delegate(rippleNode)
    }

    override fun onAttach() {
        coroutineScope.launch {
            interactionSource.interactions.collect { interaction ->
                when (interaction) {
                    is FocusInteraction.Focus -> activeFocus.add(interaction)
                    is FocusInteraction.Unfocus -> activeFocus.remove(interaction.focus)
                }
                updateVisibility()
            }
        }
    }

    private fun updateVisibility() {
        val visible = activeFocus.isNotEmpty()
        if (visible == isVisible) {
            return
        }
        isVisible = visible
        coroutineScope.launch {
            progress.animateTo(if (visible) 1f else 0f, focusAnimationSpec(visible)) { invalidateDraw() }
        }
    }

    override fun ContentDrawScope.draw() {
        drawContent()
        val visibility = progress.value
        if (visibility <= 0f) {
            return
        }
        val grow = FOCUS_RING_START_SCALE + (1 - FOCUS_RING_START_SCALE) * visibility
        val ring = FocusRingWidth.toPx() * grow
        val gap = FocusGapWidth.toPx() * grow
        val area = Size(size.width, size.height - style.bottomInset.toPx())
        val radius = style.cornerRadius?.toPx() ?: fittingRadius(area)
        insetStroke(area, color = style.ringColor.copy(alpha = style.ringColor.alpha * visibility), inset = 0f, width = ring, radius = radius)
        insetStroke(area, color = style.gapColor.copy(alpha = style.gapColor.alpha * visibility), inset = ring, width = gap, radius = radius)
    }

    private fun ContentDrawScope.fittingRadius(area: Size): Float {
        val isCompactSquare = max(area.width, area.height) <= CompactControlSize.toPx() &&
            abs(area.width - area.height) <= SquareTolerance.toPx()
        return when {
            isCompactSquare -> min(area.width, area.height) / 2
            area.height <= SlimControlHeight.toPx() -> area.height / 2
            else -> FocusRingCorner.toPx()
        }
    }

    private fun ContentDrawScope.insetStroke(area: Size, color: Color, inset: Float, width: Float, radius: Float) {
        val center = inset + width / 2
        drawRoundRect(
            color = color,
            topLeft = Offset(center, center),
            size = Size(area.width - center * 2, area.height - center * 2),
            cornerRadius = CornerRadius(max(radius - center, 0f)),
            style = Stroke(width = width),
        )
    }
}

fun Modifier.focusHalo(progress: Float, color: Color, cornerRadius: Dp): Modifier =
    if (progress <= 0f) {
        this
    } else {
        drawBehind {
            val halo = FocusHaloWidth.toPx() * progress
            drawRoundRect(
                color = color.copy(alpha = FOCUS_HALO_ALPHA * progress),
                topLeft = Offset(-halo, -halo),
                size = Size(size.width + halo * 2, size.height + halo * 2),
                cornerRadius = CornerRadius(cornerRadius.toPx() + halo),
            )
        }
    }
