package io.aiku.droid.ui.sync

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.selection.selectable
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.theme.LocalAikuColors

private enum class Side { Mine, Aiku, Both }

private data class ConflictLine(
    val code: String,
    val subtitle: String,
    val yours: String,
    val aiku: String,
    val aikuBy: String,
    val mineValue: String,
    val aikuValue: String,
    val bothValue: String?,
    val initial: Side,
)

private val sampleLines = listOf(
    ConflictLine("SKO-00733", "A-03-02 · Bath bomb – rose 180g · 6 to pick", "Picked 6 · 10:12", "Picked 4", "Sam P. · 10:15", "6", "4", "6 (max)", Side.Both),
    ConflictLine("SKO-00953", "B-01-04 · Incense sticks · 2 to pick", "Not picked 2 · out of stock", "Picked 2 from C-04-02", "Sam P. · 10:15", "Not picked", "Picked 2", null, Side.Aiku),
)

@Composable
fun ConflictScreen(reference: String) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val choices = remember { mutableStateListOf(*sampleLines.map { it.initial }.toTypedArray()) }
    val bothCount = choices.count { it == Side.Both }
    val aikuCount = choices.count { it == Side.Aiku }
    val mineCount = choices.count { it == Side.Mine }

    AppScreen(
        header = { AppHeader(title = reference, kicker = SyncSamples.CONFLICT_KICKER, isSample = true) },
        activeTab = MainTab.Menu,
        footer = {
            AikuButton(
                text = stringResource(
                    R.string.sync_apply,
                    listOfNotNull(
                        pluralStringResource(R.plurals.sync_apply_mine, mineCount, mineCount).takeIf { mineCount > 0 },
                        pluralStringResource(R.plurals.sync_apply_both, bothCount, bothCount).takeIf { bothCount > 0 },
                        pluralStringResource(R.plurals.sync_apply_aiku, aikuCount, aikuCount).takeIf { aikuCount > 0 },
                    ).joinToString(", "),
                ),
                onClick = { navigator.open(ResolveConflictsRoute) },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            AikuButton(
                text = stringResource(R.string.sync_use_mine_for_all),
                onClick = { choices.indices.forEach { choices[it] = Side.Mine } },
                style = ButtonStyle.Outline,
                modifier = Modifier.weight(1f),
            )
            AikuButton(
                text = stringResource(R.string.sync_use_aiku_for_all),
                onClick = { choices.indices.forEach { choices[it] = Side.Aiku } },
                style = ButtonStyle.Outline,
                modifier = Modifier.weight(1f),
            )
        }
        Row(verticalAlignment = Alignment.Bottom, modifier = Modifier.fillMaxWidth()) {
            GroupLabel(text = stringResource(R.string.sync_lines_differ), modifier = Modifier.weight(1f))
            Text(text = SyncSamples.LINES_DIFFER, fontSize = 12.sp, color = colors.textTertiary, modifier = Modifier.padding(end = 4.dp))
        }
        sampleLines.forEachIndexed { index, line ->
            ConflictCard(line = line, choice = choices[index], onChoose = { choices[index] = it })
        }
        GroupLabel(text = stringResource(R.string.sync_same_on_both))
        ListGroup(modifier = Modifier.fakeData()) {
            ItemRow(code = "SKO-00412", subtitle = "Picked 4", chip = stringResource(R.string.sync_no_conflict), chipTone = Tone.Success)
            RowDivider()
            ItemRow(code = "SKO-01187", subtitle = "Waiting", chip = stringResource(R.string.sync_no_conflict), chipTone = Tone.Success)
        }
        Text(
            text = stringResource(R.string.sync_conflict_explanation),
            fontSize = 12.sp,
            lineHeight = 18.sp,
            color = colors.textQuaternary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
    }
}

@Composable
private fun ConflictCard(line: ConflictLine, choice: Side, onChoose: (Side) -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .fakeData()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.warnBorder, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            ProductPhoto(code = line.code, size = 44.dp)
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = line.code, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                Text(text = line.subtitle, fontSize = 12.sp, color = colors.textTertiary)
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.weight(1f).clip(ControlShape).background(colors.soft).padding(horizontal = 10.dp, vertical = 8.dp)) {
                Text(text = stringResource(R.string.sync_yours), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.link)
                Text(text = line.yours, fontSize = 12.sp, lineHeight = 17.sp, color = colors.link)
            }
            Column(modifier = Modifier.weight(1f).clip(ControlShape).background(colors.background).padding(horizontal = 10.dp, vertical = 8.dp)) {
                Text(text = stringResource(R.string.sync_now_in_aiku), fontSize = 12.sp, fontWeight = FontWeight.Bold, color = colors.textSecondary)
                Text(text = line.aiku, fontSize = 12.sp, lineHeight = 17.sp, color = colors.textSecondary)
                Text(text = line.aikuBy, fontSize = 12.sp, color = colors.textTertiary)
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
            SideOption(label = stringResource(R.string.sync_mine), value = line.mineValue, isSelected = choice == Side.Mine, onClick = { onChoose(Side.Mine) }, modifier = Modifier.weight(1f))
            SideOption(label = stringResource(R.string.sync_aiku), value = line.aikuValue, isSelected = choice == Side.Aiku, onClick = { onChoose(Side.Aiku) }, modifier = Modifier.weight(1f))
            if (line.bothValue != null) {
                SideOption(label = stringResource(R.string.sync_both), value = line.bothValue, isSelected = choice == Side.Both, onClick = { onChoose(Side.Both) }, modifier = Modifier.weight(1f))
            }
        }
    }
}

@Composable
private fun SideOption(label: String, value: String, isSelected: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val content = if (isSelected) colors.onPrimary else colors.textSecondary

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(1.dp, Alignment.CenterVertically),
        modifier = modifier
            .heightIn(min = 48.dp)
            .clip(ControlShape)
            .background(if (isSelected) colors.primary else colors.surface)
            .then(if (isSelected) Modifier else Modifier.border(1.dp, colors.borderStrong, ControlShape))
            .selectable(selected = isSelected, role = Role.RadioButton, onClick = onClick)
            .padding(vertical = 4.dp),
    ) {
        Text(text = label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = content)
        Text(text = value, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = content)
    }
}
