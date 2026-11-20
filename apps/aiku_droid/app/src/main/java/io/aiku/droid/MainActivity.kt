package io.aiku.droid

import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.repeatOnLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.data.offline.SyncReason
import io.aiku.droid.data.settings.AppLanguage
import io.aiku.droid.data.settings.DeviceAppLocales
import io.aiku.droid.push.OfflineOpen
import io.aiku.droid.push.PushData
import io.aiku.droid.ui.app.SignedInApp
import io.aiku.droid.ui.app.WarehousesViewModel
import io.aiku.droid.ui.app.warehousesViewModelFactory
import io.aiku.droid.ui.login.LoginScreen
import io.aiku.droid.ui.login.LoginViewModel
import io.aiku.droid.ui.scan.HardwareScanKeys
import io.aiku.droid.ui.scan.HardwareScanWindowCallback
import io.aiku.droid.ui.splash.StartupDestination
import io.aiku.droid.ui.splash.StartupSplash
import io.aiku.droid.ui.splash.StartupSplashViewModel
import io.aiku.droid.ui.splash.startupDestination
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.update.UpdateRequiredScreen
import io.aiku.droid.update.AppUpdate
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {

    private val container: AppContainer get() = (application as AikuDroidApplication).container

    private lateinit var shownLanguage: AppLanguage

    override fun attachBaseContext(newBase: Context) {
        super.attachBaseContext(DeviceAppLocales.localised(newBase))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        container.language.refresh()
        shownLanguage = container.language.current.value
        recreateWhenLanguageChanges()
        enableEdgeToEdge()
        if (savedInstanceState == null) {
            receivePushTap(intent)
        }
        window.callback = HardwareScanWindowCallback(
            delegate = window.callback,
            keys = HardwareScanKeys(container.scans),
            isTextFieldFocused = { currentFocus?.onCheckIsTextEditor() == true },
        )
        val isFreshLaunch = savedInstanceState == null
        setContent {
            val appearance by container.settings.appearance.collectAsStateWithLifecycle()
            val update by container.appVersions.update.collectAsStateWithLifecycle()
            val splash: StartupSplashViewModel = viewModel(
                factory = viewModelFactory {
                    initializer { StartupSplashViewModel(isFreshLaunch) }
                },
            )
            val splashState by splash.state.collectAsStateWithLifecycle()
            if (splashState.isVisible) {
                val destination = currentStartupDestination(container, update)
                LaunchedEffect(destination) { splash.onDestination(destination) }
            }
            AikuDroidTheme(appearance = appearance) {
                StartupSplash(state = splashState) {
                    when (val current = update) {
                        is AppUpdate.Required -> UpdateRequiredScreen(update = current)
                        else -> AikuDroidApp(container)
                    }
                }
            }
        }
    }

    override fun onStart() {
        super.onStart()
        container.appVersions.check()
        container.accountLanguage.onForeground()
        container.offlineWork.syncNow(SyncReason.FOREGROUND)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        receivePushTap(intent)
    }

    private fun recreateWhenLanguageChanges() {
        lifecycleScope.launch {
            repeatOnLifecycle(Lifecycle.State.STARTED) {
                container.language.current.collect {
                    if (container.language.mustRecreate(shown = shownLanguage)) {
                        recreate()
                    }
                }
            }
        }
    }

    private fun receivePushTap(intent: Intent?) {
        if (intent == null || (intent.flags and Intent.FLAG_ACTIVITY_LAUNCHED_FROM_HISTORY) != 0) {
            return
        }
        val extras = intent.extras ?: return
        if (extras.getString(OfflineOpen.EXTRA) == OfflineOpen.RESOLVE_CONFLICTS) {
            container.offlineOpenRequests.request()
            intent.replaceExtras(Bundle())
            return
        }
        val pushOpen = PushData.openOf(PushData.keys.associateWith { extras.getString(it) }) ?: return
        container.pushInbox.receive(pushOpen)
        intent.replaceExtras(Bundle())
    }
}

@Composable
private fun currentStartupDestination(container: AppContainer, update: AppUpdate): StartupDestination {
    val session by container.sessions.session.collectAsStateWithLifecycle()
    val signedInSession = session
    val warehouses = if (signedInSession == null || update is AppUpdate.Required) {
        null
    } else {
        val warehousesViewModel: WarehousesViewModel = viewModel(factory = warehousesViewModelFactory(container))
        warehousesViewModel.state.collectAsStateWithLifecycle().value
    }
    return startupDestination(update = update, session = signedInSession, warehouses = warehouses)
}

@Composable
private fun AikuDroidApp(container: AppContainer) {
    val session by container.sessions.session.collectAsStateWithLifecycle()
    val signedInSession = session

    if (signedInSession == null) {
        val loginViewModel: LoginViewModel = viewModel(
            factory = viewModelFactory {
                initializer { LoginViewModel(container.authApi, container.sessions, container.deviceName) }
            },
        )
        val state by loginViewModel.state.collectAsStateWithLifecycle()
        LoginScreen(state = state, actions = loginViewModel)
    } else {
        SignedInApp(container = container, session = signedInSession)
    }
}
