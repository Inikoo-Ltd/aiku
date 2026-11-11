package io.aiku.droid.ui.clocking

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import android.os.Build
import android.os.CancellationSignal
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import androidx.core.location.LocationManagerCompat
import androidx.lifecycle.compose.LifecycleResumeEffect
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume
import kotlin.math.roundToInt

private const val LOCATION_TIMEOUT_MILLIS = 20_000L

private val LocationPermissions = arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION)

internal sealed interface ClockingLocationState {
    data object NeedsPermission : ClockingLocationState
    data object Detecting : ClockingLocationState
    data class Found(val coordinates: ClockingCoordinates, val accuracyMeters: Int?) : ClockingLocationState
    data object Unavailable : ClockingLocationState
}

internal class ClockingLocation(val state: ClockingLocationState, val detect: () -> Unit) {
    val coordinates: ClockingCoordinates? get() = (state as? ClockingLocationState.Found)?.coordinates
}

@Composable
internal fun rememberClockingLocation(isActive: Boolean, onPermissionDenied: () -> Unit): ClockingLocation {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val currentOnPermissionDenied by rememberUpdatedState(onPermissionDenied)
    var state by remember { mutableStateOf<ClockingLocationState>(ClockingLocationState.NeedsPermission) }
    var hasAskedPermission by rememberSaveable { mutableStateOf(false) }
    var detection by remember { mutableStateOf<Job?>(null) }

    val startDetection = {
        detection?.cancel()
        state = ClockingLocationState.Detecting
        detection = scope.launch { state = locate(context) }
    }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { grants ->
        if (grants.values.any { it }) {
            startDetection()
        } else {
            state = ClockingLocationState.NeedsPermission
            currentOnPermissionDenied()
        }
    }
    val requestOrDetect = {
        if (hasLocationPermission(context)) {
            startDetection()
        } else {
            hasAskedPermission = true
            permissionLauncher.launch(LocationPermissions)
        }
    }

    LaunchedEffect(isActive) {
        when {
            !isActive -> Unit
            hasLocationPermission(context) -> startDetection()
            !hasAskedPermission -> requestOrDetect()
            else -> state = ClockingLocationState.NeedsPermission
        }
    }
    LifecycleResumeEffect(isActive) {
        if (isActive && state == ClockingLocationState.NeedsPermission && hasLocationPermission(context)) {
            startDetection()
        }
        onPauseOrDispose { }
    }

    return ClockingLocation(state = state, detect = requestOrDetect)
}

private fun hasLocationPermission(context: Context): Boolean =
    LocationPermissions.any { ContextCompat.checkSelfPermission(context, it) == PackageManager.PERMISSION_GRANTED }

private suspend fun locate(context: Context): ClockingLocationState {
    val manager = context.getSystemService(LocationManager::class.java) ?: return ClockingLocationState.Unavailable
    if (!LocationManagerCompat.isLocationEnabled(manager)) {
        return ClockingLocationState.Unavailable
    }
    val provider = locationProviders().firstOrNull { LocationManagerCompat.hasProvider(manager, it) && manager.isProviderEnabled(it) }
        ?: return ClockingLocationState.Unavailable
    val location = withTimeoutOrNull(LOCATION_TIMEOUT_MILLIS) { currentLocation(context, manager, provider) }
        ?: return ClockingLocationState.Unavailable

    return ClockingLocationState.Found(
        coordinates = ClockingCoordinates(latitude = location.latitude, longitude = location.longitude),
        accuracyMeters = location.takeIf { it.hasAccuracy() }?.accuracy?.roundToInt(),
    )
}

private fun locationProviders(): List<String> = buildList {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
        add(LocationManager.FUSED_PROVIDER)
    }
    add(LocationManager.GPS_PROVIDER)
    add(LocationManager.NETWORK_PROVIDER)
}

private suspend fun currentLocation(context: Context, manager: LocationManager, provider: String): Location? =
    suspendCancellableCoroutine { continuation ->
        val cancellation = CancellationSignal()
        continuation.invokeOnCancellation { cancellation.cancel() }
        try {
            if (ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED &&
                ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED
            ) {
                continuation.resume(null)
            } else {
                LocationManagerCompat.getCurrentLocation(manager, provider, cancellation, ContextCompat.getMainExecutor(context)) { location ->
                    if (continuation.isActive) {
                        continuation.resume(location)
                    }
                }
            }
        } catch (exception: SecurityException) {
            continuation.resume(null)
        }
    }
