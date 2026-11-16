package io.aiku.droid.ui.components

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.produceState
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import io.aiku.droid.data.images.ImageSource

val LocalImageSource = staticCompositionLocalOf<ImageSource?> { null }

@Composable
fun rememberRemoteImage(url: String?): ImageBitmap? {
    val source = LocalImageSource.current
    val image by produceState(initialValue = url?.let { source?.cached(it)?.asImageBitmap() }, source, url) {
        value = url?.let { source?.cached(it) ?: source?.load(it) }?.asImageBitmap()
    }
    return image
}
