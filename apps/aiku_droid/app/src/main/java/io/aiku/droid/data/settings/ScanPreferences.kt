package io.aiku.droid.data.settings

import kotlinx.coroutines.flow.StateFlow

enum class ScanMode { Automatic, Scanner, Camera }

data class ScanPreferences(
    val mode: ScanMode = ScanMode.Automatic,
    val isSoundOn: Boolean = true,
    val isVibrationOn: Boolean = true,
)

interface ScanSettings {
    val scanPreferences: StateFlow<ScanPreferences>

    fun updateScanPreferences(chosen: ScanPreferences)
}
