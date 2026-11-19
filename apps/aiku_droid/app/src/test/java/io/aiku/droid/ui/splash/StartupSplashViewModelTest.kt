package io.aiku.droid.ui.splash

import io.aiku.droid.data.api.OrganisationSummary
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.UiMessage
import io.aiku.droid.ui.app.WarehousesUiState
import io.aiku.droid.update.AppUpdate
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class StartupSplashViewModelTest {

    private val dispatcher = StandardTestDispatcher()
    private val session = StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana")
    private val main = WarehouseAccess(
        id = 1,
        slug = "wh1",
        code = "WH1",
        name = "Main Warehouse",
        organisation = OrganisationSummary(id = 3, slug = "aw", name = "AW"),
        can = WarehouseAbilities(viewGoodsOut = true),
    )

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `without a remembered session the splash leads to sign-in`() {
        assertEquals(StartupDestination.SignIn, startupDestination(AppUpdate.None, session = null, warehouses = null))
    }

    @Test
    fun `a remembered session keeps the splash while the warehouses load`() {
        assertEquals(StartupDestination.Undecided, startupDestination(AppUpdate.None, session, warehouses = null))
        assertEquals(StartupDestination.Undecided, startupDestination(AppUpdate.None, session, WarehousesUiState.Loading))
    }

    @Test
    fun `a remembered session leads to Home once the warehouses are known`() {
        assertEquals(StartupDestination.SignedIn, startupDestination(AppUpdate.None, session, WarehousesUiState.Ready(listOf(main), main)))
        assertEquals(StartupDestination.SignedIn, startupDestination(AppUpdate.None, session, WarehousesUiState.NoWarehouses))
    }

    @Test
    fun `a failed warehouse load leaves the splash for its own screen`() {
        listOf(
            WarehousesUiState.Offline,
            WarehousesUiState.ServerError(500),
            WarehousesUiState.SessionExpired,
            WarehousesUiState.Failed(UiMessage.FromServer("App key rejected")),
        ).forEach { failed ->
            assertEquals(StartupDestination.SignedIn, startupDestination(AppUpdate.None, session, failed))
        }
    }

    @Test
    fun `a required update leaves the splash for the update screen whatever is still loading`() {
        val required = AppUpdate.Required(latestVersionName = "0.2.0", updateUrl = null)

        assertEquals(StartupDestination.UpdateRequired, startupDestination(required, session, WarehousesUiState.Loading))
        assertEquals(StartupDestination.UpdateRequired, startupDestination(required, session = null, warehouses = null))
    }

    @Test
    fun `an available update does not hold the splash`() {
        val available = AppUpdate.Available(latestVersionCode = 9, latestVersionName = "0.2.0", updateUrl = null)

        assertEquals(StartupDestination.SignIn, startupDestination(available, session = null, warehouses = null))
    }

    @Test
    fun `a quick start-up keeps the splash for the minimum time`() {
        val splash = StartupSplashViewModel(isFreshLaunch = true)
        splash.onDestination(StartupDestination.SignIn)

        elapse(StartupSplashViewModel.MINIMUM_VISIBLE_MILLIS - 1)
        assertTrue(splash.state.value.isVisible)

        elapse(1)
        assertFalse(splash.state.value.isVisible)
    }

    @Test
    fun `a slow start-up leaves the splash as soon as the destination is known`() {
        val splash = StartupSplashViewModel(isFreshLaunch = true)
        splash.onDestination(StartupDestination.Undecided)

        elapse(3_000)
        assertTrue(splash.state.value.isVisible)

        splash.onDestination(StartupDestination.SignedIn)
        assertFalse(splash.state.value.isVisible)
    }

    @Test
    fun `an undecided destination never ends the splash`() {
        val splash = StartupSplashViewModel(isFreshLaunch = true)

        elapse(StartupSplashViewModel.MINIMUM_VISIBLE_MILLIS)
        splash.onDestination(StartupDestination.Undecided)

        assertTrue(splash.state.value.isVisible)
    }

    @Test
    fun `the progress shows only when start-up takes longer than the progress delay`() {
        val splash = StartupSplashViewModel(isFreshLaunch = true)

        elapse(StartupSplashViewModel.PROGRESS_DELAY_MILLIS - 1)
        assertFalse(splash.state.value.showsProgress)

        elapse(1)
        assertTrue(splash.state.value.showsProgress)
        assertTrue(splash.state.value.isVisible)
    }

    @Test
    fun `a start-up that settles in time never shows the progress`() {
        val splash = StartupSplashViewModel(isFreshLaunch = true)
        splash.onDestination(StartupDestination.SignedIn)

        elapse(StartupSplashViewModel.PROGRESS_DELAY_MILLIS * 2)

        assertFalse(splash.state.value.isVisible)
        assertFalse(splash.state.value.showsProgress)
    }

    @Test
    fun `returning to the app shows no splash`() {
        val splash = StartupSplashViewModel(isFreshLaunch = false)
        assertFalse(splash.state.value.isVisible)

        splash.onDestination(StartupDestination.Undecided)
        elapse(StartupSplashViewModel.PROGRESS_DELAY_MILLIS * 2)

        assertEquals(StartupSplashState(isVisible = false, showsProgress = false), splash.state.value)
    }

    private fun elapse(millis: Long) {
        dispatcher.scheduler.advanceTimeBy(millis)
        dispatcher.scheduler.runCurrent()
    }
}
