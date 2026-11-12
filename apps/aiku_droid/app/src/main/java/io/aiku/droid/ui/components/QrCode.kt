package io.aiku.droid.ui.components

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.common.BitMatrix
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlin.math.floor

private const val QR_QUIET_ZONE_MODULES = 2

fun qrMatrixOf(content: String): BitMatrix? = runCatching {
    QRCodeWriter().encode(
        content,
        BarcodeFormat.QR_CODE,
        0,
        0,
        mapOf(EncodeHintType.MARGIN to QR_QUIET_ZONE_MODULES, EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.M),
    )
}.getOrNull()

@Composable
fun QrCode(
    matrix: BitMatrix,
    description: String,
    ink: Color,
    paper: Color,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current

    Canvas(
        modifier = modifier
            .aspectRatio(1f)
            .clip(ControlShape)
            .background(paper)
            .border(1.dp, colors.border, ControlShape)
            .semantics { contentDescription = description },
    ) {
        val cell = floor(size.width / matrix.width).coerceAtLeast(1f)
        val inset = (size.width - cell * matrix.width) / 2
        for (y in 0 until matrix.height) {
            for (x in 0 until matrix.width) {
                if (matrix[x, y]) {
                    drawRect(color = ink, topLeft = Offset(inset + x * cell, inset + y * cell), size = Size(cell, cell))
                }
            }
        }
    }
}
