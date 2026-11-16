package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.Stable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.delay

private const val TOAST_DURATION_MILLIS = 4_000L
private const val SHIMMER_DURATION_MILLIS = 1_400

enum class AlertTone { Danger, Warn, Info, Success }

private data class AlertColors(val container: Color, val border: Color, val icon: Color, val title: Color, @DrawableRes val iconRes: Int)

@Composable
private fun alertColors(tone: AlertTone): AlertColors {
    val colors = LocalAikuColors.current
    return when (tone) {
        AlertTone.Danger -> AlertColors(colors.dangerSoft, colors.dangerBorder, colors.danger, colors.dangerInk, R.drawable.ic_feedback_danger)
        AlertTone.Warn -> AlertColors(colors.warnSoft, colors.warnBorder, colors.warn, colors.warnInk, R.drawable.ic_feedback_warn)
        AlertTone.Info -> AlertColors(colors.soft, colors.softBorder, colors.link, colors.link, R.drawable.ic_feedback_info)
        AlertTone.Success -> AlertColors(colors.successSoft, colors.successSoft, colors.success, colors.success, R.drawable.ic_feedback_success)
    }
}

@Composable
fun InlineAlert(
    title: String,
    modifier: Modifier = Modifier,
    body: String? = null,
    tone: AlertTone = AlertTone.Warn,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val palette = alertColors(tone)

    Row(
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(palette.container)
            .border(1.dp, palette.border, ControlShape)
            .semantics { liveRegion = if (tone == AlertTone.Danger) LiveRegionMode.Assertive else LiveRegionMode.Polite }
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Icon(painter = painterResource(palette.iconRes), contentDescription = null, tint = palette.icon, modifier = Modifier.padding(top = 1.dp).size(20.dp))
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = palette.title)
            if (body != null) {
                Text(text = body, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textSecondary)
            }
            if (actionLabel != null && onAction != null) {
                Text(
                    text = actionLabel,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = palette.title,
                    modifier = Modifier
                        .padding(top = 6.dp)
                        .clip(ChipShape)
                        .clickable(role = Role.Button, onClick = onAction)
                        .padding(vertical = 4.dp),
                )
            }
        }
    }
}

@Composable
fun OfflineBar(
    text: String,
    modifier: Modifier = Modifier,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.toast)
            .semantics { liveRegion = LiveRegionMode.Polite }
            .padding(horizontal = 12.dp, vertical = 8.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_feedback_offline), contentDescription = null, tint = colors.onToast, modifier = Modifier.size(18.dp))
        Text(text = text, fontSize = 13.sp, color = colors.onToast, modifier = Modifier.weight(1f))
        if (actionLabel != null && onAction != null) {
            ToastActionText(text = actionLabel, onClick = onAction, fontSize = 13)
        }
    }
}

enum class ToastKind {
    Success,
    Error,
    Warning,
    Offline,
    Info,
    ;

    val staysUntilReplaced: Boolean get() = this == Error || this == Warning
}

@Immutable
data class ToastMessage(
    val text: String,
    val kind: ToastKind = ToastKind.Success,
    val actionLabel: String? = null,
    val onAction: (() -> Unit)? = null,
    val id: Long = System.nanoTime(),
)

@Stable
class ToastState {
    var current by mutableStateOf<ToastMessage?>(null)
        private set

    fun show(text: String, kind: ToastKind = ToastKind.Success, actionLabel: String? = null, onAction: (() -> Unit)? = null) {
        current = ToastMessage(text = text, kind = kind, actionLabel = actionLabel, onAction = onAction)
    }

    fun dismiss(message: ToastMessage? = current) {
        if (message != null && current?.id == message.id) {
            current = null
        }
    }
}

val LocalToasts = staticCompositionLocalOf { ToastState() }

@Composable
fun ToastHost(state: ToastState, modifier: Modifier = Modifier) {
    val message = state.current ?: return

    LaunchedEffect(message.id) {
        if (!message.kind.staysUntilReplaced) {
            delay(TOAST_DURATION_MILLIS)
            state.dismiss(message)
        }
    }
    AikuToast(
        message = message,
        onAction = {
            state.dismiss(message)
            message.onAction?.invoke()
        },
        modifier = modifier,
    )
}

@Composable
fun AikuToast(message: ToastMessage, onAction: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)
    val (badge, icon) = when (message.kind) {
        ToastKind.Success -> colors.success to R.drawable.ic_feedback_check
        ToastKind.Error -> colors.danger to R.drawable.ic_feedback_close
        ToastKind.Warning -> colors.warn to R.drawable.ic_feedback_warn
        ToastKind.Offline -> colors.textQuaternary to R.drawable.ic_feedback_cloud
        ToastKind.Info -> colors.link to R.drawable.ic_feedback_bell
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .shadow(elevation = 10.dp, shape = shape, ambientColor = colors.shadow, spotColor = colors.shadow)
            .clip(shape)
            .background(colors.toast)
            .semantics { liveRegion = if (message.kind.staysUntilReplaced) LiveRegionMode.Assertive else LiveRegionMode.Polite }
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(26.dp).clip(CircleShape).background(badge)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
        }
        Text(text = message.text, fontSize = 14.sp, lineHeight = 19.sp, color = colors.onToast, modifier = Modifier.weight(1f))
        if (message.actionLabel != null) {
            ToastActionText(text = message.actionLabel, onClick = onAction, fontSize = 14)
        }
    }
}

