package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.data.api.DeliveryNoteParcel
import io.aiku.droid.data.api.ParcelWrite
import io.aiku.droid.data.api.WriteErrorKey
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors
import java.math.BigDecimal
import java.math.RoundingMode

internal const val MAX_PARCELS = 50
private const val MAX_PARCEL_WEIGHT_KG = 1000.0
private const val MAX_PARCEL_DIMENSION_CM = 1000.0
private const val PARCEL_DIMENSIONS = 3
private const val MAX_PARCEL_INPUT_LENGTH = 7
private val ParcelServerErrorKey = Regex("""^parcels\.(\d+)\.(weight_kg|weight|dimensions_cm|dimensions|boxes)(?:\.(\d+))?$""")

internal enum class ParcelField { Weight, Length, Width, Height, Boxes }

internal enum class ParcelProblem { WeightMissing, WeightOutOfRange, DimensionMissing, DimensionOutOfRange, BoxesInvalid }

internal enum class ParcelListProblem { NoParcels, TooManyParcels }

internal data class ParcelFieldKey(val index: Int, val field: ParcelField)

internal data class ParcelInput(val weight: String, val length: String, val width: String, val height: String, val boxes: String) {
    fun value(field: ParcelField): String = when (field) {
        ParcelField.Weight -> weight
        ParcelField.Length -> length
        ParcelField.Width -> width
        ParcelField.Height -> height
        ParcelField.Boxes -> boxes
    }

    fun with(field: ParcelField, text: String): ParcelInput = when (field) {
        ParcelField.Weight -> copy(weight = text)
        ParcelField.Length -> copy(length = text)
        ParcelField.Width -> copy(width = text)
        ParcelField.Height -> copy(height = text)
        ParcelField.Boxes -> copy(boxes = text)
    }

    companion object {
        fun of(parcel: DeliveryNoteParcel): ParcelInput = ParcelInput(
            weight = parcel.weightKg?.let(::editableNumber).orEmpty(),
            length = parcel.dimensionsCm.getOrNull(0)?.let(::editableNumber).orEmpty(),
            width = parcel.dimensionsCm.getOrNull(1)?.let(::editableNumber).orEmpty(),
            height = parcel.dimensionsCm.getOrNull(2)?.let(::editableNumber).orEmpty(),
            boxes = parcel.boxes?.toString().orEmpty(),
        )

        fun added(estimatedWeightKg: Double?, parcelCount: Int): ParcelInput = ParcelInput(
            weight = estimatedWeightKg?.takeIf { it > 0.0 && parcelCount > 0 }?.let { editableNumber(roundedUpToTenth(it / parcelCount)) }.orEmpty(),
            length = "",
            width = "",
            height = "",
            boxes = "",
        )
    }
}

internal sealed interface ParcelCheck {
    data class Valid(val parcels: List<ParcelWrite>) : ParcelCheck
    data class Invalid(val list: ParcelListProblem?, val fields: Map<ParcelFieldKey, ParcelProblem>) : ParcelCheck
}

internal data class ParcelServerErrors(val general: List<String>, val rows: Map<Int, List<String>>, val fields: Set<ParcelFieldKey>)

internal fun editableNumber(value: Double): String = BigDecimal.valueOf(value).stripTrailingZeros().toPlainString()

internal fun roundedUpToTenth(value: Double): Double = BigDecimal.valueOf(value).setScale(1, RoundingMode.CEILING).toDouble()

internal fun parseDecimal(text: String): Double? = text.trim().replace(',', '.').toDoubleOrNull()

private val dimensionFields = listOf(ParcelField.Length, ParcelField.Width, ParcelField.Height)

