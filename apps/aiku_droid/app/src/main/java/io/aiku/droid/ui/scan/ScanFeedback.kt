package io.aiku.droid.ui.scan

import android.content.Context
import android.media.AudioManager
import android.media.ToneGenerator
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import io.aiku.droid.data.settings.ScanPreferences

interface ScanSignals {
    fun successTone()
    fun errorTone()
    fun successVibration()
    fun errorVibration()
}

object SilentScanSignals : ScanSignals {
    override fun successTone() = Unit
    override fun errorTone() = Unit
    override fun successVibration() = Unit
    override fun errorVibration() = Unit
}

class ScanFeedback(private val preferences: () -> ScanPreferences, private val signals: ScanSignals) {

    fun accepted() {
        val current = preferences()
        if (current.isSoundOn) {
            signals.successTone()
        }
        if (current.isVibrationOn) {
            signals.successVibration()
        }
    }

    fun rejected() {
        val current = preferences()
        if (current.isSoundOn) {
            signals.errorTone()
        }
        if (current.isVibrationOn) {
            signals.errorVibration()
        }
    }
}

class DeviceScanSignals(context: Context) : ScanSignals {

    private val vibrator: Vibrator? = context.defaultVibrator()?.takeIf { it.hasVibrator() }

    private val tones: ToneGenerator? by lazy {
        runCatching { ToneGenerator(AudioManager.STREAM_MUSIC, ToneGenerator.MAX_VOLUME) }.getOrNull()
    }

    override fun successTone() {
        tones?.startTone(ToneGenerator.TONE_PROP_BEEP, SUCCESS_TONE_MILLIS)
    }

    override fun errorTone() {
        tones?.startTone(ToneGenerator.TONE_PROP_NACK, ERROR_TONE_MILLIS)
    }

    override fun successVibration() {
        vibrator?.vibrate(VibrationEffect.createOneShot(SUCCESS_VIBRATION_MILLIS, VibrationEffect.DEFAULT_AMPLITUDE))
    }

    override fun errorVibration() {
        vibrator?.vibrate(VibrationEffect.createWaveform(ErrorVibrationPattern, NO_REPEAT))
    }

    private companion object {
        const val SUCCESS_TONE_MILLIS = 150
        const val ERROR_TONE_MILLIS = 400
        const val SUCCESS_VIBRATION_MILLIS = 40L
        const val NO_REPEAT = -1
        val ErrorVibrationPattern = longArrayOf(0L, 70L, 90L, 70L)
    }
}

private fun Context.defaultVibrator(): Vibrator? =
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
        getSystemService(VibratorManager::class.java)?.defaultVibrator
    } else {
        getSystemService(Vibrator::class.java)
    }

private class HeardMiss(var value: Any?)

@Composable
fun ScanMissEffect(miss: Any?) {
    val feedback = LocalScanController.current.feedback
    val heard = remember { HeardMiss(miss) }

    LaunchedEffect(miss) {
        if (miss != null && miss != heard.value) {
            feedback.rejected()
        }
        heard.value = miss
    }
}
