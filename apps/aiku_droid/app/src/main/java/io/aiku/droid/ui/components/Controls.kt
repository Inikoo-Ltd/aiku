package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.material3.ripple
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

enum class ButtonStyle { Primary, Soft, Outline, Danger, Scan }

@Composable
fun AikuButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    style: ButtonStyle = ButtonStyle.Primary,
    @DrawableRes icon: Int? = null,
    enabled: Boolean = true,
    isLoading: Boolean = false,
    height: Dp = 48.dp,
) {
    val colors = LocalAikuColors.current
    val interactionSource = remember { MutableInteractionSource() }
    val isPressed by interactionSource.collectIsPressedAsState()
    val (container, content, border) = when {
        !enabled -> Triple(Color.Transparent, colors.textQuaternary, colors.borderStrong)
        style == ButtonStyle.Primary -> Triple(if (isPressed) colors.primaryPressed else colors.primary, colors.onPrimary, colors.primaryBorder)
        style == ButtonStyle.Soft -> Triple(colors.soft, colors.link, colors.softBorder)
        style == ButtonStyle.Outline -> Triple(Color.Transparent, colors.textSecondary, colors.borderStrong)
        style == ButtonStyle.Scan -> Triple(colors.scan, colors.onScan, Color.Transparent)
        else -> Triple(colors.surface, colors.danger, colors.dangerBorder)
    }
    val focusRing = when (style) {
        ButtonStyle.Primary -> colors.onPrimary
        ButtonStyle.Scan -> colors.onScan
        else -> colors.focus
    }

    Row(
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .heightIn(min = height)
            .alpha(if (!enabled) 0.8f else if (isLoading) 0.85f else 1f)
            .clip(ControlShape)
            .background(container)
            .border(1.dp, border, ControlShape)
            .clickable(
                interactionSource = interactionSource,
                indication = AikuFocusIndication(ringColor = focusRing, gapColor = colors.background, ripple = ripple(color = content)),
                enabled = enabled && !isLoading,
                role = Role.Button,
                onClick = onClick,
            )
            .padding(horizontal = 16.dp),
    ) {
        if (isLoading) {
            CircularProgressIndicator(color = content, trackColor = content.copy(alpha = 0.35f), strokeWidth = 2.5.dp, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(10.dp))
        } else if (icon != null) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(20.dp))
            Spacer(modifier = Modifier.width(8.dp))
        }
        Text(text = text, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = content, maxLines = 1)
    }
}

@Composable
fun SearchField(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current
    val interactionSource = remember { MutableInteractionSource() }
    val isFocused by interactionSource.collectIsFocusedAsState()
    val focusProgress = animateFocusProgress(isFocused)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .height(48.dp)
            .focusHalo(progress = focusProgress, color = colors.focus, cornerRadius = 8.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, lerp(colors.border, colors.focus, focusProgress), ControlShape)
            .padding(horizontal = 12.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_search), contentDescription = null, tint = colors.textQuaternary, modifier = Modifier.size(20.dp))
        Spacer(modifier = Modifier.width(8.dp))
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            singleLine = true,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 15.sp, color = colors.text),
            cursorBrush = SolidColor(colors.primary),
            interactionSource = interactionSource,
            modifier = Modifier.weight(1f),
            decorationBox = { inner ->
                Box(contentAlignment = Alignment.CenterStart) {
                    if (value.isEmpty()) {
                        Text(text = placeholder, fontSize = 15.sp, color = colors.textQuaternary, maxLines = 1)
                    }
                    inner()
                }
            },
        )
    }
}

data class FilterOption(val label: String, val dotColor: Color? = null)

@Composable
fun FilterChips(
    options: List<FilterOption>,
    selectedIndex: Int,
    onSelect: (Int) -> Unit,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current
    val pill = RoundedCornerShape(999.dp)

    Row(
        modifier = modifier.fillMaxWidth().horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        options.forEachIndexed { index, option ->
            val isSelected = index == selectedIndex
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier
                    .heightIn(min = 34.dp)
                    .clip(pill)
                    .background(if (isSelected) colors.primary else colors.surface)
                    .then(if (isSelected) Modifier else Modifier.border(1.dp, colors.borderStrong, pill))
                    .clickable(role = Role.Tab) { onSelect(index) }
                    .padding(horizontal = 12.dp),
            ) {
                option.dotColor?.let { Box(modifier = Modifier.size(8.dp).clip(pill).background(it)) }
                Text(
                    text = option.label,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isSelected) colors.onPrimary else colors.textSecondary,
                )
            }
        }
    }
}

@Composable
fun SegmentedControl(
    options: List<String>,
    selectedIndex: Int,
    onSelect: (Int) -> Unit,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current
    val track = RoundedCornerShape(10.dp)
    val inner = RoundedCornerShape(7.dp)

    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(track)
            .background(colors.border)
            .padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        options.forEachIndexed { index, option ->
            val isSelected = index == selectedIndex
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .weight(1f)
                    .height(36.dp)
                    .clip(inner)
                    .background(if (isSelected) colors.surface else Color.Transparent)
                    .clickable(role = Role.RadioButton) { onSelect(index) },
            ) {
                Text(
                    text = option,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isSelected) colors.link else colors.textTertiary,
                    maxLines = 1,
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AikuBottomSheet(
    title: String,
    onDismiss: () -> Unit,
    subtitle: String? = null,
    @DrawableRes icon: Int? = null,
    context: String? = null,
    headerAction: (@Composable () -> Unit)? = null,
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        shape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp),
        containerColor = colors.surface,
        contentColor = colors.text,
        scrimColor = colors.scrim,
        dragHandle = {
            Box(
                modifier = Modifier
                    .padding(top = 12.dp, bottom = 2.dp)
                    .size(width = 40.dp, height = 4.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(colors.borderStrong),
            )
        },
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(start = 20.dp, end = 20.dp, top = 12.dp, bottom = 20.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                if (icon != null) {
                    Icon(painter = painterResource(icon), contentDescription = null, tint = colors.link, modifier = Modifier.size(22.dp))
                }
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    if (subtitle != null) {
                        Text(text = subtitle, fontSize = 14.sp, color = colors.textTertiary)
                    }
                }
                if (context != null) {
                    Text(text = context, fontSize = 13.sp, color = colors.textTertiary, maxLines = 1)
                }
                headerAction?.invoke()
            }
            content()
        }
    }
}
