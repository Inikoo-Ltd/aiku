package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.GroupLabel
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.theme.LocalAikuColors

private const val MAX_COPIES = 20

@Composable
fun PrintDeliveryNoteSheet(reference: String, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val toasts = LocalToasts.current
    var selectedPrinter by remember { mutableIntStateOf(0) }
    var copies by remember { mutableIntStateOf(1) }
    val printers = GoodsOutSamples.printers
    val sentText = pluralStringResource(R.plurals.goodsout_print_sent, copies, copies, reference, printers[selectedPrinter].title)
    val openedText = stringResource(R.string.goodsout_print_pdf_opened, reference)

    AikuBottomSheet(
        title = stringResource(R.string.goodsout_print_title, reference),
        subtitle = pluralStringResource(R.plurals.goodsout_print_subtitle, GoodsOutSamples.PRINT_PAGES, GoodsOutSamples.PRINT_PAGES),
        icon = R.drawable.ic_ui_clipboard,
        onDismiss = onDismiss,
    ) {
        GroupLabel(text = stringResource(R.string.goodsout_printer))
        Column(verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fakeData(cornerRadius = 10.dp)) {
            printers.forEachIndexed { index, printer ->
                RadioCardRow(
                    title = printer.title,
                    subtitle = printer.subtitle,
                    selected = index == selectedPrinter,
                    onClick = { selectedPrinter = index },
                )
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp)) {
            Text(text = stringResource(R.string.goodsout_copies), fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text, modifier = Modifier.weight(1f))
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                CopiesButton(
                    symbol = "−",
                    description = stringResource(R.string.stepper_decrease),
                    isPrimary = false,
                    enabled = copies > 1,
                    onClick = { copies -= 1 },
                )
                Text(
                    text = copies.toString(),
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.widthIn(min = 24.dp),
                )
                CopiesButton(
                    symbol = "+",
                    description = stringResource(R.string.stepper_increase),
                    isPrimary = true,
                    enabled = copies < MAX_COPIES,
                    onClick = { copies += 1 },
                )
            }
        }
        AikuButton(
            text = stringResource(R.string.goodsout_print),
            onClick = {
                toasts.show(sentText, ToastKind.Success)
                onDismiss()
            },
            icon = R.drawable.ic_ui_clipboard,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
        AikuButton(
            text = stringResource(R.string.goodsout_open_pdf),
            onClick = {
                toasts.show(openedText, ToastKind.Info)
                onDismiss()
            },
            style = ButtonStyle.Outline,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
private fun CopiesButton(symbol: String, description: String, isPrimary: Boolean, enabled: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(ControlShape)
            .then(if (isPrimary) Modifier.background(colors.primary) else Modifier.border(1.dp, colors.borderStrong, ControlShape))
            .clickable(enabled = enabled, role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Text(text = symbol, fontSize = 20.sp, color = if (isPrimary) colors.onPrimary else colors.text)
    }
}
