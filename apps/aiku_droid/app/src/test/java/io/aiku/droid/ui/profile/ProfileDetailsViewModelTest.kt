package io.aiku.droid.ui.profile

import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.PasswordChange
import io.aiku.droid.data.api.PasswordReset
import io.aiku.droid.data.api.PasswordResult
import io.aiku.droid.data.api.ProfileResult
import io.aiku.droid.data.api.StaffProfile
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class ProfileDetailsViewModelTest {

    private val api = FakeProfileApi()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `pulling to refresh loads the profile again and keeps the old one until it arrives`() {
        api.profile = StaffProfile(id = 42, username = "stock", position = "Picker")
        val details = ProfileDetailsViewModel(api, FakeSessions())
        assertFalse(details.isRefreshing.value)
        val gate = CompletableDeferred<Unit>()
        api.gate = gate
        api.profile = StaffProfile(id = 42, username = "stock", position = "Team leader")

        details.refresh(isPull = true)

        assertTrue(details.isRefreshing.value)
        assertEquals("Picker", details.profile.value?.position)

        gate.complete(Unit)

        assertFalse(details.isRefreshing.value)
        assertEquals("Team leader", details.profile.value?.position)
        assertEquals(2, api.requests)
    }

    private class FakeProfileApi : AuthApi {
        var profile: StaffProfile? = null
        var gate: CompletableDeferred<Unit>? = null
        var requests = 0

        override suspend fun login(credentials: LoginCredentials): LoginResult = LoginResult.Unreachable

        override suspend fun logout(token: String) = Unit

        override suspend fun requestPasswordResetCode(login: String): PasswordResult = PasswordResult.Done

        override suspend fun resetPassword(reset: PasswordReset): PasswordResult = PasswordResult.Done

        override suspend fun changePassword(token: String, change: PasswordChange): PasswordResult = PasswordResult.Done

        override suspend fun profile(token: String): ProfileResult {
            requests++
            gate?.await()
            return profile?.let { ProfileResult.Loaded(it) } ?: ProfileResult.Unavailable
        }
    }
}
