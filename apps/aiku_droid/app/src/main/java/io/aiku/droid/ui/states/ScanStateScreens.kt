package io.aiku.droid.ui.states

import android.content.Intent
import android.net.Uri
import android.provider.Settings
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.warehouseKicker
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.ScanToPickRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun NotFoundScreen(barcode: String, onScanAgain: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    var isReportOpen by remember { mutableStateOf(false) }

    StatesFullScreen(
        modifier = modifier,
        header = {
            AppHeader(
                title = stringResource(R.string.states_scan_header),
                kicker = stringResource(R.string.states_not_found_kicker),
            )
        },
        actions = {
            StatesButton(text = stringResource(R.string.states_scan_again), onClick = onScanAgain, icon = R.drawable.ic_scan)
            StatesButton(
                text = stringResource(R.string.states_type_code),
                onClick = { navigator.open(SearchRoute) },
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_scan_keyboard,
            )
            StatesTextLink(
                text = stringResource(R.string.states_report_unknown_barcode),
                onClick = { isReportOpen = true },
                modifier = Modifier.align(Alignment.CenterHorizontally),
            )
        },
    ) {
        StatesHeroIcon(icon = R.drawable.ic_feedback_search, container = colors.dangerSoft, tint = colors.danger)
        StatesTitle(text = stringResource(R.string.states_not_found_title))
        StatesBody(text = emphasise(stringResource(R.string.states_not_found_body, barcode), barcode))
        Text(
            text = stringResource(R.string.states_not_found_hint),
            fontSize = 13.sp,
            fontWeight = FontWeight.Normal,
            color = colors.textTertiary,
            textAlign = TextAlign.Center,
        )
    }
    if (isReportOpen) {
        ReportProblemSheet(onDismiss = { isReportOpen = false }, initialCategory = ReportProblemCategory.UnknownBarcode)
    }
}

@Composable
fun CameraBlockedScreen(modifier: Modifier = Modifier, kicker: String? = warehouseKicker()) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val context = LocalContext.current

    StatesFullScreen(
        modifier = modifier,
        header = {
            AppHeader(title = stringResource(R.string.states_scan_header), kicker = kicker)
        },
        actions = {
            StatesButton(
                text = stringResource(R.string.states_camera_open_settings),
                onClick = {
                    context.startActivity(
                        Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", context.packageName, null)),
                    )
                },
            )
            StatesButton(
                text = stringResource(R.string.states_camera_use_scanner),
                onClick = { navigator.open(ScanToPickRoute(reference = STATES_CAMERA_PICK_SAMPLE)) },
                style = ButtonStyle.Soft,
                icon = R.drawable.ic_scan_device,
            )
            StatesButton(
                text = stringResource(R.string.states_type_code),
                onClick = { navigator.open(SearchRoute) },
                style = ButtonStyle.Outline,
                icon = R.drawable.ic_scan_keyboard,
            )
        },
    ) {
        StatesHeroIcon(icon = R.drawable.ic_scan_camera, container = colors.surfaceMuted, tint = colors.textTertiary)
        StatesTitle(text = stringResource(R.string.states_camera_title))
        StatesBody(text = AnnotatedString(stringResource(R.string.states_camera_body)))
    }
}

@Preview(showSystemUi = true)
@Composable
private fun NotFoundScreenPreview() {
    AikuDroidTheme {
        NotFoundScreen(barcode = STATES_UNKNOWN_BARCODE_SAMPLE, onScanAgain = {})
    }
}

@Preview(showSystemUi = true)
@Composable
private fun CameraBlockedScreenPreview() {
    AikuDroidTheme {
        CameraBlockedScreen(kicker = STATES_CAMERA_CONTEXT_SAMPLE)
    }
}
