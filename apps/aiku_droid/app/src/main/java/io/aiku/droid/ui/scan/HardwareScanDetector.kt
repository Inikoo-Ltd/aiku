package io.aiku.droid.ui.scan

sealed interface ScanKey {
    data class Typed(val character: Char) : ScanKey
    data object Enter : ScanKey
    data object Modifier : ScanKey
    data object Other : ScanKey
}

data class ScanKeyResult(val isConsumed: Boolean, val code: String? = null) {
    companion object {
        val Passed = ScanKeyResult(isConsumed = false)
        val Swallowed = ScanKeyResult(isConsumed = true)
    }
}

class HardwareScanDetector(
    private val maxKeyGapMillis: Long = DEFAULT_MAX_KEY_GAP_MILLIS,
    private val minCodeLength: Int = DEFAULT_MIN_CODE_LENGTH,
) {
    private val burst = StringBuilder()
    private var lastKeyAt = 0L

    fun onKey(key: ScanKey, atMillis: Long): ScanKeyResult = when (key) {
        is ScanKey.Typed -> onTyped(key.character, atMillis)
        ScanKey.Enter -> onEnter(atMillis)
        ScanKey.Modifier -> ScanKeyResult.Passed
        ScanKey.Other -> {
            reset()
            ScanKeyResult.Passed
        }
    }

    fun reset() {
        burst.clear()
    }

    private fun onTyped(character: Char, atMillis: Long): ScanKeyResult {
        val continuesBurst = burst.isNotEmpty() && atMillis - lastKeyAt <= maxKeyGapMillis
        if (!continuesBurst) {
            burst.clear()
            if (character.isWhitespace()) {
                return ScanKeyResult.Passed
            }
        }
        burst.append(character)
        lastKeyAt = atMillis
        return ScanKeyResult.Swallowed
    }

    private fun onEnter(atMillis: Long): ScanKeyResult {
        val code = burst.toString().trim()
        val isScan = burst.length >= minCodeLength && atMillis - lastKeyAt <= maxKeyGapMillis && code.isNotEmpty()
        reset()
        return if (isScan) ScanKeyResult(isConsumed = true, code = code) else ScanKeyResult.Passed
    }

    companion object {
        const val DEFAULT_MAX_KEY_GAP_MILLIS = 50L
        const val DEFAULT_MIN_CODE_LENGTH = 3
    }
}

class RepeatedScanFilter(private val quietMillis: Long = DEFAULT_QUIET_MILLIS) {
    private var lastCode: String? = null
    private var lastSeenAt = 0L

    fun accepts(code: String, atMillis: Long): Boolean {
        val isRepeat = code == lastCode && atMillis - lastSeenAt <= quietMillis
        lastCode = code
        lastSeenAt = atMillis
        return !isRepeat
    }

    companion object {
        const val DEFAULT_QUIET_MILLIS = 2_000L
    }
}
