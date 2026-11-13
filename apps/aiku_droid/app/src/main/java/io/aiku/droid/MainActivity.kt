package io.aiku.droid

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import io.aiku.droid.ui.app.SignedInApp
import io.aiku.droid.ui.login.LoginScreen
import io.aiku.droid.ui.login.LoginViewModel
import io.aiku.droid.ui.scan.HardwareScanKeys
import io.aiku.droid.ui.scan.HardwareScanWindowCallback
import io.aiku.droid.ui.theme.AikuDroidTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        val container = (application as AikuDroidApplication).container
        window.callback = HardwareScanWindowCallback(
            delegate = window.callback,
            keys = HardwareScanKeys(container.scans),
            isTextFieldFocused = { currentFocus?.onCheckIsTextEditor() == true },
        )
        setContent {
            val appearance by container.settings.appearance.collectAsStateWithLifecycle()
            AikuDroidTheme(appearance = appearance) {
                AikuDroidApp(container)
            }
        }
    }
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
