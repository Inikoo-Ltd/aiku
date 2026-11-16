package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Consumable
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.InsertLeaflet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.ScanPanel
import io.aiku.droid.ui.components.ScanPanelItem
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlin.math.floor

private val insertStates = setOf(DeliveryNoteBucket.Handling.key, DeliveryNoteBucket.Picked.key, DeliveryNoteBucket.Packing.key)

@Composable
fun DeliveryNotePackingScreen(note: DeliveryNoteViewModel, reference: String) {
    DeliveryNoteScreen(note = note, reference = reference)
}

internal val DeliveryNote.nextLineToPack get() = lines.firstOrNull { it.quantityToPack > 0.0 && it.orgStock != null }

internal val DeliveryNote.showsInserts: Boolean get() = inserts != null && state in insertStates

@Composable
internal fun PackingScanCard(note: DeliveryNote, queuedScans: Int) {
    val next = note.nextLineToPack
    val code = next?.orgStock?.code.orEmpty()

    ScanHintCard(
        title = stringResource(R.string.goodsout_scan_to_pack),
        caption = stringResource(R.string.goodsout_scan_listening),
        value = code.ifBlank { stringResource(R.string.goodsout_all_lines_packed) },
        detail = listOfNotNull(
            next?.let { line ->
                pluralStringResource(R.plurals.goodsout_left_to_pack_label, pluralQuantity(line.quantityToPack), quantityText(line.quantityToPack, line.quantityToPackLabel))
            },
            if (queuedScans > 0) pluralStringResource(R.plurals.goodsout_scan_queued, queuedScans, queuedScans) else null,
        ).joinToString(" · ").ifBlank { stringResource(R.string.goodsout_scan_nothing_left_to_pack) },
    )
}

@Composable
internal fun ScanToPackPanel(
    note: DeliveryNote,
    target: ScanTarget,
    isWorking: Boolean,
    onPackAll: () -> Unit,
    onPackAmount: (Int) -> Unit,
    onClose: () -> Unit,
) {
    val line = note.line(target.lineId) ?: return
    val remaining = line.quantityToPack
    val remainingText = quantityText(remaining, line.quantityToPackLabel)
    val maxAmount = maxOf(1, floor(remaining).toInt())
    var amount by remember(target.lineId) { mutableIntStateOf(1) }
    val shownAmount = amount.coerceIn(1, maxAmount)

    ScanPanel(
        item = ScanPanelItem(
            code = line.orgStock?.code?.takeIf { it.isNotBlank() } ?: target.barcode,
            name = line.orgStock?.name.orEmpty(),
            imageUrl = line.orgStock?.imageUrl,
        ),
        countLabel = stringResource(R.string.goodsout_scan_packed_chip, quantityText(line.quantityPacked, line.quantityPackedLabel)),
        remainingLabel = pluralStringResource(R.plurals.goodsout_left_to_pack_label, pluralQuantity(remaining), remainingText),
        allLabel = stringResource(R.string.goodsout_pack_all, remainingText),
        onAll = onPackAll,
        amount = shownAmount,
        onAmountChange = { amount = it.coerceIn(1, maxAmount) },
        amountLabel = stringResource(R.string.goodsout_pack_amount, shownAmount),
        onAmount = { onPackAmount(shownAmount) },
        hint = stringResource(R.string.goodsout_scan_panel_hint),
        maxAmount = maxAmount,
        isWorking = isWorking,
        onClose = onClose,
    )
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun PackerMustAdd(consumables: List<Consumable>) {
    if (consumables.isEmpty()) {
        return
    }
    val colors = LocalAikuColors.current

    FlowRow(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.warnSoft)
            .border(1.dp, colors.warnBorder, CardShape)
            .padding(12.dp),
    ) {
        Text(
            text = stringResource(R.string.goodsout_packer_must_add),
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            color = colors.warnInk,
            modifier = Modifier.align(Alignment.CenterVertically),
        )
        consumables.forEach { consumable ->
            Text(
                text = stringResource(R.string.goodsout_consumable, quantityText(consumable.quantity), consumable.code),
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.warnInk,
                modifier = Modifier.clip(ChipShape).background(colors.surface).padding(horizontal = 8.dp, vertical = 3.dp),
            )
        }
    }
}

@Composable
internal fun BoxPackingListBlock(note: DeliveryNote, isOpening: Boolean, onOpenPdf: (String) -> Unit) {
    val list = note.boxPackingList ?: return
    list.missingMessage?.takeIf { it.isNotBlank() && note.stage.isBeforeDispatch }?.let { InlineAlert(title = it, tone = AlertTone.Warn) }
    list.pdfUrl?.takeIf { it.isNotBlank() }?.let { url ->
        AikuButton(
            text = stringResource(R.string.goodsout_document_packing_list),
            onClick = { onOpenPdf(url) },
            style = ButtonStyle.Outline,
            icon = R.drawable.ic_ui_document,
            isLoading = isOpening,
            height = 48.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
internal fun InsertsBlock(
    note: DeliveryNote,
    isPrinting: Boolean,
    openingLeafletId: Long?,
    onOpenPdf: (InsertLeaflet) -> Unit,
    onPrintAll: () -> Unit,
) {
    val inserts = note.inserts?.takeIf { note.showsInserts } ?: return
    val packaging = note.packaging?.current
    if (inserts.leaflets.isEmpty() && packaging == null) {
        return
    }
    val openLabel = stringResource(R.string.goodsout_open_pdf)
    val openingLabel = stringResource(R.string.goodsout_opening)

    GoodsOutHeading(title = stringResource(R.string.goodsout_inserts), trailing = inserts.label?.takeIf { it.isNotBlank() })
    ListGroup {
        packaging?.let {
            IconInfoRow(icon = R.drawable.ic_ui_box, title = it.name, subtitle = stringResource(R.string.goodsout_packaging), tone = Tone.Neutral)
        }
        inserts.leaflets.forEachIndexed { index, leaflet ->
            if (index > 0 || packaging != null) {
                RowDivider()
            }
            val canOpen = leaflet.canOpenPdf && note.isEditable
            IconInfoRow(
                icon = R.drawable.ic_ui_print,
                title = leaflet.name,
                subtitle = pluralStringResource(R.plurals.goodsout_insert_copies, leaflet.copies, leaflet.copies),
                tone = if (leaflet.isDone) Tone.Success else Tone.Warn,
                chip = leaflet.stateLabel.takeIf { it.isNotBlank() },
                chipTone = if (leaflet.isDone) Tone.Success else Tone.Warn,
                actionLabel = if (canOpen) (if (openingLeafletId == leaflet.id) openingLabel else openLabel) else null,
                onAction = if (canOpen) ({ onOpenPdf(leaflet) }) else null,
            )
        }
    }
    if (inserts.canPrintAll && note.isEditable) {
        AikuButton(
            text = stringResource(R.string.goodsout_print_all_inserts),
            onClick = onPrintAll,
            style = ButtonStyle.Soft,
            icon = R.drawable.ic_ui_print,
            isLoading = isPrinting,
            height = 48.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
