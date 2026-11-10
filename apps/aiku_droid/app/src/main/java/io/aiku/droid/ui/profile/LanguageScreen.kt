package io.aiku.droid.ui.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private data class SampleLanguage(val code: String, val nativeName: String, val englishName: String)

private val SampleLanguages = listOf(
    SampleLanguage(code = "en", nativeName = "English", englishName = "English"),
    SampleLanguage(code = "es", nativeName = "Español", englishName = "Spanish"),
    SampleLanguage(code = "sk", nativeName = "Slovenčina", englishName = "Slovak"),
)

@Composable
fun LanguageScreen() {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    var selectedCode by rememberSaveable { mutableStateOf(SampleLanguages.first().code) }
    val selectedLanguage = SampleLanguages.firstOrNull { it.code == selectedCode } ?: SampleLanguages.first()

    Column(modifier = Modifier.fillMaxSize().background(colors.background)) {
        AppHeader(
            title = stringResource(R.string.profile_language),
            kicker = stringResource(R.string.profile_title),
        )
        Column(
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
        ) {
            Text(
                text = stringResource(R.string.profile_language_intro),
                fontSize = 13.sp,
                lineHeight = 19.sp,
                color = colors.textTertiary,
                modifier = Modifier.padding(horizontal = 4.dp),
            )
            ListGroup(modifier = Modifier.fakeData().selectableGroup()) {
                SampleLanguages.forEachIndexed { index, language ->
                    if (index > 0) {
                        RowDivider()
                    }
                    LanguageRow(
                        language = language,
                        isSelected = language.code == selectedLanguage.code,
                        onSelect = { selectedCode = language.code },
                    )
                }
            }
        }
        Column(modifier = Modifier.fillMaxWidth().background(colors.surface)) {
            HorizontalDivider(thickness = 1.dp, color = colors.border)
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .navigationBarsPadding()
                    .padding(horizontal = 16.dp, vertical = 12.dp),
            ) {
                AikuButton(
                    text = stringResource(R.string.profile_language_use, selectedLanguage.nativeName),
                    onClick = navigator::back,
                    modifier = Modifier
                        .fillMaxWidth()
                        .fakeData(8.dp),
                    height = 52.dp,
                )
            }
        }
    }
}

@Composable
private fun LanguageRow(language: SampleLanguage, isSelected: Boolean, onSelect: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .background(if (isSelected) colors.soft else colors.surface)
            .selectable(selected = isSelected, role = Role.RadioButton, onClick = onSelect)
            .padding(horizontal = 16.dp, vertical = 8.dp),
    ) {
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = language.nativeName, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            Text(text = language.englishName, fontSize = 12.sp, color = colors.textTertiary)
        }
        Box(
            modifier = Modifier
                .size(22.dp)
                .clip(CircleShape)
                .background(colors.surface)
                .border(
                    width = if (isSelected) 7.dp else 2.dp,
                    color = if (isSelected) colors.primary else colors.borderStrong,
                    shape = CircleShape,
                ),
        )
    }
}

@Preview
@Composable
private fun LanguageScreenPreview() {
    AikuDroidTheme {
        LanguageScreen()
    }
}
