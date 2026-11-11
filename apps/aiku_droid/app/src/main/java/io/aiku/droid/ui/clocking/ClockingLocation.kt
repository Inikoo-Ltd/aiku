package io.aiku.droid.ui.clocking

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import android.location.LocationRequest
import android.os.Build
import android.os.CancellationSignal
import android.os.SystemClock
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
import java.util.function.Consumer
import kotlin.coroutines.resume
import kotlin.math.roundToInt
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull

private const val LOCATION_TIMEOUT_MILLIS = 20_000L
private const val RECENT_LOCATION_MILLIS = 120_000L
private const val NANOS_PER_MILLI = 1_000_000L

private val LocationPermissions = arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION)

internal sealed interface ClockingLocationState {
    data object NeedsPermission : ClockingLocationState
    data object Detecting : ClockingLocationState
    data class Found(val coordinates: ClockingCoordinates, val accuracyMeters: Int?) : ClockingLocationState
    data object Unavailable : ClockingLocationState
    data object NotFound : ClockingLocationState
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
    val providers = locationProviders().filter { LocationManagerCompat.hasProvider(manager, it) && manager.isProviderEnabled(it) }
    if (providers.isEmpty()) {
        return ClockingLocationState.Unavailable
    }
    val location = recentLocation(context, manager, providers)
        ?: withTimeoutOrNull(LOCATION_TIMEOUT_MILLIS) { firstCurrentLocation(context, manager, providers) }
        ?: return ClockingLocationState.NotFound

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

private fun recentLocation(context: Context, manager: LocationManager, providers: List<String>): Location? {
    if (ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED &&
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED
    ) {
        return null
    }
    val oldestAcceptedNanos = SystemClock.elapsedRealtimeNanos() - RECENT_LOCATION_MILLIS * NANOS_PER_MILLI
    return try {
        (providers + LocationManager.PASSIVE_PROVIDER)
            .mapNotNull { manager.getLastKnownLocation(it) }
            .filter { it.elapsedRealtimeNanos >= oldestAcceptedNanos }
            .maxByOrNull { it.elapsedRealtimeNanos }
    } catch (exception: SecurityException) {
        null
    }
}

private suspend fun firstCurrentLocation(context: Context, manager: LocationManager, providers: List<String>): Location? =
    suspendCancellableCoroutine { continuation ->
        val signals = providers.map { CancellationSignal() }
        continuation.invokeOnCancellation { signals.forEach(CancellationSignal::cancel) }
        var pending = providers.size
        val onResult = Consumer<Location?> { location ->
            pending -= 1
            if (continuation.isActive && (location != null || pending == 0)) {
                signals.forEach(CancellationSignal::cancel)
                continuation.resume(location)
            }
        }
        try {
            if (ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED &&
                ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED
            ) {
                continuation.resume(null)
            } else {
                val executor = ContextCompat.getMainExecutor(context)
                providers.forEachIndexed { index, provider ->
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                        val request = LocationRequest.Builder(0L)
                            .setQuality(LocationRequest.QUALITY_HIGH_ACCURACY)
                            .setDurationMillis(LOCATION_TIMEOUT_MILLIS)
                            .build()
                        manager.getCurrentLocation(provider, request, signals[index], executor, onResult)
                    } else {
                        LocationManagerCompat.getCurrentLocation(manager, provider, signals[index], executor) { onResult.accept(it) }
                    }
                }
            }
        } catch (exception: SecurityException) {
            if (continuation.isActive) {
                continuation.resume(null)
            }
        }
    }
