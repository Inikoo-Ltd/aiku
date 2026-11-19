package io.aiku.droid.data.settings

import android.content.SharedPreferences
import androidx.core.content.edit
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LanguageUpdateResult
import io.aiku.droid.data.api.ProfileResult
import io.aiku.droid.data.api.ProfileSource
import io.aiku.droid.data.api.StaffProfile
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancelChildren
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

interface PendingLanguageStore {
    var pendingTag: String?
}

class PreferencesPendingLanguage(private val preferences: SharedPreferences) : PendingLanguageStore {

    override var pendingTag: String?
        get() = preferences.getString(PENDING_LANGUAGE_KEY, null)
        set(value) = preferences.edit(commit = true) {
            if (value == null) remove(PENDING_LANGUAGE_KEY) else putString(PENDING_LANGUAGE_KEY, value)
        }

    private companion object {
        const val PENDING_LANGUAGE_KEY = "pending_account_language"
    }
}

class AccountLanguageSync(
    private val settings: AppLanguageSettings,
    private val api: AuthApi,
    private val sessions: SessionRepository,
    private val pending: PendingLanguageStore,
    private val scope: CoroutineScope,
) : ProfileSource {

    private val work = SupervisorJob(scope.coroutineContext[Job])
    private val sendLock = Mutex()
    private var sending: Job? = null
    private var loading: Job? = null
    private var localChoices = 0

    fun start() {
        val restoredToken = sessions.session.value?.token
        scope.launch {
            sessions.session
                .map { it?.token }
                .distinctUntilChanged()
                .collect { token ->
                    when (token) {
                        null -> forgetPending()
                        restoredToken -> Unit
                        else -> sync(token)
                    }
                }
        }
    }

    fun onForeground() {
        val token = sessions.session.value?.token ?: return
        sync(token)
    }

    fun choose(language: AppLanguage) {
        settings.choose(language)
        localChoices++
        val token = sessions.session.value?.token ?: return
        pending.pendingTag = language.tag
        send(token, language.tag)
    }

    override suspend fun profile(token: String): ProfileResult {
        val choicesBeforeLoad = localChoices
        val result = api.profile(token)
        if (result is ProfileResult.Loaded && choicesBeforeLoad == localChoices) {
            follow(token, result.profile)
        }
        return result
    }

    private fun sync(token: String) {
        if (sending?.isActive == true || loading?.isActive == true) {
            return
        }
        val waiting = pending.pendingTag
        if (waiting != null) {
            send(token, waiting)
        } else {
            loading = scope.launch(work) { profile(token) }
        }
    }

    private fun follow(token: String, profile: StaffProfile) {
        val waiting = pending.pendingTag
        if (waiting != null) {
            if (sending?.isActive != true) {
                send(token, waiting)
            }
            return
        }
        val accountLanguage = AppLanguage.ofAccountCode(profile.language?.code) ?: return
        if (accountLanguage != settings.current.value) {
            settings.choose(accountLanguage)
        }
    }

    private fun send(token: String, tag: String) {
        sending = scope.launch(work) { sendLock.withLock { deliver(token, tag) } }
    }

    private suspend fun deliver(token: String, tag: String) {
        if (pending.pendingTag != tag) {
            return
        }
        when (api.updateLanguage(token, tag)) {
            LanguageUpdateResult.Saved -> settle(tag)
            LanguageUpdateResult.Rejected -> if (settle(tag)) {
                profile(token)
            }
            LanguageUpdateResult.Unreachable, is LanguageUpdateResult.ServerError -> Unit
        }
    }

    private fun settle(tag: String): Boolean {
        if (pending.pendingTag != tag) {
            return false
        }
        pending.pendingTag = null
        return true
    }

    private fun forgetPending() {
        work.cancelChildren()
        localChoices++
        pending.pendingTag = null
    }
}
