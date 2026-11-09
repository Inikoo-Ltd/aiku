package io.aiku.droid.ui.profile

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalAppearanceController
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.AikuTheme
import io.aiku.droid.ui.theme.LocalAikuColors
import io.aiku.droid.ui.theme.ThemeMode
import io.aiku.droid.ui.theme.aikuColors

private enum class AppearanceModeOption(val mode: ThemeMode, @StringRes val label: Int, @DrawableRes val icon: Int) {
    Light(ThemeMode.Light, R.string.profile_mode_light, R.drawable.ic_profile_mode_light),
    Dark(ThemeMode.Dark, R.string.profile_mode_dark, R.drawable.ic_profile_mode_dark),
    System(ThemeMode.System, R.string.profile_mode_system, R.drawable.ic_profile_mode_system),
}

private object AppearancePreviewSample {
    const val REFERENCE = "SD-1093"
    const val ITEM = "Soy wax candle – vanilla"
    const val PROGRESS = 0.51f
}

private const val THEME_COLUMNS = 3

@Composable
fun AppearanceScreen() {
    val colors = LocalAikuColors.current
    val controller = LocalAppearanceController.current
    val appearance = controller.appearance

    Column(modifier = Modifier.fillMaxSize().background(colors.background)) {
        AppearanceHeader()
        Column(
            verticalArrangement = Arrangement.spacedBy(16.dp),
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .navigationBarsPadding()
                .padding(16.dp),
        ) {
            ModeCard(selected = appearance.mode, onSelect = { controller.update(appearance.copy(mode = it)) })
            ThemeCard(selected = appearance.theme, onSelect = { controller.update(appearance.copy(theme = it)) })
            ThemePreview()
            OrganisationColourCard()
            Text(
                text = stringResource(R.string.profile_saved_note),
                fontSize = 12.sp,
                color = colors.textQuaternary,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .align(Alignment.CenterHorizontally)
                    .fakeData(8.dp)
                    .padding(horizontal = 12.dp, vertical = 8.dp),
            )
        }
    }
}

@Composable
private fun AppearanceHeader() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.nav)
            .statusBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 16.dp),
    ) {
        HeaderIconButton(icon = R.drawable.ic_chevron_left, description = stringResource(R.string.nav_back), onClick = navigator::back)
        Column(modifier = Modifier.weight(1f)) {
            Text(text = stringResource(R.string.profile_appearance), fontSize = 20.sp, fontWeight = FontWeight.Bold, color = colors.onNav)
            Text(text = stringResource(R.string.profile_appearance_kicker), fontSize = 12.sp, color = colors.onNavSecondary)
        }
    }
}

@Composable
private fun ModeCard(selected: ThemeMode, onSelect: (ThemeMode) -> Unit) {
    val colors = LocalAikuColors.current
    val segmentShape = RoundedCornerShape(6.dp)

    AikuCard {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = stringResource(R.string.profile_mode), fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = stringResource(R.string.profile_mode_caption), fontSize = 13.sp, color = colors.textTertiary)
        }
        Row(
            horizontalArrangement = Arrangement.spacedBy(4.dp),
            modifier = Modifier
                .fillMaxWidth()
                .clip(ControlShape)
                .background(colors.background)
                .border(1.dp, colors.border, ControlShape)
                .padding(4.dp)
                .selectableGroup(),
        ) {
            AppearanceModeOption.entries.forEach { option ->
                val isSelected = option.mode == selected
                val tint = if (isSelected) colors.text else colors.textTertiary
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally),
                    modifier = Modifier
                        .weight(1f)
                        .height(44.dp)
                        .clip(segmentShape)
                        .background(if (isSelected) colors.surface else Color.Transparent)
                        .then(if (isSelected) Modifier.border(1.dp, colors.borderStrong, segmentShape) else Modifier)
                        .selectable(selected = isSelected, role = Role.RadioButton, onClick = { onSelect(option.mode) }),
                ) {
                    Icon(painter = painterResource(option.icon), contentDescription = null, tint = tint, modifier = Modifier.size(18.dp))
                    Text(text = stringResource(option.label), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = tint, maxLines = 1)
                }
            }
        }
    }
}

@Composable
private fun ThemeCard(selected: AikuTheme, onSelect: (AikuTheme) -> Unit) {
    val colors = LocalAikuColors.current

    AikuCard {
        Row(verticalAlignment = Alignment.Bottom, modifier = Modifier.fillMaxWidth()) {
            Text(
                text = stringResource(R.string.profile_theme_colour),
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
                modifier = Modifier.weight(1f),
            )
            Text(text = stringResource(selected.label), fontSize = 13.sp, color = colors.textTertiary)
        }
        Column(verticalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.selectableGroup()) {
            AikuTheme.entries.chunked(THEME_COLUMNS).forEach { rowThemes ->
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                    rowThemes.forEach { theme ->
                        ThemeTile(
                            theme = theme,
                            isSelected = theme == selected,
                            onSelect = { onSelect(theme) },
                            modifier = Modifier.weight(1f),
                        )
                    }
                    repeat(THEME_COLUMNS - rowThemes.size) {
                        Spacer(modifier = Modifier.weight(1f))
                    }
                }
            }
        }
    }
}