@Composable
private fun ToastActionText(text: String, onClick: () -> Unit, fontSize: Int) {
    val colors = LocalAikuColors.current

    Text(
        text = text,
        fontSize = fontSize.sp,
        fontWeight = FontWeight.Bold,
        color = colors.toastAction,
        modifier = Modifier
            .clip(ChipShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 4.dp, vertical = 6.dp),
    )
}

@Composable
fun ConfirmDialog(
    title: String,
    body: String,
    confirmLabel: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    dismissLabel: String = stringResource(R.string.dialog_cancel),
    isDestructive: Boolean = false,
) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(14.dp)

    Dialog(onDismissRequest = onDismiss) {
        Column(
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .shadow(elevation = 20.dp, shape = shape, ambientColor = colors.shadow, spotColor = colors.shadow)
                .clip(shape)
                .background(colors.surface)
                .border(1.dp, colors.border, shape)
                .padding(20.dp),
        ) {
            Text(text = title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = body, fontSize = 14.sp, lineHeight = 20.sp, color = colors.textTertiary)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                AikuButton(text = dismissLabel, onClick = onDismiss, style = ButtonStyle.Outline, height = 44.dp, modifier = Modifier.weight(1f))
                AikuButton(
                    text = confirmLabel,
                    onClick = onConfirm,
                    style = if (isDestructive) ButtonStyle.Danger else ButtonStyle.Primary,
                    height = 44.dp,
                    modifier = Modifier.weight(1f),
                )
            }
        }
    }
}

enum class EmptyTone { Neutral, Success, Info }

@Composable
fun EmptyState(
    @DrawableRes icon: Int,
    title: String,
    body: String,
    modifier: Modifier = Modifier,
    tone: EmptyTone = EmptyTone.Neutral,
    action: (@Composable () -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val (container, content) = when (tone) {
        EmptyTone.Neutral -> colors.surfaceMuted to colors.textQuaternary
        EmptyTone.Success -> colors.successSoft to colors.success
        EmptyTone.Info -> colors.soft to colors.link
    }

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier.fillMaxWidth().padding(horizontal = 4.dp, vertical = 12.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(56.dp).clip(CircleShape).background(container)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(26.dp))
        }
        Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center)
        Text(text = body, fontSize = 13.sp, lineHeight = 19.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
        action?.invoke()
    }
}

@Composable
fun shimmerBrush(): Brush {
    val colors = LocalAikuColors.current
    val travel = with(LocalDensity.current) { 240.dp.toPx() }
    val transition = rememberInfiniteTransition(label = "skeleton")
    val shift by transition.animateFloat(
        initialValue = -travel,
        targetValue = travel,
        animationSpec = infiniteRepeatable(tween(SHIMMER_DURATION_MILLIS, easing = LinearEasing), RepeatMode.Restart),
        label = "skeletonShift",
    )
    return Brush.linearGradient(
        colors = listOf(colors.divider, colors.border, colors.divider),
        start = Offset(shift, 0f),
        end = Offset(shift + travel * 2, 0f),
    )
}

@Composable
fun SkeletonBlock(modifier: Modifier = Modifier, shape: Shape = RoundedCornerShape(6.dp)) {
    Box(modifier = modifier.clip(shape).background(shimmerBrush()))
}

@Composable
fun SkeletonListRow(modifier: Modifier = Modifier) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = modifier.fillMaxWidth()) {
        SkeletonBlock(modifier = Modifier.size(40.dp), shape = ControlShape)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            SkeletonBlock(modifier = Modifier.fillMaxWidth(0.6f).height(12.dp))
            SkeletonBlock(modifier = Modifier.fillMaxWidth(0.4f).height(10.dp))
        }
    }
}

@Composable
fun SkeletonListCard(modifier: Modifier = Modifier, rows: Int = 3) {
    val description = stringResource(R.string.loading)

    AikuCard(modifier = modifier.semantics { contentDescription = description }) {
        repeat(rows) { SkeletonListRow() }
    }
}

@Composable
fun SkeletonStatCard(modifier: Modifier = Modifier) {
    val description = stringResource(R.string.loading)

    AikuCard(modifier = modifier.semantics { contentDescription = description }) {
        SkeletonBlock(modifier = Modifier.fillMaxWidth(0.5f).height(12.dp))
        SkeletonBlock(modifier = Modifier.fillMaxWidth(0.35f).height(26.dp))
        SkeletonBlock(modifier = Modifier.fillMaxWidth().height(8.dp), shape = CircleShape)
    }
}

