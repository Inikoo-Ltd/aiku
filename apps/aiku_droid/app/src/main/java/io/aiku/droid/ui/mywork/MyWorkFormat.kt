package io.aiku.droid.ui.mywork

import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.MyWorkRow
import io.aiku.droid.data.api.MyWorkStep
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.fulfilment.outbound.returnRoute
import io.aiku.droid.ui.fulfilment.outbound.returnScreenFor
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.navigation.GoodsInReturnRoute
import io.aiku.droid.ui.navigation.PickingSessionRoute
import java.time.Duration
import java.time.Instant
import java.time.OffsetDateTime

private const val MINUTES_PER_HOUR = 60L
private const val MINUTES_PER_DAY = 24L * MINUTES_PER_HOUR

enum class ElapsedUnit { JustNow, Minutes, Hours, Days }

data class Elapsed(val unit: ElapsedUnit, val amount: Int)

fun MyWorkRow.route(): Any = when (kind) {
    MyWorkKind.DeliveryNote -> deliveryNoteRoute(id = id, reference = reference, state = state)
    MyWorkKind.PickingSession -> PickingSessionRoute(id = id, reference = reference)
    MyWorkKind.CustomerReturn -> GoodsInReturnRoute(id = id, reference = reference)
    MyWorkKind.PalletReturn -> returnRoute(returnScreenFor(state, typeGroup ?: PalletReturnTypeGroup.PALLETS), id, reference)
}

fun myWorkStepTone(step: String): Tone = when (step) {
    MyWorkStep.WAITING -> Tone.Warn
    MyWorkStep.TO_PICK, MyWorkStep.TO_PACK, MyWorkStep.TO_START, MyWorkStep.TO_DISPATCH -> Tone.Primary
    else -> Tone.Neutral
}

fun elapsedSince(since: String?, now: Instant): Elapsed? {
    val text = since?.takeIf { it.isNotBlank() } ?: return null
    val start = runCatching { OffsetDateTime.parse(text).toInstant() }.getOrNull()
        ?: runCatching { Instant.parse(text) }.getOrNull()
        ?: return null
    val minutes = Duration.between(start, now).toMinutes()
    return when {
        minutes < 1 -> Elapsed(ElapsedUnit.JustNow, 0)
        minutes < MINUTES_PER_HOUR -> Elapsed(ElapsedUnit.Minutes, minutes.toInt())
        minutes < MINUTES_PER_DAY -> Elapsed(ElapsedUnit.Hours, (minutes / MINUTES_PER_HOUR).toInt())
        else -> Elapsed(ElapsedUnit.Days, (minutes / MINUTES_PER_DAY).toInt())
    }
}