@Composable
private fun ThemeTile(theme: AikuTheme, isSelected: Boolean, onSelect: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val swatch = remember(theme) { aikuColors(theme, isDark = false) }
    val borderWidth = if (isSelected) 2.dp else 1.dp
    val lineShape = RoundedCornerShape(2.dp)

    Box(modifier = modifier) {
        Column(
            verticalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .fillMaxWidth()
                .clip(ControlShape)
                .selectable(selected = isSelected, role = Role.RadioButton, onClick = onSelect),
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(62.dp)
                    .clip(ControlShape)
                    .background(swatch.surface)
                    .border(borderWidth, if (isSelected) swatch.primary else swatch.borderStrong, ControlShape)
                    .padding(borderWidth),
            ) {
                Row(modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(8.dp - borderWidth))) {
                    Column(
                        verticalArrangement = Arrangement.spacedBy(4.dp),
                        modifier = Modifier
                            .width(18.dp)
                            .fillMaxHeight()
                            .background(swatch.nav)
                            .padding(horizontal = 4.dp, vertical = 8.dp),
                    ) {
                        Box(modifier = Modifier.fillMaxWidth().height(2.dp).clip(lineShape).background(swatch.navAccent))
                        Box(modifier = Modifier.fillMaxWidth().height(2.dp).clip(lineShape).background(swatch.onNavSecondary.copy(alpha = 0.7f)))
                        Box(modifier = Modifier.fillMaxWidth().height(2.dp).clip(lineShape).background(swatch.onNavSecondary.copy(alpha = 0.7f)))
                    }
                    Column(
                        horizontalAlignment = Alignment.End,
                        verticalArrangement = Arrangement.spacedBy(6.dp),
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxHeight()
                            .padding(8.dp),
                    ) {
                        Box(modifier = Modifier.size(width = 22.dp, height = 4.dp).clip(lineShape).background(swatch.primary.copy(alpha = 0.55f)))
                        Box(modifier = Modifier.fillMaxWidth().height(3.dp).clip(lineShape).background(swatch.border))
                        Box(modifier = Modifier.fillMaxWidth(0.7f).height(3.dp).clip(lineShape).background(swatch.border))
                    }
                }
            }
            Text(text = stringResource(theme.label), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary)
        }
        if (isSelected) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .offset(x = 8.dp, y = (-8).dp)
                    .size(26.dp)
                    .clip(CircleShape)
                    .background(colors.surface)
                    .padding(2.dp)
                    .clip(CircleShape)
                    .background(colors.primary),
            ) {
                Icon(painter = painterResource(R.drawable.ic_profile_check), contentDescription = null, tint = colors.onPrimary, modifier = Modifier.size(14.dp))
            }
        }
    }
}

@Composable
private fun ThemePreview() {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        GroupLabel(text = stringResource(R.string.profile_preview))
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(CardShape)
                .background(colors.surface)
                .border(1.dp, colors.border, CardShape),
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .background(colors.nav)
                    .padding(horizontal = 16.dp, vertical = 12.dp),
            ) {
                Text(
                    text = stringResource(R.string.profile_preview_goods_in),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.onNav,
                    modifier = Modifier.weight(1f),
                )
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier
                        .height(30.dp)
                        .clip(ChipShape)
                        .background(colors.navActive)
                        .border(1.dp, colors.navRing, ChipShape)
                        .padding(horizontal = 10.dp),
                ) {
                    Text(text = stringResource(R.string.profile_preview_receiving), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = colors.navAccent)
                }
            }
            Column(
                verticalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 14.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                        Text(text = AppearancePreviewSample.REFERENCE, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                        Text(text = AppearancePreviewSample.ITEM, fontSize = 13.sp, color = colors.textTertiary)
                    }
                    StatusBadge(text = stringResource(R.string.profile_preview_in_progress), tone = Tone.Warn)
                }
                ProgressBar(fraction = AppearancePreviewSample.PROGRESS)
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                    PreviewButton(
                        text = stringResource(R.string.profile_preview_enter_quantity),
                        container = colors.soft,
                        border = colors.softBorder,
                        content = colors.link,
                        modifier = Modifier.weight(1f),
                    )
                    PreviewButton(
                        text = stringResource(R.string.profile_preview_receive),
                        container = colors.primary,
                        border = colors.primaryBorder,
                        content = colors.onPrimary,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
        }
    }
}

@Composable
private fun PreviewButton(text: String, container: Color, border: Color, content: Color, modifier: Modifier = Modifier) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .heightIn(min = 44.dp)
            .clip(ChipShape)
            .background(container)
            .border(1.dp, border, ChipShape),
    ) {
        Text(text = text, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = content, maxLines = 1)
    }
}

@Composable
private fun OrganisationColourCard() {
    val colors = LocalAikuColors.current
    var isColourPerOrganisation by rememberSaveable { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fakeData(12.dp)
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(horizontal = 16.dp, vertical = 4.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 64.dp)
                .toggleable(value = isColourPerOrganisation, role = Role.Checkbox, onValueChange = { isColourPerOrganisation = it }),
        ) {
            Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = stringResource(R.string.profile_org_colour), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
                Text(text = stringResource(R.string.profile_org_colour_caption), fontSize = 13.sp, color = colors.textTertiary)
            }
            Checkbox(
                checked = isColourPerOrganisation,
                onCheckedChange = null,
                colors = CheckboxDefaults.colors(
                    checkedColor = colors.primary,
                    uncheckedColor = colors.borderStrong,
                    checkmarkColor = colors.onPrimary,
                ),
            )
        }
    }
}

@Preview
@Composable
private fun AppearanceScreenPreview() {
    AikuDroidTheme {
        AppearanceScreen()
    }
}
