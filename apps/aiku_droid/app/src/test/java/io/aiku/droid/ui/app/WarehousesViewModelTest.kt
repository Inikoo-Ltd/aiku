package io.aiku.droid.ui.app

import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.OrganisationSummary
import io.aiku.droid.data.api.PasswordResetResult
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.api.WarehouseApi
import io.aiku.droid.data.api.WarehousesResult
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.data.settings.WarehouseSelection
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class WarehousesViewModelTest {

    private val main = warehouse(1, "Main Warehouse")
    private val second = warehouse(2, "Second Warehouse")
    private val sessions = InMemorySessions(StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana"))
    private val selection = InMemorySelection()

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    @Test
    fun `the first warehouse is chosen and remembered when none was chosen before`() {
        val viewModel = viewModelReturning(WarehousesResult.Loaded(listOf(main, second)))

        assertEquals(WarehousesUiState.Ready(listOf(main, second), main), viewModel.state.value)
        assertEquals(1L, selection.selectedWarehouseId)
    }

    @Test
    fun `the remembered warehouse is chosen again and switching remembers the new one`() {
        selection.selectedWarehouseId = 2
        val viewModel = viewModelReturning(WarehousesResult.Loaded(listOf(main, second)))

        assertEquals(second, (viewModel.state.value as WarehousesUiState.Ready).selected)

        viewModel.select(1)

        assertEquals(main, (viewModel.state.value as WarehousesUiState.Ready).selected)
        assertEquals(1L, selection.selectedWarehouseId)
    }

    @Test
    fun `a user without any warehouse role gets the no access state`() {
        val viewModel = viewModelReturning(WarehousesResult.Loaded(emptyList()))

        assertEquals(WarehousesUiState.NoWarehouses, viewModel.state.value)
    }

    @Test
    fun `an expired token asks the user to sign in again before signing them out`() {
        val viewModel = viewModelReturning(WarehousesResult.SessionExpired)

        assertEquals(WarehousesUiState.SessionExpired, viewModel.state.value)
        assertNotNull(sessions.session.value)

        viewModel.signInAgain()

        assertNull(sessions.session.value)
    }

    @Test
    fun `signing in again after an expired session loads the warehouses of the new login`() {
        val api = FakeWarehouseApi(WarehousesResult.SessionExpired)
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection)

        viewModel.signInAgain()
        assertEquals(WarehousesUiState.Loading, viewModel.state.value)

        api.result = WarehousesResult.Loaded(listOf(warehouse(7, "Main")))
        sessions.start(StaffSession(token = "8|new", userId = 42, username = "picker", displayName = "Ana"), rememberOnDevice = true)

        assertEquals("Main", (viewModel.state.value as WarehousesUiState.Ready).selected.name)
    }

    @Test
    fun `no connection gets the offline state and a server failure keeps its status code`() {
        assertEquals(WarehousesUiState.Offline, viewModelReturning(WarehousesResult.Unreachable).state.value)
        assertEquals(WarehousesUiState.ServerError(502), viewModelReturning(WarehousesResult.ServerError(502)).state.value)
    }

    private fun viewModelReturning(result: WarehousesResult) =
        WarehousesViewModel(FakeWarehouseApi(result), FakeAuthApi(), sessions, selection)

    private fun warehouse(id: Long, name: String) = WarehouseAccess(
        id = id,
        slug = "wh$id",
        code = "WH$id",
        name = name,
        organisation = OrganisationSummary(id = 3, slug = "aw", name = "AW"),
        can = WarehouseAbilities(viewGoodsOut = true, pickAndPack = true),
    )

    private class FakeWarehouseApi(var result: WarehousesResult) : WarehouseApi {
        override suspend fun warehouses(token: String) = result
    }

    private class FakeAuthApi : AuthApi {
        override suspend fun login(credentials: LoginCredentials): LoginResult = LoginResult.Unreachable
        override suspend fun logout(token: String) = Unit
        override suspend fun sendPasswordResetLink(email: String): PasswordResetResult = PasswordResetResult.Unreachable
    }

    private class InMemorySessions(initial: StaffSession) : SessionRepository {
        private val current = MutableStateFlow<StaffSession?>(initial)
        override val session: StateFlow<StaffSession?> = current.asStateFlow()
        override fun start(session: StaffSession, rememberOnDevice: Boolean) {
            current.value = session
        }
        override fun end() {
            current.value = null
        }
    }

    private class InMemorySelection : WarehouseSelection {
        override var selectedWarehouseId: Long? = null
    }
}