internal fun checkParcels(inputs: List<ParcelInput>, withBoxes: Boolean): ParcelCheck {
    val fields = mutableMapOf<ParcelFieldKey, ParcelProblem>()
    val parcels = inputs.mapIndexed { index, input ->
        val weight = parseDecimal(input.weight)
        when {
            weight == null -> fields[ParcelFieldKey(index, ParcelField.Weight)] = ParcelProblem.WeightMissing
            weight <= 0.0 || weight > MAX_PARCEL_WEIGHT_KG -> fields[ParcelFieldKey(index, ParcelField.Weight)] = ParcelProblem.WeightOutOfRange
        }
        val dimensions = dimensionFields.map { field ->
            val dimension = parseDecimal(input.value(field))
            when {
                dimension == null -> fields[ParcelFieldKey(index, field)] = ParcelProblem.DimensionMissing
                dimension <= 0.0 || dimension > MAX_PARCEL_DIMENSION_CM -> fields[ParcelFieldKey(index, field)] = ParcelProblem.DimensionOutOfRange
            }
            dimension ?: 0.0
        }
        val boxesText = input.boxes.trim()
        val boxes = if (withBoxes && boxesText.isNotEmpty()) boxesText.toIntOrNull() else null
        if (withBoxes && boxesText.isNotEmpty() && (boxes == null || boxes < 1)) {
            fields[ParcelFieldKey(index, ParcelField.Boxes)] = ParcelProblem.BoxesInvalid
        }
        ParcelWrite(weightKg = weight ?: 0.0, dimensionsCm = dimensions, boxes = boxes)
    }
    val listProblem = when {
        inputs.isEmpty() -> ParcelListProblem.NoParcels
        inputs.size > MAX_PARCELS -> ParcelListProblem.TooManyParcels
        else -> null
    }
    return if (listProblem == null && fields.isEmpty()) ParcelCheck.Valid(parcels) else ParcelCheck.Invalid(listProblem, fields)
}

internal fun parcelServerErrors(errors: Map<String, List<String>>): ParcelServerErrors {
    val general = mutableListOf<String>()
    val rows = mutableMapOf<Int, MutableList<String>>()
    val fields = mutableSetOf<ParcelFieldKey>()
    errors.filterKeys { it == WriteErrorKey.PARCELS || it.startsWith(WriteErrorKey.PARCELS + ".") }.forEach { (key, messages) ->
        val match = ParcelServerErrorKey.matchEntire(key)
        if (match == null) {
            general += messages
            return@forEach
        }
        val index = match.groupValues[1].toInt()
        val dimension = match.groupValues[3].toIntOrNull()
        rows.getOrPut(index) { mutableListOf() } += messages
        fields += when (match.groupValues[2]) {
            "weight_kg", "weight" -> listOf(ParcelFieldKey(index, ParcelField.Weight))
            "boxes" -> listOf(ParcelFieldKey(index, ParcelField.Boxes))
            else -> dimensionFields.filterIndexed { position, _ -> dimension == null || dimension == position }.map { ParcelFieldKey(index, it) }
        }
    }
    return ParcelServerErrors(general = general, rows = rows, fields = fields)
}

