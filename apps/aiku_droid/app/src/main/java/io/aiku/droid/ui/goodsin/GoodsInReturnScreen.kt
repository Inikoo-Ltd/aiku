package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ItemRow
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

private const val RETURNING_STEP = 1
private const val RETURNED_STEP = 2

private val returnSteps = listOf(
    R.string.goodsin_return_step_received,
    R.string.goodsin_return_step_returning,
    R.string.goodsin_return_step_returned,
    R.string.goodsin_return_step_done,
)

@Composable
fun GoodsInReturnScreen(reference: String) {
    val toasts = LocalToasts.current
    val can = LocalWarehouse.current.can
    val items = remember { mutableStateListOf(*GoodsInSamples.returnItems.toTypedArray()) }
    var isReturned by remember { mutableStateOf(false) }
    val pendingIndex = items.indexOfFirst { it.outcome == null }
    val returnedToast = stringResource(R.string.goodsin_toast_returned, reference)

    fun settlePending(outcome: ReturnOutcome) {
        if (pendingIndex >= 0) {
            val location = if (outcome == ReturnOutcome.ToLocation) GoodsInSamples.RETURN_LOCATION else null
            items[pendingIndex] = items[pendingIndex].copy(outcome = outcome, location = location)
        }
    }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        AppScreen(
            header = { AppHeader(isSample = true, title = reference, kicker = stringResource(R.string.goodsin_kicker_return, GoodsInSamples.RETURN_CUSTOMER)) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            ReturnStepBar(currentStep = if (isReturned) RETURNED_STEP else RETURNING_STEP)
            ListGroup(modifier = Modifier.fakeData()) {
                items.forEachIndexed { index, item ->
                    if (index > 0) {
                        RowDivider()
                    }
                    ReturnItemRow(item = item)
                }
            }
            if (can.bookIn) {
                val pending = items.getOrNull(pendingIndex)
                if (pending != null) {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        ReturnActionButton(
                            text = stringResource(R.string.goodsin_to_location),
                            isPrimary = true,
                            onClick = { settlePending(ReturnOutcome.ToLocation) },
                            modifier = Modifier.weight(1f),
                        )
                        ReturnActionButton(
                            text = stringResource(R.string.goodsin_damaged),
                            isPrimary = false,
                            onClick = { settlePending(ReturnOutcome.Damaged) },
                            modifier = Modifier.weight(1f),
                        )
                        ReturnActionButton(
                            text = stringResource(R.string.goodsin_not_returned),
                            isPrimary = false,
                            onClick = { settlePending(ReturnOutcome.NotReturned) },
                            modifier = Modifier.weight(1f),
                        )
                    }
                    HelperNote(text = stringResource(R.string.goodsin_return_actions_note, pending.code))
                } else if (!isReturned) {
                    AikuButton(
                        text = stringResource(R.string.goodsin_set_returned),
                        onClick = {
                            isReturned = true
                            toasts.show(returnedToast, kind = ToastKind.Success)
                        },
                        height = 52.dp,
                        modifier = Modifier.fillMaxWidth(),
                    )
                    HelperNote(text = stringResource(R.string.goodsin_return_processed_note))
                }
            }
        }
    }
}

@Composable
private fun ReturnStepBar(currentStep: Int) {
    val colors = LocalAikuColors.current

    Row(horizontalArrangement = Arrangement.spacedBy(3.dp), modifier = Modifier.fillMaxWidth()) {
        returnSteps.forEachIndexed { index, label ->
            val (container, content) = when {
                index < currentStep -> colors.success to Color.White
                index == currentStep -> colors.primary to colors.onPrimary
                else -> colors.background to colors.textTertiary
            }
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .weight(1f)
                    .height(26.dp)
                    .clip(RoundedCornerShape(6.dp))
                    .background(container),
            ) {
                Text(text = stringResource(label), fontSize = 10.sp, fontWeight = FontWeight.Bold, color = content, maxLines = 1)
            }
        }
    }
}

@Composable
private fun ReturnItemRow(item: ReturnItem) {
    val quantity = item.quantity.toString()
    when (item.outcome) {
        ReturnOutcome.ToLocation -> ItemRow(
            code = item.code,
            subtitle = pluralStringResource(R.plurals.goodsin_returned_to, item.quantity, item.quantity, item.location.orEmpty()),
            chip = stringResource(R.string.goodsin_return_returned),
            chipTone = Tone.Success,
            done = quantity,
            required = quantity,
            quantityTone = Tone.Success,
        )
        ReturnOutcome.Damaged -> ItemRow(
            code = item.code,
            subtitle = item.name,
            chip = stringResource(R.string.goodsin_damaged),
            chipTone = Tone.Warn,
            done = quantity,
            required = quantity,
            quantityTone = Tone.Warn,
        )
        ReturnOutcome.NotReturned -> ItemRow(
            code = item.code,
            subtitle = item.name,
            chip = stringResource(R.string.goodsin_not_returned),
            chipTone = Tone.Danger,
            done = "0",
            required = quantity,
            quantityTone = Tone.Danger,
        )
        null -> ItemRow(
            code = item.code,
            subtitle = stringResource(R.string.goodsin_to_process, item.name, item.quantity),
            done = "0",
            required = quantity,
        )
    }
}

@Composable
private fun ReturnActionButton(text: String, isPrimary: Boolean, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier
            .heightIn(min = 52.dp)
            .clip(ControlShape)
            .background(if (isPrimary) colors.primary else colors.surface)
            .border(width = 1.dp, color = if (isPrimary) colors.primaryBorder else colors.borderStrong, shape = ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 6.dp, vertical = 4.dp),
    ) {
        Text(
            text = text,
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold,
            color = if (isPrimary) colors.onPrimary else colors.textSecondary,
            textAlign = TextAlign.Center,
            maxLines = 2,
        )
    }
}
