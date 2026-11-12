package io.aiku.droid

import android.app.Application
import io.sentry.Sentry
import io.sentry.android.core.SentryAndroid
import io.sentry.protocol.User
import kotlinx.coroutines.MainScope
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch

class AikuDroidApplication : Application() {

    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        val isReportingCrashes = startCrashReporting()
        container = AppContainer(this)
        if (isReportingCrashes) {
            reportSignedInUser()
        }
    }

    private fun startCrashReporting(): Boolean {
        if (BuildConfig.SENTRY_DSN.isBlank()) {
            return false
        }
        SentryAndroid.init(this) { options ->
            options.dsn = BuildConfig.SENTRY_DSN
            options.environment = BuildConfig.BUILD_TYPE
            options.isAttachScreenshot = false
            options.isAttachViewHierarchy = false
        }
        return true
    }

    private fun reportSignedInUser() {
        MainScope().launch {
            container.sessions.session
                .map { it?.userId }
                .distinctUntilChanged()
                .collect { userId -> Sentry.setUser(userId?.let { id -> User().apply { this.id = id.toString() } }) }
        }
    }
}
