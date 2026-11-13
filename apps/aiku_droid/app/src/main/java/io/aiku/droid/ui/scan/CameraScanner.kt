package io.aiku.droid.ui.scan

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.SystemClock
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.mlkit.vision.MlKitAnalyzer
import androidx.camera.view.CameraController
import androidx.camera.view.LifecycleCameraController
import androidx.camera.view.PreviewView
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.compose.LifecycleResumeEffect
import androidx.lifecycle.compose.LocalLifecycleOwner
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode

enum class BarcodeSet { Clocking, Warehouse }

private fun BarcodeSet.scannerOptions(): BarcodeScannerOptions = when (this) {
    BarcodeSet.Clocking -> BarcodeScannerOptions.Builder().setBarcodeFormats(Barcode.FORMAT_QR_CODE).build()
    BarcodeSet.Warehouse -> BarcodeScannerOptions.Builder()
        .setBarcodeFormats(
            Barcode.FORMAT_CODE_128,
            Barcode.FORMAT_EAN_13,
            Barcode.FORMAT_EAN_8,
            Barcode.FORMAT_UPC_A,
            Barcode.FORMAT_UPC_E,
            Barcode.FORMAT_QR_CODE,
            Barcode.FORMAT_DATA_MATRIX,
        )
        .build()
}

class CameraAccess(val isAllowed: Boolean, val isRefused: Boolean)

private fun isCameraAllowed(context: Context): Boolean =
    ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED

@Composable
fun rememberCameraAccess(): CameraAccess {
    val context = LocalContext.current
    var isAllowed by remember { mutableStateOf(isCameraAllowed(context)) }
    var isRefused by rememberSaveable { mutableStateOf(false) }
    val permissionLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        isAllowed = granted
        isRefused = !granted
    }

    LaunchedEffect(Unit) {
        if (!isAllowed && !isRefused) {
            permissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }
    LifecycleResumeEffect(Unit) {
        if (isCameraAllowed(context)) {
            isAllowed = true
            isRefused = false
        }
        onPauseOrDispose { }
    }

    return CameraAccess(isAllowed = isAllowed, isRefused = isRefused && !isAllowed)
}

@Composable
fun CameraBarcodePreview(
    barcodes: BarcodeSet,
    onScanned: (String) -> Unit,
    modifier: Modifier = Modifier,
    isSingleShot: Boolean = false,
    isTorchOn: Boolean = false,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val currentOnScanned by rememberUpdatedState(onScanned)
    val controller = remember(context) { LifecycleCameraController(context) }

    DisposableEffect(controller, lifecycleOwner, barcodes, isSingleShot) {
        val executor = ContextCompat.getMainExecutor(context)
        val scanner = BarcodeScanning.getClient(barcodes.scannerOptions())
        val repeats = RepeatedScanFilter()
        var hasScanned = false

        controller.cameraSelector = CameraSelector.DEFAULT_BACK_CAMERA
        controller.setEnabledUseCases(CameraController.IMAGE_ANALYSIS)
        controller.setImageAnalysisAnalyzer(
            executor,
            MlKitAnalyzer(listOf(scanner), ImageAnalysis.COORDINATE_SYSTEM_ORIGINAL, executor) { result ->
                val code = result.getValue(scanner)?.firstNotNullOfOrNull { barcode -> barcode.rawValue?.takeIf { it.isNotBlank() } }
                if (code != null && !hasScanned && repeats.accepts(code, SystemClock.uptimeMillis())) {
                    if (isSingleShot) {
                        hasScanned = true
                        controller.clearImageAnalysisAnalyzer()
                    }
                    currentOnScanned(code)
                }
            },
        )
        controller.bindToLifecycle(lifecycleOwner)

        onDispose {
            controller.clearImageAnalysisAnalyzer()
            controller.unbind()
            scanner.close()
        }
    }
    LaunchedEffect(controller, isTorchOn) {
        controller.enableTorch(isTorchOn)
    }

    AndroidView(
        factory = { viewContext ->
            PreviewView(viewContext).apply {
                scaleType = PreviewView.ScaleType.FILL_CENTER
                this.controller = controller
            }
        },
        modifier = modifier,
    )
}
