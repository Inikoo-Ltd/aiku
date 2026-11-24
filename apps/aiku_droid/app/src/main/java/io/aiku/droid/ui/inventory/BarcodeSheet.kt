package io.aiku.droid.ui.inventory

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.view.WindowManager
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.LinearBarcode
import io.aiku.droid.ui.components.linearBarcodeOf
import io.aiku.droid.ui.theme.LocalAikuColors

private val PaperShape = RoundedCornerShape(12.dp)
private const val FULL_BRIGHTNESS = 1f

data class ShownBarcode(val title: String, val value: String)

@Composable
fun BarcodeSheet(barcode: ShownBarcode, subtitle: String, onDismiss: () -> Unit) {
    val colors = LocalAikuColors.current
    val matrix = remember(barcode.value) { linearBarcodeOf(barcode.value) }

    FullBrightness()

    AikuBottomSheet(title = barcode.title, subtitle = subtitle, onDismiss = onDismiss) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(10.dp),
            modifier = Modifier
                .fillMaxWidth()
                .clip(PaperShape)
                .background(Color.White)
                .border(1.dp, colors.border, PaperShape)
                .padding(horizontal = 24.dp, vertical = 24.dp),
        ) {
            if (matrix != null) {
                LinearBarcode(matrix = matrix, description = stringResource(R.string.inventory_barcode_description, barcode.value))
            } else {
                Text(text = stringResource(R.string.inventory_barcode_unreadable), fontSize = 13.sp, color = Color.DarkGray, textAlign = TextAlign.Center)
            }
            Text(
                text = barcode.value,
                fontSize = 20.sp,
                fontWeight = FontWeight.SemiBold,
                fontFamily = FontFamily.Monospace,
                letterSpacing = 2.sp,
                color = Color.Black,
            )
        }
        AikuButton(text = stringResource(R.string.goodsout_close), onClick = onDismiss, style = ButtonStyle.Outline, height = 52.dp, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun FullBrightness() {
    val activity = LocalContext.current.findActivity()

    DisposableEffect(activity) {
        val window = activity?.window
        val previous = window?.attributes?.screenBrightness ?: WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_NONE
        window?.let { it.attributes = it.attributes.apply { screenBrightness = FULL_BRIGHTNESS } }
        onDispose {
            window?.let { it.attributes = it.attributes.apply { screenBrightness = previous } }
        }
    }
}

private tailrec fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}
