package io.aiku.droid.ui.update

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.net.toUri
import io.aiku.droid.BuildConfig
import io.aiku.droid.R
import io.aiku.droid.ui.components.ChipShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.states.StatesBody
import io.aiku.droid.ui.states.StatesButton
import io.aiku.droid.ui.states.StatesFullScreen
import io.aiku.droid.ui.states.StatesHeroIcon
import io.aiku.droid.ui.states.StatesTitle
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors
import io.aiku.droid.update.AppUpdate

private fun Context.openUpdateLink(url: String): Boolean = try {
    startActivity(Intent(Intent.ACTION_VIEW, url.toUri()))
    true
} catch (exception: ActivityNotFoundException) {
    false
}

@Composable
fun UpdateRequiredScreen(update: AppUpdate.Required) {
    val colors = LocalAikuColors.current
    val context = LocalContext.current
    var isLinkUnavailable by rememberSaveable { mutableStateOf(false) }

    StatesFullScreen(
        actions = {
            if (update.updateUrl != null) {
                StatesButton(
                    text = stringResource(R.string.app_update_button),
                    onClick = { isLinkUnavailable = !context.openUpdateLink(update.updateUrl) },
                    icon = R.drawable.ic_states_sync,
                )
            }
        },
    ) {
        StatesHeroIcon(icon = R.drawable.ic_states_sync, container = colors.warnSoft, tint = colors.warn)
        StatesTitle(text = stringResource(R.string.app_update_required_title))
        StatesBody(text = AnnotatedString(stringResource(R.string.app_update_required_body, BuildConfig.VERSION_NAME, update.latestVersionName)))
        if (update.updateUrl == null) {
            StatesBody(text = AnnotatedString(stringResource(R.string.app_update_ask_supervisor)))
        }
        if (isLinkUnavailable) {
            Text(
                text = stringResource(R.string.app_update_no_app),
                fontSize = 13.sp,
                color = colors.dangerInk,
                textAlign = TextAlign.Center,
                modifier = Modifier.semantics { liveRegion = LiveRegionMode.Polite },
            )
        }
    }
}

@Composable
fun AppUpdateBanner(update: AppUpdate.Available, onDismiss: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val context = LocalContext.current
    val toasts = LocalToasts.current
    val noAppMessage = stringResource(R.string.app_update_no_app)

    Row(
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(ControlShape)
            .background(colors.soft)
            .border(1.dp, colors.softBorder, ControlShape)
            .semantics { liveRegion = LiveRegionMode.Polite }
            .padding(start = 14.dp, top = 12.dp, bottom = 12.dp, end = 4.dp),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_states_sync),
            contentDescription = null,
            tint = colors.link,
            modifier = Modifier.padding(top = 1.dp).size(20.dp),
        )
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(
                text = stringResource(R.string.app_update_available_title, update.latestVersionName),
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.link,
            )
            Text(
                text = stringResource(if (update.updateUrl != null) R.string.app_update_available_body else R.string.app_update_available_ask_supervisor),
                fontSize = 13.sp,
                lineHeight = 18.sp,
                color = colors.textSecondary,
            )
            if (update.updateUrl != null) {
                Text(
                    text = stringResource(R.string.app_update_button),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.link,
                    modifier = Modifier
                        .padding(top = 6.dp)
                        .clip(ChipShape)
                        .clickable(role = Role.Button) {
                            if (!context.openUpdateLink(update.updateUrl)) {
                                toasts.show(noAppMessage, ToastKind.Error)
                            }
                        }
                        .padding(vertical = 4.dp),
                )
            }
        }
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier
                .size(40.dp)
                .clip(ChipShape)
                .clickable(role = Role.Button, onClickLabel = stringResource(R.string.app_update_dismiss), onClick = onDismiss),
        ) {
            Icon(
                painter = painterResource(R.drawable.ic_ui_close),
                contentDescription = stringResource(R.string.app_update_dismiss),
                tint = colors.textTertiary,
                modifier = Modifier.size(18.dp),
            )
        }
    }
}

@Preview
@Composable
private fun UpdateRequiredScreenPreview() {
    AikuDroidTheme {
        UpdateRequiredScreen(AppUpdate.Required(latestVersionName = "0.2.0", updateUrl = null))
    }
}

@Preview
@Composable
private fun AppUpdateBannerPreview() {
    AikuDroidTheme {
        AppUpdateBanner(AppUpdate.Available(latestVersionCode = 2, latestVersionName = "0.2.0", updateUrl = "https://example.com"), onDismiss = {})
    }
}
