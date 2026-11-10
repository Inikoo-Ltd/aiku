package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.FooterStatus
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.StateStepper
import io.aiku.droid.ui.components.StockDeliveryStage
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.components.stockDeliverySteps
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab

@Composable
fun StockDeliveryChecklistScreen(reference: String) {
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val checks = remember { mutableStateListOf(*GoodsInSamples.checks.toTypedArray()) }
    val nextCheckIndex = checks.indexOfFirst { it.status == CheckStatus.ToDo }
    val doneBy = stringResource(R.string.goodsin_check_done_by, LocalStaff.current.displayName)
    val passedToast = stringResource(R.string.goodsin_toast_check_passed)
    val failedToast = stringResource(R.string.goodsin_toast_check_failed)
    val differences = GoodsInSamples.checkItems.filter { item -> item.checked != null && item.checked != item.delivered }

    fun settleNextCheck(status: CheckStatus) {
        if (nextCheckIndex >= 0) {
            checks[nextCheckIndex] = checks[nextCheckIndex].copy(status = status, detail = doneBy)
            toasts.show(if (status == CheckStatus.Passed) passedToast else failedToast, kind = ToastKind.Success)
        }
    }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { StockDeliveryHeader(reference = reference) },
            activeTab = MainTab.Menu,
            showBar = true,
            footer = if (can.bookIn) {
                {
                    if (nextCheckIndex >= 0) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                            AikuButton(
                                text = stringResource(R.string.goodsin_fail),
                                onClick = { settleNextCheck(CheckStatus.Failed) },
                                style = ButtonStyle.Danger,
                                height = 52.dp,
                                modifier = Modifier.weight(1f),
                            )
                            AikuButton(
                                text = stringResource(R.string.goodsin_pass),
                                onClick = { settleNextCheck(CheckStatus.Passed) },
                                height = 52.dp,
                                modifier = Modifier.weight(1f),
                            )
                        }
                    } else {
                        FooterStatus(title = stringResource(R.string.goodsin_checks_done))
                    }
                }
            } else {
                null
            },
        ) {
            StateStepper(steps = stockDeliverySteps(current = StockDeliveryStage.Checked))
            DeliveryViewTabs(active = DeliveryView.Checklist, reference = reference)
            SectionHeading(title = stringResource(R.string.goodsin_checks))
            ListGroup(modifier = Modifier.fakeData()) {
                checks.forEachIndexed { index, check ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val (chip, tone) = when (check.status) {
                        CheckStatus.Passed -> stringResource(R.string.goodsin_check_passed) to Tone.Success
                        CheckStatus.Failed -> stringResource(R.string.goodsin_check_failed) to Tone.Danger
                        CheckStatus.ToDo -> stringResource(R.string.goodsin_check_to_do) to Tone.Neutral
                    }
                    ItemRow(
                        code = check.title,
                        subtitle = check.detail ?: stringResource(R.string.goodsin_check_pass_or_fail),
                        chip = chip,
                        chipTone = tone,
                        showPhoto = false,
                    )
                }
            }
            if (differences.isNotEmpty()) {
                SectionHeading(title = stringResource(R.string.goodsin_under_over), caption = stringResource(R.string.goodsin_read_only))
                ListGroup(modifier = Modifier.fakeData()) {
                    differences.forEachIndexed { index, item ->
                        if (index > 0) {
                            RowDivider()
                        }
                        val difference = differenceOf(checked = item.checked ?: 0, delivered = item.delivered)
                        ItemRow(code = item.code, subtitle = item.name, chip = difference.chip, chipTone = difference.tone)
                    }
                }
            }
            HelperNote(text = stringResource(R.string.goodsin_checklist_note))
        }
    }
}
