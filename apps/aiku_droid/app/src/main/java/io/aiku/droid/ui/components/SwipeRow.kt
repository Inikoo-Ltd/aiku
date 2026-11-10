package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectHorizontalDragGestures
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.CustomAccessibilityAction
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.customActions
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlin.math.abs
import kotlin.math.roundToInt
import kotlinx.coroutines.launch

enum class SwipeDirection { Right, Left }

data class SwipeAction(
    val label: String,
    @DrawableRes val icon: Int,
    val container: Color,
    val content: Color,
    val onClick: () -> Unit,
)

private val SwipeRightOpenWidth = 120.dp
private val SwipeLeftActionWidth = 100.dp
private val SwipeLeftOpenWidth = 200.dp
private val SwipeOpenThreshold = 50.dp
private val SwipeFullThreshold = 240.dp
private val SwipeFullLeftExtra = 30.dp
private val SwipeMaxOffset = 300.dp
private val SwipeMoveSlop = 6.dp
private const val SWIPE_SETTLE_MILLIS = 200

@Composable
fun SwipeRow(
    openDirection: SwipeDirection?,
    onOpenDirectionChange: (SwipeDirection?) -> Unit,
    rightAction: SwipeAction,
    rightTrayColor: Color,
    leftActions: List<SwipeAction>,
    leftTrayColor: Color,
    onFullSwipeRight: () -> Unit,
    onFullSwipeLeft: () -> Unit,
    background: Color,
    modifier: Modifier = Modifier,
    minHeight: Dp = 72.dp,
    onDragStarted: () -> Unit = {},
    content: @Composable RowScope.() -> Unit,
) {
    val density = LocalDensity.current
    val scope = rememberCoroutineScope()
    val offset = remember { Animatable(0f) }
    val rightOpenPx = with(density) { SwipeRightOpenWidth.toPx() }
    val leftOpenPx = with(density) { SwipeLeftOpenWidth.toPx() }
    val openThresholdPx = with(density) { SwipeOpenThreshold.toPx() }
    val fullRightPx = with(density) { SwipeFullThreshold.toPx() }
    val fullLeftPx = with(density) { (SwipeFullThreshold + SwipeFullLeftExtra).toPx() }
    val maxOffsetPx = with(density) { SwipeMaxOffset.toPx() }
    val moveSlopPx = with(density) { SwipeMoveSlop.toPx() }
    val currentOpenDirection by rememberUpdatedState(openDirection)
    val currentOnOpenDirectionChange by rememberUpdatedState(onOpenDirectionChange)
    val currentOnFullSwipeRight by rememberUpdatedState(onFullSwipeRight)
    val currentOnFullSwipeLeft by rememberUpdatedState(onFullSwipeLeft)
    val currentOnDragStarted by rememberUpdatedState(onDragStarted)
    val revealed by remember {
        derivedStateOf {
            when {
                offset.value > 0.5f -> SwipeDirection.Right
                offset.value < -0.5f -> SwipeDirection.Left
                else -> null
            }
        }
    }
    val restingOffset: (SwipeDirection?) -> Float = { direction ->
        when (direction) {
            SwipeDirection.Right -> rightOpenPx
            SwipeDirection.Left -> -leftOpenPx
            null -> 0f
        }
    }
    val accessibilityActions = (listOf(rightAction) + leftActions).map { action ->
        CustomAccessibilityAction(label = action.label) {
            action.onClick()
            true
        }
    }

    LaunchedEffect(openDirection) {
        offset.animateTo(restingOffset(openDirection), tween(SWIPE_SETTLE_MILLIS))
    }

    Box(
        modifier = modifier
            .fillMaxWidth()
            .clipToBounds()
            .semantics { customActions = accessibilityActions },
    ) {
        when (revealed) {
            SwipeDirection.Right -> Row(modifier = Modifier.matchParentSize().background(rightTrayColor)) {
                SwipeActionButton(action = rightAction, width = SwipeRightOpenWidth)
            }
            SwipeDirection.Left -> Row(
                horizontalArrangement = Arrangement.End,
                modifier = Modifier.matchParentSize().background(leftTrayColor),
            ) {
                leftActions.forEach { SwipeActionButton(action = it, width = SwipeLeftActionWidth) }
            }
            null -> Unit
        }
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .offset { IntOffset(offset.value.roundToInt(), 0) }
                .fillMaxWidth()
                .background(background)
                .pointerInput(density) {
                    var dragStart = 0f
                    var dragOffset = 0f
                    detectHorizontalDragGestures(
                        onDragStart = {
                            dragStart = offset.value
                            dragOffset = offset.value
                            currentOnDragStarted()
                        },
                        onDragEnd = {
                            val hasMoved = abs(dragOffset - dragStart) > moveSlopPx
                            val target = when {
                                !hasMoved -> null
                                dragOffset > fullRightPx -> {
                                    currentOnFullSwipeRight()
                                    null
                                }
                                dragOffset > openThresholdPx -> SwipeDirection.Right
                                dragOffset < -fullLeftPx -> {
                                    currentOnFullSwipeLeft()
                                    null
                                }
                                dragOffset < -openThresholdPx -> SwipeDirection.Left
                                else -> null
                            }
                            currentOnOpenDirectionChange(target)
                            scope.launch { offset.animateTo(restingOffset(target), tween(SWIPE_SETTLE_MILLIS)) }
                        },
                        onDragCancel = {
                            scope.launch { offset.animateTo(restingOffset(currentOpenDirection), tween(SWIPE_SETTLE_MILLIS)) }
                        },
                        onHorizontalDrag = { change, dragAmount ->
                            change.consume()
                            dragOffset = (dragOffset + dragAmount).coerceIn(-maxOffsetPx, maxOffsetPx)
                            scope.launch { offset.snapTo(dragOffset) }
                        },
                    )
                }
                .clickable(
                    interactionSource = remember { MutableInteractionSource() },
                    indication = null,
                    enabled = openDirection != null,
                    onClick = { onOpenDirectionChange(null) },
                )
                .heightIn(min = minHeight)
                .height(IntrinsicSize.Min)
                .padding(start = 14.dp, top = 10.dp, end = 6.dp, bottom = 10.dp),
            content = content,
        )
    }
}

@Composable
private fun SwipeActionButton(action: SwipeAction, width: Dp) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
        modifier = Modifier
            .width(width)
            .fillMaxHeight()
            .background(action.container)
            .clickable(role = Role.Button, onClick = action.onClick)
            .padding(horizontal = 4.dp),
    ) {
        Icon(painter = painterResource(action.icon), contentDescription = null, tint = action.content, modifier = Modifier.size(24.dp))
        Text(
            text = action.label,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = action.content,
            textAlign = TextAlign.Center,
            maxLines = 2,
        )
    }
}

@Composable
fun SwipeStatusBar(color: Color) {
    Box(
        modifier = Modifier
            .width(6.dp)
            .fillMaxHeight()
            .background(color = color, shape = RoundedCornerShape(999.dp)),
    )
}
