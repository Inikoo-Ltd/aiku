package io.aiku.droid.ui.splash

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.EnterTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.size
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.res.vectorResource
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import io.aiku.droid.R
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

private const val LOGO_SCREEN_FRACTION = 0.42f
private const val CROSSFADE_MILLIS = 250
private const val PROGRESS_FADE_MILLIS = 300
private val ProgressSize = 24.dp
private val ProgressGap = 32.dp

@Composable
fun StartupSplash(state: StartupSplashState, content: @Composable () -> Unit) {
    Box(modifier = Modifier.fillMaxSize()) {
        Box(
            propagateMinConstraints = true,
            modifier = Modifier
                .fillMaxSize()
                .then(if (state.isVisible) Modifier.clearAndSetSemantics {} else Modifier),
        ) {
            content()
        }
        AnimatedVisibility(
            visible = state.isVisible,
            enter = EnterTransition.None,
            exit = fadeOut(animationSpec = tween(durationMillis = CROSSFADE_MILLIS)),
        ) {
            SplashScreen(showsProgress = state.showsProgress)
        }
    }
}

@Composable
private fun SplashScreen(showsProgress: Boolean) {
    val colors = LocalAikuColors.current
    val logo = ImageVector.vectorResource(R.drawable.aiku_invader)

    BoxWithConstraints(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .fillMaxSize()
            .background(colors.background)
            .blockTouches(),
    ) {
        Image(
            painter = painterResource(if (colors.isDark) R.drawable.login_background_dark else R.drawable.login_background),
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize(),
        )
        val logoWidth = minOf(maxWidth, maxHeight) * LOGO_SCREEN_FRACTION
        val logoHeight = logoWidth * (logo.viewportHeight / logo.viewportWidth)
        Image(
            imageVector = logo,
            contentDescription = stringResource(R.string.brand_name),
            contentScale = ContentScale.FillBounds,
            modifier = Modifier.size(width = logoWidth, height = logoHeight),
        )
        AnimatedVisibility(
            visible = showsProgress,
            enter = fadeIn(animationSpec = tween(durationMillis = PROGRESS_FADE_MILLIS)),
            exit = fadeOut(animationSpec = tween(durationMillis = PROGRESS_FADE_MILLIS)),
            modifier = Modifier.offset(y = logoHeight / 2 + ProgressGap + ProgressSize / 2),
        ) {
            CircularProgressIndicator(color = colors.textTertiary, strokeWidth = 2.dp, modifier = Modifier.size(ProgressSize))
        }
    }
}

private fun Modifier.blockTouches(): Modifier = pointerInput(Unit) {
    awaitPointerEventScope {
        while (true) {
            awaitPointerEvent().changes.forEach { it.consume() }
        }
    }
}

@Preview(showSystemUi = true)
@Composable
private fun StartupSplashPreview() {
    AikuDroidTheme {
        StartupSplash(state = StartupSplashState(isVisible = true)) {}
    }
}

@Preview(showSystemUi = true)
@Composable
private fun StartupSplashSlowPreview() {
    AikuDroidTheme {
        StartupSplash(state = StartupSplashState(isVisible = true, showsProgress = true)) {}
    }
}
