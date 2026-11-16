package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.CheckResult
import io.aiku.droid.data.api.ChecklistCheck
import io.aiku.droid.data.api.GoodsInChecklist
import io.aiku.droid.data.api.GoodsInErrorKey
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ChoiceRow
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.ActionProblem
import io.aiku.droid.ui.goodsout.organisationZone
import io.aiku.droid.ui.inventory.SheetGroupLabel
import io.aiku.droid.ui.inventory.SheetTextField
import io.aiku.droid.ui.inventory.momentText
import io.aiku.droid.ui.locations.LIST_SEPARATOR
import io.aiku.droid.ui.theme.LocalAikuColors

private val resultChoices = listOf(CheckResult.Passed, CheckResult.Failed)

@Composable
internal fun ChecklistView(delivery: StockDelivery, checklist: GoodsInChecklist, onOpenCheck: ((String) -> Unit)?) {
    val zone = organisationZone()
    val differences = delivery.items.filter { item -> item.skoChecked.let { it != null && it != item.skoExpected } }

    SectionHeading(
        title = stringResource(R.string.goodsin_checks),
        caption = pluralStringResource(R.plurals.goodsin_checks_done_count, checklist.total, checklist.checked, checklist.total),
    )
    if (checklist.sections.all { it.checks.isEmpty() }) {
        HelperNote(text = stringResource(R.string.goodsin_checks_none))
    }
    checklist.sections.filter { it.checks.isNotEmpty() }.forEach { section ->
        if (section.title.isNotBlank()) {
            SectionHeading(title = section.title, caption = section.subtitle?.takeIf { it.isNotBlank() })
        }
        ListGroup {
            section.checks.forEachIndexed { index, check ->
                if (index > 0) {
                    RowDivider()
                }
                val (chip, tone) = when (CheckResult.ofKey(check.result)) {
                    CheckResult.Passed -> stringResource(R.string.goodsin_check_passed) to Tone.Success
                    CheckResult.Failed -> stringResource(R.string.goodsin_check_failed) to Tone.Danger
                    null -> stringResource(R.string.goodsin_check_to_do) to Tone.Neutral
                }
                val done = listOfNotNull(check.checkedBy?.name?.takeIf { it.isNotBlank() }, momentText(check.checkedAt, zone)).joinToString(LIST_SEPARATOR)
                ItemRow(
                    code = check.label,
                    subtitle = listOfNotNull(
                        check.detail?.takeIf { it.isNotBlank() },
                        done.takeIf { it.isNotBlank() } ?: stringResource(R.string.goodsin_check_pass_or_fail).takeIf { check.result == null },
                    ).joinToString(LIST_SEPARATOR),
                    chip = chip,
                    chipTone = tone,
                    showPhoto = false,
                    onClick = onOpenCheck?.let { open -> { open(check.key) } },
                )
            }
        }
    }
    if (differences.isNotEmpty()) {
        SectionHeading(title = stringResource(R.string.goodsin_under_over), caption = stringResource(R.string.goodsin_read_only))
        ListGroup {
            differences.forEachIndexed { index, item ->
                if (index > 0) {
                    RowDivider()
                }
                val difference = differenceOf(checked = item.skoChecked ?: 0.0, expected = item.skoExpected)
                ItemRow(code = item.code, subtitle = item.name, chip = difference.chip, chipTone = difference.tone, imageUrl = item.sko?.imageUrl)
            }
        }
    }
    HelperNote(text = stringResource(R.string.goodsin_checklist_note))
}

@Composable
internal fun CheckResultSheet(
    check: ChecklistCheck,
    checklist: GoodsInChecklist,
    isWorking: Boolean,
    problem: String?,
    errors: Map<String, List<String>>,
    onSave: (CheckResult, String?, String?, Double?) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var result by remember(check.key) { mutableStateOf(CheckResult.ofKey(check.result) ?: CheckResult.Passed) }
    var note by remember(check.key) { mutableStateOf("") }
    var incidentType by remember(check.key) { mutableStateOf<String?>(null) }
    var quantity by remember(check.key) { mutableStateOf("") }
    var isNoteMissing by remember(check.key) { mutableStateOf(false) }
    val isFailing = result == CheckResult.Failed
    val labels = mapOf(
        CheckResult.Passed to stringResource(R.string.goodsin_pass),
        CheckResult.Failed to stringResource(R.string.goodsin_fail),
    )
    val noteError = errors[GoodsInErrorKey.BODY]?.firstOrNull() ?: stringResource(R.string.goodsin_check_note_required).takeIf { isNoteMissing }

    AikuBottomSheet(
        title = check.label,
        subtitle = check.detail?.takeIf { it.isNotBlank() },
        onDismiss = onDismiss,
        footer = {
            ActionProblem(problem)
            AikuButton(
                text = labels.getValue(result),
                onClick = {
                    if (isFailing && note.isBlank()) {
                        isNoteMissing = true
                    } else {
                        onSave(result, note, incidentType, quantity.trim().replace(',', '.').toDoubleOrNull())
                    }
                },
                style = if (isFailing) ButtonStyle.Danger else ButtonStyle.Primary,
                isLoading = isWorking,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
            AikuButton(text = stringResource(R.string.dialog_cancel), onClick = onDismiss, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
        },
    ) {
        resultChoices.forEach { choice ->
            ChoiceRow(title = labels.getValue(choice), selected = result == choice, onClick = { result = choice })
        }
        if (isFailing) {
            SheetTextField(
                value = note,
                onValueChange = {
                    note = it
                    isNoteMissing = false
                },
                label = stringResource(R.string.goodsin_check_note),
                isError = noteError != null,
                helper = noteError,
            )
            if (checklist.incidentTypes.isNotEmpty()) {
                SheetGroupLabel(text = stringResource(R.string.goodsin_incident_type_optional))
                checklist.incidentTypes.forEach { type ->
                    ChoiceRow(
                        title = type.label.ifBlank { type.value },
                        selected = incidentType == type.value,
                        onClick = { incidentType = if (incidentType == type.value) null else type.value },
                    )
                }
            }
            SheetTextField(
                value = quantity,
                onValueChange = { quantity = it },
                label = stringResource(R.string.goodsin_incident_quantity_optional),
                isNumber = true,
            )
        }
        check.checkedBy?.name?.takeIf { it.isNotBlank() }?.let { name ->
            Text(text = stringResource(R.string.goodsin_check_last_by, name), fontSize = 13.sp, color = colors.textTertiary)
        }
        HelperNote(text = stringResource(R.string.goodsin_checklist_note))
    }
}
