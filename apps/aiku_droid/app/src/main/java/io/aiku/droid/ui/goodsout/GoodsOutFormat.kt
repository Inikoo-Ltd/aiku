package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.ui.clocking.LocalClocking
import io.aiku.droid.ui.components.DeliveryNoteStage
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNoteFinalisedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.DeliveryNoteToDoRoute
import java.text.DecimalFormat
import java.text.DecimalFormatSymbols
import java.time.Instant
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale
import kotlin.math.roundToInt

private const val DATE_TIME_PATTERN = "d MMM HH:mm"
private const val PLURAL_FOR_FRACTION = 2
internal const val CANCELLED_STATE = "cancelled"

internal val stageBuckets = listOf(
    DeliveryNoteStage.ToDo to DeliveryNoteBucket.Unassigned,
    DeliveryNoteStage.Picking to DeliveryNoteBucket.Handling,
    DeliveryNoteStage.Waiting to DeliveryNoteBucket.HandlingBlocked,
    DeliveryNoteStage.Picked to DeliveryNoteBucket.Picked,
    DeliveryNoteStage.Packing to DeliveryNoteBucket.Packing,
    DeliveryNoteStage.Packed to DeliveryNoteBucket.Packed,
    DeliveryNoteStage.Finalised to DeliveryNoteBucket.Finalised,
    DeliveryNoteStage.Dispatched to DeliveryNoteBucket.Dispatched,
)

internal val filterChannels = listOf(
    DispatchChannel.All,
    DispatchChannel.B2b,
    DispatchChannel.Dropshipping,
    DispatchChannel.B2c,
    DispatchChannel.External,
    DispatchChannel.Partners,
    DispatchChannel.Fulfilment,
)

internal fun stageOf(state: String): DeliveryNoteStage? = when (DeliveryNoteBucket.ofKey(state)) {
    DeliveryNoteBucket.Unassigned, DeliveryNoteBucket.Queued -> DeliveryNoteStage.ToDo
    DeliveryNoteBucket.Handling -> DeliveryNoteStage.Picking
    DeliveryNoteBucket.HandlingBlocked -> DeliveryNoteStage.Waiting
    DeliveryNoteBucket.Picked -> DeliveryNoteStage.Picked
    DeliveryNoteBucket.Packing -> DeliveryNoteStage.Packing
    DeliveryNoteBucket.Packed -> DeliveryNoteStage.Packed
    DeliveryNoteBucket.Finalised -> DeliveryNoteStage.Finalised
    DeliveryNoteBucket.Dispatched -> DeliveryNoteStage.Dispatched
    DeliveryNoteBucket.InWarehouse, DeliveryNoteBucket.All, null -> null
}

internal fun stageTone(stage: DeliveryNoteStage?): Tone = when (stage) {
    DeliveryNoteStage.ToDo, null -> Tone.Neutral
    DeliveryNoteStage.Waiting -> Tone.Warn
    DeliveryNoteStage.Finalised, DeliveryNoteStage.Dispatched -> Tone.Success
    DeliveryNoteStage.Picking, DeliveryNoteStage.Picked, DeliveryNoteStage.Packing, DeliveryNoteStage.Packed -> Tone.Primary
}

fun deliveryNoteRoute(id: Long, reference: String, state: String): Any {
    if (state == CANCELLED_STATE) {
        return DeliveryNoteCancelledRoute(id = id, reference = reference)
    }
    return when (stageOf(state)) {
        DeliveryNoteStage.Picking, DeliveryNoteStage.Waiting -> DeliveryNotePickingRoute(id = id, reference = reference)
        DeliveryNoteStage.Picked -> DeliveryNotePickedRoute(id = id, reference = reference)
        DeliveryNoteStage.Packing, DeliveryNoteStage.Packed -> DeliveryNotePackingRoute(id = id, reference = reference)
        DeliveryNoteStage.Finalised -> DeliveryNoteFinalisedRoute(id = id, reference = reference)
        DeliveryNoteStage.Dispatched -> DeliveryNoteDispatchedRoute(id = id, reference = reference)
        DeliveryNoteStage.ToDo, null -> DeliveryNoteToDoRoute(id = id, reference = reference)
    }
}

@StringRes
internal fun channelLabel(channel: DispatchChannel): Int = when (channel) {
    DispatchChannel.All -> R.string.goodsout_channel_all
    DispatchChannel.B2b -> R.string.goodsout_channel_wholesale
    DispatchChannel.Dropshipping -> R.string.goodsout_channel_dropshipping
    DispatchChannel.B2c -> R.string.goodsout_channel_b2c
    DispatchChannel.External -> R.string.goodsout_channel_marketplaces
    DispatchChannel.Partners -> R.string.goodsout_channel_partners
    DispatchChannel.Fulfilment -> R.string.goodsout_channel_fulfilment
}

@Composable
internal fun channelName(channel: String, fallback: String? = null): String? =
    DispatchChannel.ofKey(channel)?.takeIf { it != DispatchChannel.All }?.let { stringResource(channelLabel(it)) }
        ?: fallback?.takeIf { it.isNotBlank() }

internal fun quantityText(value: Double, label: String? = null): String =
    label?.takeIf { it.isNotBlank() } ?: decimalFormat().format(value)

internal fun pluralQuantity(value: Double): Int = if (value == value.toInt().toDouble()) value.toInt() else PLURAL_FOR_FRACTION

internal fun minutesText(value: Double?): Int? = value?.takeIf { it > 0 }?.let { maxOf(1, it.roundToInt()) }

@Composable
internal fun weightText(kilograms: Double?): String? =
    kilograms?.takeIf { it > 0 }?.let { stringResource(R.string.goodsout_weight_kg, decimalFormat().format(it)) }

internal fun dateTimeText(isoDateTime: String?, zone: ZoneId): String? {
    val text = isoDateTime?.takeIf { it.isNotBlank() } ?: return null
    val instant = runCatching { OffsetDateTime.parse(text).toInstant() }.getOrNull()
        ?: runCatching { Instant.parse(text) }.getOrNull()
        ?: return null
    return DateTimeFormatter.ofPattern(DATE_TIME_PATTERN, Locale.getDefault()).withZone(zone).format(instant)
}

@Composable
internal fun organisationZone(): ZoneId {
    val clocking by LocalClocking.current.state.collectAsStateWithLifecycle()
    return clocking.zone
}

private fun decimalFormat(): DecimalFormat = DecimalFormat("0.##", DecimalFormatSymbols.getInstance(Locale.getDefault()))
