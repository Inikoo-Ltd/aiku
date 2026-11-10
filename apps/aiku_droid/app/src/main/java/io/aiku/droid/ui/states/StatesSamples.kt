package io.aiku.droid.ui.states

internal const val STATES_SYNC_QUEUE_COUNT_SAMPLE = 3
internal const val STATES_SYNC_QUEUE_SUMMARY_SAMPLE = "Picked 6 × SKO-00733 · Moved 10 × SKO-00412 · Adjustment −2 SKO-00953"

internal const val STATES_SERVER_REFERENCE_SAMPLE = "Ref 7F3A-2C19 · 09:41"

internal val statesSearchSuggestionsSample = listOf("rosemary", "SKO-0", "A-03")

internal const val STATES_REPORT_DETAILS_SAMPLE = "Scanned 5012345678900 at B-06-02 · not found"

internal const val STATES_UNKNOWN_BARCODE_SAMPLE = "5060123456789"
internal const val STATES_CAMERA_CONTEXT_SAMPLE = "Goods in · SDaw-0093"
internal const val STATES_CAMERA_PICK_SAMPLE = "AR24811"
internal const val STATES_SEARCH_QUERY_SAMPLE = "rosemary oil 50ml"

internal object StatesTransferFailureSample {
    const val SKO = "SKO-00733"
    const val ERROR_TITLE = "Couldn’t move stock"
    const val ERROR_BODY = "A-03-02 has only 30 units. Someone picked from it a moment ago."
    const val FROM_LABEL = "From"
    const val FROM_LOCATION = "A-03-02"
    const val FROM_HELPER = "30 in location now"
    const val TO_LABEL = "To"
    const val TO_LOCATION = "B-01-04"
    const val QUANTITY_LABEL = "Quantity"
    const val QUANTITY = "36"
    const val QUANTITY_ERROR = "Max 30 — lower the quantity or choose “Move all”."
    const val RETRY_LABEL = "Move 30 units"
}

internal object StatesConflictSample {
    const val REFERENCE = "DN-24817"
    const val MESSAGE = "Sam P. picked 2 items and changed the picker while you were offline. Reload to see the latest version before continuing."
}

internal val statesConflictChangesSample = listOf(
    ConflictChange(label = "SKO-00733", detail = "Picked 3 → 5 of 6"),
    ConflictChange(label = "Picker", detail = "Alex Morgan → Sam P."),
)