@Composable
internal fun DeliveryNoteParcels(note: DeliveryNote, writeErrors: Map<String, List<String>>, isSaving: Boolean, onSave: (List<ParcelWrite>) -> Unit) {
    val withBoxes = note.boxPackingList != null
    if (!note.parcelsEditable) {
        ReadOnlyParcels(parcels = note.parcels, withBoxes = withBoxes)
        return
    }
    val saved = remember(note.parcels) { note.parcels.map(ParcelInput::of) }
    val starter = if (saved.isEmpty() && note.stage == DeliveryNoteStage.Packing) listOf(ParcelInput.added(note.summary.estimatedWeightKg, parcelCount = 1)) else saved
    var inputs by remember(note.parcels) { mutableStateOf(starter) }
    var check by remember(note.parcels) { mutableStateOf<ParcelCheck.Invalid?>(null) }
    val server = remember(writeErrors) { parcelServerErrors(writeErrors) }
    val update: (List<ParcelInput>) -> Unit = {
        inputs = it
        check = null
    }

    GoodsOutHeading(
        title = stringResource(R.string.goodsout_parcels_count, inputs.size),
        trailing = stringResource(if (withBoxes) R.string.goodsout_parcel_units_boxes else R.string.goodsout_parcel_units),
    )
    ListGroup {
        inputs.forEachIndexed { index, input ->
            if (index > 0) {
                RowDivider()
            }
            ParcelEditorRow(
                index = index,
                input = input,
                withBoxes = withBoxes,
                problems = check?.fields.orEmpty().filterKeys { it.index == index },
                serverMessages = server.rows[index].orEmpty(),
                serverFields = server.fields.filter { it.index == index }.toSet(),
                canRemove = inputs.size > 1,
                onChange = { changed -> update(inputs.mapIndexed { position, current -> if (position == index) changed else current }) },
                onRemove = { update(inputs.filterIndexed { position, _ -> position != index }) },
            )
        }
        if (inputs.size < MAX_PARCELS) {
            if (inputs.isNotEmpty()) {
                RowDivider()
            }
            Text(
                text = stringResource(if (inputs.isEmpty()) R.string.goodsout_add_first_parcel else R.string.goodsout_add_parcel),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = LocalAikuColors.current.link,
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 48.dp)
                    .clickable(role = Role.Button) { update(inputs + ParcelInput.added(note.summary.estimatedWeightKg, parcelCount = inputs.size + 1)) }
                    .padding(horizontal = 14.dp, vertical = 14.dp),
            )
        }
    }
    check?.list?.let { problem ->
        InlineAlert(
            title = when (problem) {
                ParcelListProblem.NoParcels -> stringResource(R.string.goodsout_parcels_none)
                ParcelListProblem.TooManyParcels -> pluralStringResource(R.plurals.goodsout_parcels_too_many, MAX_PARCELS, MAX_PARCELS)
            },
            tone = AlertTone.Danger,
        )
    }
    server.general.takeIf { it.isNotEmpty() }?.let { InlineAlert(title = it.joinToString(" "), tone = AlertTone.Danger) }
    if (inputs != saved) {
        AikuButton(
            text = stringResource(R.string.goodsout_parcels_save),
            onClick = {
                when (val result = checkParcels(inputs, withBoxes)) {
                    is ParcelCheck.Valid -> onSave(result.parcels)
                    is ParcelCheck.Invalid -> check = result
                }
            },
            style = ButtonStyle.Soft,
            isLoading = isSaving,
            height = 52.dp,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

@Composable
internal fun ReadOnlyParcels(parcels: List<DeliveryNoteParcel>, withBoxes: Boolean) {
    if (parcels.isEmpty()) {
        return
    }
    GoodsOutHeading(title = stringResource(R.string.goodsout_parcels_count, parcels.size))
    ListGroup {
        parcels.forEachIndexed { index, parcel ->
            if (index > 0) {
                RowDivider()
            }
            IconInfoRow(
                icon = R.drawable.ic_ui_box,
                title = stringResource(R.string.goodsout_parcel_name, index + 1),
                subtitle = parcelSummary(parcel, withBoxes),
            )
        }
    }
}

@Composable
internal fun parcelSummary(parcel: DeliveryNoteParcel, withBoxes: Boolean): String {
    val dimensions = parcel.dimensionsCm.filterNotNull().takeIf { it.size == PARCEL_DIMENSIONS }
    return listOfNotNull(
        parcel.weightKg?.let { stringResource(R.string.goodsout_weight_kg, editableNumber(it)) },
        dimensions?.let { stringResource(R.string.goodsout_parcel_dimensions, editableNumber(it[0]), editableNumber(it[1]), editableNumber(it[2])) }
            ?: stringResource(R.string.goodsout_dimensions_missing),
        parcel.boxes?.takeIf { withBoxes }?.let { pluralStringResource(R.plurals.goodsout_parcel_boxes, it, it) },
    ).joinToString(" · ")
}

@Composable
private fun ParcelEditorRow(
    index: Int,
    input: ParcelInput,
    withBoxes: Boolean,
    problems: Map<ParcelFieldKey, ParcelProblem>,
    serverMessages: List<String>,
    serverFields: Set<ParcelFieldKey>,
    canRemove: Boolean,
    onChange: (ParcelInput) -> Unit,
    onRemove: () -> Unit,
) {
    val colors = LocalAikuColors.current
    val name = stringResource(R.string.goodsout_parcel_name, index + 1)
    val fields = listOfNotNull(
        ParcelField.Weight,
        ParcelField.Length,
        ParcelField.Width,
        ParcelField.Height,
        if (withBoxes) ParcelField.Boxes else null,
    )
    val messages = problems.values.distinct().map { parcelProblemText(it) } + serverMessages

    Column(modifier = Modifier.fillMaxWidth().padding(start = 14.dp, end = 4.dp, top = 6.dp, bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().heightIn(min = 44.dp)) {
            Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
            if (canRemove) {
                RemoveParcelButton(description = stringResource(R.string.goodsout_parcel_remove, name), onClick = onRemove)
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth().padding(end = 10.dp)) {
            fields.forEach { field ->
                val key = ParcelFieldKey(index, field)
                ParcelInputField(
                    label = stringResource(field.label()),
                    value = input.value(field),
                    isWholeNumber = field == ParcelField.Boxes,
                    isError = key in problems || key in serverFields,
                    onValueChange = { onChange(input.with(field, it)) },
                    modifier = Modifier.weight(1f),
                )
            }
        }
        messages.forEach { message ->
            Text(text = message, fontSize = 12.sp, color = colors.danger, modifier = Modifier.padding(end = 10.dp))
        }
    }
}

@StringRes
private fun ParcelField.label(): Int = when (this) {
    ParcelField.Weight -> R.string.goodsout_parcel_kg
    ParcelField.Length -> R.string.goodsout_parcel_length
    ParcelField.Width -> R.string.goodsout_parcel_width
    ParcelField.Height -> R.string.goodsout_parcel_height
    ParcelField.Boxes -> R.string.goodsout_parcel_boxes_short
}

@Composable
private fun parcelProblemText(problem: ParcelProblem): String = when (problem) {
    ParcelProblem.WeightMissing -> stringResource(R.string.goodsout_parcel_weight_missing)
    ParcelProblem.WeightOutOfRange -> stringResource(R.string.goodsout_parcel_weight_range, editableNumber(MAX_PARCEL_WEIGHT_KG))
    ParcelProblem.DimensionMissing -> stringResource(R.string.goodsout_parcel_dimension_missing)
    ParcelProblem.DimensionOutOfRange -> stringResource(R.string.goodsout_parcel_dimension_range, editableNumber(MAX_PARCEL_DIMENSION_CM))
    ParcelProblem.BoxesInvalid -> stringResource(R.string.goodsout_parcel_boxes_invalid)
}

@Composable
private fun RemoveParcelButton(description: String, onClick: () -> Unit) {
    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_close), contentDescription = null, tint = LocalAikuColors.current.textTertiary, modifier = Modifier.size(18.dp))
    }
}

