package io.aiku.droid.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun ProductPhoto(code: String, modifier: Modifier = Modifier, size: Dp = 48.dp) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.product_photo_of, code)

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(size)
            .clip(ControlShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, ControlShape)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_photo), contentDescription = null, tint = colors.faint, modifier = Modifier.size(size / 2))
    }
}

@Composable
fun ItemRow(
    code: String,
    subtitle: String,
    modifier: Modifier = Modifier,
    chip: String? = null,
    chipTone: Tone = Tone.Neutral,
    done: String? = null,
    required: String? = null,
    quantityTone: Tone? = null,
    showPhoto: Boolean = true,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 60.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        if (showPhoto) {
            ProductPhoto(code = code)
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                if (chip != null) {
                    StatusBadge(text = chip, tone = chipTone)
                }
            }
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
        if (done != null) {
            val doneColor = quantityTone?.let { colors.tone(it).content } ?: colors.text
            Text(
                text = buildAnnotatedString {
                    withStyle(SpanStyle(color = doneColor)) { append(done) }
                    if (required != null) {
                        withStyle(SpanStyle(fontSize = 13.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)) { append(" / $required") }
                    }
                },
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
            )
        }
    }
}

@Composable
fun ScanPanel(
    code: String,
    chip: String,
    subtitle: String,
    remaining: String,
    allLabel: String,
    onAll: () -> Unit,
    amount: Int,
    onAmountChange: (Int) -> Unit,
    amountLabel: String,
    onAmount: () -> Unit,
    hint: String,
    modifier: Modifier = Modifier,
    chipTone: Tone = Tone.Success,
    maxAmount: Int = Int.MAX_VALUE,
) {
    val colors = LocalAikuColors.current
    val panelShape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp)
    val stepShape = RoundedCornerShape(10.dp)

    Column(
        modifier = modifier
            .fillMaxWidth()
            .shadow(elevation = 12.dp, shape = panelShape, ambientColor = colors.shadow, spotColor = colors.shadow)
            .clip(panelShape)
            .background(colors.surface)
            .border(1.dp, colors.border, panelShape)
            .padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 14.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            ProductPhoto(code = code, size = 56.dp)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(text = code, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    StatusBadge(text = chip, tone = chipTone)
                }
                Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(text = remaining, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.warn)
            }
        }
        AikuButton(text = allLabel, onClick = onAll, icon = R.drawable.ic_ui_check, height = 52.dp, modifier = Modifier.fillMaxWidth())
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            StepButton(symbol = "−", description = stringResource(R.string.stepper_decrease), enabled = amount > 1, shape = stepShape) { onAmountChange(amount - 1) }
            Text(text = amount.toString(), fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
            StepButton(symbol = "+", description = stringResource(R.string.stepper_increase), enabled = amount < maxAmount, shape = stepShape) { onAmountChange(amount + 1) }
            AikuButton(text = amountLabel, onClick = onAmount, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.weight(1.4f))
        }
        Text(text = hint, fontSize = 11.sp, color = colors.textQuaternary, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun StepButton(symbol: String, description: String, enabled: Boolean, shape: RoundedCornerShape, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .width(52.dp)
            .height(48.dp)
            .clip(shape)
            .background(colors.surface)
            .border(1.dp, colors.borderStrong, shape)
            .clickable(enabled = enabled, role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Text(text = symbol, fontSize = 22.sp, color = if (enabled) colors.text else colors.faint)
    }
}

@Composable
fun ChoiceRow(
    title: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    enabled: Boolean = true,
) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .alpha(if (enabled) 1f else 0.5f)
            .heightIn(min = 56.dp)
            .clip(shape)
            .background(if (selected) colors.soft else colors.surface)
            .border(if (selected) 2.dp else 1.dp, if (selected) colors.primary else colors.border, shape)
            .selectable(selected = selected, enabled = enabled, role = Role.RadioButton, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            if (subtitle != null) {
                Text(text = subtitle, fontSize = 12.sp, color = colors.textTertiary)
            }
        }
        if (selected) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        }
    }
}
