package io.aiku.droid.ui.components

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.ContentTransform
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.Appearance
import io.aiku.droid.ui.theme.LocalAikuColors
import io.aiku.droid.ui.theme.ThemeMode

private const val DARK_PHOTO_ALPHA = 0.85f
private const val SCAN_PANEL_CHANGE_MILLIS = 180

@Composable
fun ProductPhoto(code: String, modifier: Modifier = Modifier, size: Dp = 48.dp, imageUrl: String? = null) {
    val colors = LocalAikuColors.current
    val description = stringResource(R.string.product_photo_of, code)
    val image = rememberRemoteImage(imageUrl)

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .size(size)
            .clip(ControlShape)
            .background(colors.surfaceMuted)
            .border(1.dp, colors.border, ControlShape)
            .semantics { contentDescription = description },
    ) {
        if (image != null) {
            Image(
                bitmap = image,
                contentDescription = null,
                contentScale = ContentScale.Crop,
                alpha = if (colors.isDark) DARK_PHOTO_ALPHA else 1f,
                modifier = Modifier.fillMaxSize(),
            )
        } else {
            Icon(painter = painterResource(R.drawable.ic_ui_photo), contentDescription = null, tint = colors.faint, modifier = Modifier.size(size / 2))
        }
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
    imageUrl: String? = null,
    extra: (@Composable ColumnScope.() -> Unit)? = null,
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
            ProductPhoto(code = code, imageUrl = imageUrl)
        }
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                if (chip != null) {
                    StatusBadge(text = chip, tone = chipTone)
                }
            }
            Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
            extra?.invoke(this)
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

data class ScanPanelItem(val code: String, val name: String, val location: String? = null, val imageUrl: String? = null)

@Composable
fun ScanPanel(
    item: ScanPanelItem,
    countLabel: String,
    remainingLabel: String,
    allLabel: String,
    onAll: () -> Unit,
    amount: Int,
    onAmountChange: (Int) -> Unit,
    amountLabel: String,
    onAmount: () -> Unit,
    hint: String,
    modifier: Modifier = Modifier,
    countTone: Tone = Tone.Success,
    maxAmount: Int = Int.MAX_VALUE,
    isWorking: Boolean = false,
    onClose: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val panelShape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp)
    val stepShape = RoundedCornerShape(10.dp)
    val subtitle = listOfNotNull(item.location?.takeIf { it.isNotBlank() }, item.name.takeIf { it.isNotBlank() }).joinToString(" · ")

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
            ProductPhoto(code = item.code, size = 56.dp, imageUrl = item.imageUrl)
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(text = item.code, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f, fill = false))
                    AnimatedContent(targetState = countLabel, transitionSpec = { scanPanelChange() }, label = "scanPanelCount") { label ->
                        StatusBadge(text = label, tone = countTone)
                    }
                }
                if (subtitle.isNotEmpty()) {
                    Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
                AnimatedContent(targetState = remainingLabel, transitionSpec = { scanPanelChange() }, label = "scanPanelRemaining") { label ->
                    Text(text = label, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.warn, modifier = Modifier.semantics { liveRegion = LiveRegionMode.Polite })
                }
            }
            if (onClose != null) {
                ScanPanelCloseButton(onClick = onClose)
            }
        }
        AikuButton(text = allLabel, onClick = onAll, icon = R.drawable.ic_ui_check, height = 52.dp, isLoading = isWorking, modifier = Modifier.fillMaxWidth())
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            StepButton(symbol = "−", description = stringResource(R.string.stepper_decrease), enabled = amount > 1, shape = stepShape) { onAmountChange(amount - 1) }
            Text(
                text = amount.toString(),
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                textAlign = TextAlign.Center,
                modifier = Modifier.weight(1f).semantics { liveRegion = LiveRegionMode.Polite },
            )
            StepButton(symbol = "+", description = stringResource(R.string.stepper_increase), enabled = amount < maxAmount, shape = stepShape) { onAmountChange(amount + 1) }
            AikuButton(text = amountLabel, onClick = onAmount, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.weight(1.4f))
        }
        Text(text = hint, fontSize = 11.sp, color = colors.textQuaternary, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun ScanPanelCloseButton(onClick: () -> Unit) {
    val description = stringResource(R.string.scan_panel_close)

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_close), contentDescription = null, tint = LocalAikuColors.current.textTertiary, modifier = Modifier.size(20.dp))
    }
}

private fun scanPanelChange(): ContentTransform =
    (slideInVertically(tween(SCAN_PANEL_CHANGE_MILLIS)) { height -> height / 2 } + fadeIn(tween(SCAN_PANEL_CHANGE_MILLIS)))
        .togetherWith(slideOutVertically(tween(SCAN_PANEL_CHANGE_MILLIS)) { height -> -height / 2 } + fadeOut(tween(SCAN_PANEL_CHANGE_MILLIS)))

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

private object ScanPanelPreviewSample {
    const val CODE = "SKO-00733"
    const val NAME = "Bath bomb – rose 180g"
    const val LOCATION = "A-03-02"
    const val PICKED = 1
    const val REMAINING = 23
    const val AMOUNT = 6
}

@Composable
private fun ScanPanelSample() {
    var picked by remember { mutableIntStateOf(ScanPanelPreviewSample.PICKED) }
    var remaining by remember { mutableIntStateOf(ScanPanelPreviewSample.REMAINING) }
    var amount by remember { mutableIntStateOf(ScanPanelPreviewSample.AMOUNT) }

    fun pick(count: Int) {
        val taken = count.coerceAtMost(remaining)
        picked += taken
        remaining -= taken
        amount = amount.coerceIn(1, maxOf(1, remaining))
    }

    Box(contentAlignment = Alignment.BottomCenter, modifier = Modifier.background(LocalAikuColors.current.background).padding(top = 24.dp)) {
        ScanPanel(
            item = ScanPanelItem(code = ScanPanelPreviewSample.CODE, name = ScanPanelPreviewSample.NAME, location = ScanPanelPreviewSample.LOCATION),
            countLabel = stringResource(R.string.goodsout_scan_picked_chip, picked),
            remainingLabel = pluralStringResource(R.plurals.goodsout_left_on_item, remaining, remaining),
            allLabel = pluralStringResource(R.plurals.goodsout_pick_all, remaining, remaining),
            onAll = { pick(remaining) },
            amount = amount,
            onAmountChange = { amount = it.coerceIn(1, maxOf(1, remaining)) },
            amountLabel = stringResource(R.string.goodsout_pick_amount, amount),
            onAmount = { pick(amount) },
            hint = stringResource(R.string.goodsout_scan_panel_hint),
            maxAmount = maxOf(1, remaining),
            modifier = Modifier.fakeData(cornerRadius = 16.dp),
        )
    }
}

@Preview
@Composable
private fun ScanPanelPreview() {
    AikuDroidTheme(appearance = Appearance(mode = ThemeMode.Light)) {
        ScanPanelSample()
    }
}

@Preview
@Composable
private fun ScanPanelDarkPreview() {
    AikuDroidTheme(appearance = Appearance(mode = ThemeMode.Dark)) {
        ScanPanelSample()
    }
}
