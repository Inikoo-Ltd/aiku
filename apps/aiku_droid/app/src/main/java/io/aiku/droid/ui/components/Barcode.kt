package io.aiku.droid.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.common.BitMatrix
import com.google.zxing.oned.Code128Writer
import com.google.zxing.oned.EAN13Writer

private val EAN13_PATTERN = Regex("^\\d{13}$")
private const val BARCODE_MARGIN_MODULES = 0

enum class LinearBarcodeFormat { Ean13, Code128 }

internal fun linearBarcodeFormatOf(value: String): LinearBarcodeFormat = if (EAN13_PATTERN.matches(value)) LinearBarcodeFormat.Ean13 else LinearBarcodeFormat.Code128

fun linearBarcodeOf(value: String): BitMatrix? {
    val text = value.trim().takeIf { it.isNotEmpty() } ?: return null
    val hints = mapOf(EncodeHintType.MARGIN to BARCODE_MARGIN_MODULES)
    return runCatching {
        when (linearBarcodeFormatOf(text)) {
            LinearBarcodeFormat.Ean13 -> EAN13Writer().encode(text, BarcodeFormat.EAN_13, 0, 1, hints)
            LinearBarcodeFormat.Code128 -> Code128Writer().encode(text, BarcodeFormat.CODE_128, 0, 1, hints)
        }
    }.getOrNull()
}

@Composable
fun LinearBarcode(matrix: BitMatrix, description: String, modifier: Modifier = Modifier, barHeight: Dp = 120.dp) {
    Canvas(
        modifier = modifier
            .fillMaxWidth()
            .height(barHeight)
            .semantics { contentDescription = description },
    ) {
        val module = size.width / matrix.width
        for (x in 0 until matrix.width) {
            if (matrix[x, 0]) {
                drawRect(color = Color.Black, topLeft = Offset(x * module, 0f), size = Size(module, size.height))
            }
        }
    }
}
