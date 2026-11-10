package io.aiku.droid.ui.states

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.SyncQueueRoute

enum class StatePreview(val kind: String) {
    Offline("offline"),
    SessionExpired("session"),
    ServerError("server"),
    Loading("loading"),
    NoAccess("no_access"),
    NotFound("not_found"),
    CameraBlocked("camera"),
    ActionFailed("action_failed"),
}

private const val SAMPLE_SERVER_STATUS = 500

@Composable
fun StatePreviewScreen(kind: String) {
    val navigator = LocalAppNavigator.current

    when (StatePreview.entries.firstOrNull { it.kind == kind }) {
        StatePreview.Offline -> OfflineScreen(
            onRetry = navigator::back,
            onKeepWorking = navigator::back,
            onViewQueue = { navigator.open(SyncQueueRoute) },
        )
        StatePreview.SessionExpired -> SessionExpiredScreen(onSignIn = navigator::back)
        StatePreview.ServerError -> ServerErrorScreen(onRetry = navigator::back, statusCode = SAMPLE_SERVER_STATUS)
        StatePreview.Loading -> LoadingScreen()
        StatePreview.NoAccess -> NoAccessScreen(
            moduleName = stringResource(R.string.module_goods_in),
            missingPermission = Ability.ViewGoodsIn.permission,
            activeTab = MainTab.Menu,
        )
        StatePreview.NotFound -> NotFoundScreen(barcode = STATES_UNKNOWN_BARCODE_SAMPLE, onScanAgain = { navigator.open(ScanCameraRoute) })
        StatePreview.CameraBlocked -> CameraBlockedScreen(kicker = STATES_CAMERA_CONTEXT_SAMPLE)
        StatePreview.ActionFailed -> ActionFailedPreview()
        null -> NotDesignedScreen(title = kind)
    }
}
