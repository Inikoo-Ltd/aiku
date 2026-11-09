package io.aiku.droid.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.interaction.MutableInteractionSource
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

enum class ButtonStyle { Primary, Soft, Outline, Danger }

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
        else -> Triple(colors.surface, colors.danger, colors.dangerBorder)
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
                indication = ripple(color = content),
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

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = modifier
            .height(48.dp)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, colors.border, ControlShape)
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
                    .heightIn(min = 36.dp)
                    .clip(pill)
                    .background(if (isSelected) colors.primary else colors.surface)
                    .then(if (isSelected) Modifier else Modifier.border(1.dp, colors.borderStrong, pill))
                    .clickable(role = Role.Tab) { onSelect(index) }
                    .padding(horizontal = 14.dp),
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
    val inner = RoundedCornerShape(6.dp)

    Row(
        modifier = modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.background)
            .border(1.dp, colors.border, ControlShape)
            .padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        options.forEachIndexed { index, option ->
            val isSelected = index == selectedIndex
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .weight(1f)
                    .height(44.dp)
                    .clip(inner)
                    .background(if (isSelected) colors.surface else Color.Transparent)
                    .then(if (isSelected) Modifier.border(1.dp, colors.borderStrong, inner) else Modifier)
                    .clickable(role = Role.RadioButton) { onSelect(index) },
            ) {
                Text(
                    text = option,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isSelected) colors.text else colors.textTertiary,
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
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = colors.surface,
        contentColor = colors.text,
    ) {
        Column(
            modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, bottom = 24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = title, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = colors.text)
                if (subtitle != null) {
                    Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary)
                }
            }
            content()
        }
    }
}