@Composable
internal fun ParcelInputField(
    label: String,
    value: String,
    isWholeNumber: Boolean,
    isError: Boolean,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(8.dp)
    val interaction = remember { MutableInteractionSource() }
    val isFocused by interaction.collectIsFocusedAsState()
    val focusProgress = animateFocusProgress(isFocused)
    val idleBorder = when {
        isError -> colors.danger
        value.isBlank() -> colors.warn
        else -> colors.borderStrong
    }

    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(3.dp)) {
        Text(text = label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = colors.textTertiary, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
        BasicTextField(
            value = value,
            onValueChange = { input -> onValueChange(numericInput(input, isWholeNumber)) },
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = if (isWholeNumber) KeyboardType.Number else KeyboardType.Decimal),
            textStyle = TextStyle(fontFamily = Inter, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.text, textAlign = TextAlign.Center),
            cursorBrush = SolidColor(colors.primary),
            interactionSource = interaction,
            modifier = Modifier
                .fillMaxWidth()
                .height(44.dp)
                .focusHalo(progress = focusProgress, color = colors.focus, cornerRadius = 8.dp)
                .clip(shape)
                .border(1.dp, if (isError) colors.danger else lerp(idleBorder, colors.focus, focusProgress), shape)
                .semantics { contentDescription = label },
            decorationBox = { inner ->
                Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(horizontal = 4.dp)) {
                    inner()
                }
            },
        )
    }
}

internal fun numericInput(input: String, isWholeNumber: Boolean): String {
    val allowed = input.filter { it.isDigit() || (!isWholeNumber && (it == '.' || it == ',')) }
    val separator = allowed.indexOfFirst { it == '.' || it == ',' }
    val single = if (separator < 0) allowed else allowed.substring(0, separator + 1) + allowed.substring(separator + 1).filter { it.isDigit() }
    return single.take(MAX_PARCEL_INPUT_LENGTH)
}
