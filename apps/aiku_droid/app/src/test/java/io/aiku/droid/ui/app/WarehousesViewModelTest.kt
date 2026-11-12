package io.aiku.droid.ui.app

import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.LoginCredentials
import io.aiku.droid.data.api.LoginResult
import io.aiku.droid.data.api.OrganisationSummary
import io.aiku.droid.data.api.PasswordChange
import io.aiku.droid.data.api.PasswordReset
import io.aiku.droid.data.api.PasswordResult
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.api.WarehouseApi
import io.aiku.droid.data.api.WarehousesResult
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.data.session.WarehouseCache
import io.aiku.droid.data.settings.WarehouseSelection
import kotlinx.coroutines.CompletableDeferred
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
    private val cache = InMemoryCache()

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
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection, cache)

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

    @Test
    fun `every successful load is kept in the cache for the signed in user`() {
        viewModelReturning(WarehousesResult.Loaded(listOf(main, second)))

        assertEquals(listOf(main, second), cache.read(42))
    }

    @Test
    fun `opening without a connection uses the cached warehouses of the same user`() {
        cache.save(42, listOf(second))

        val viewModel = viewModelReturning(WarehousesResult.Unreachable)

        assertEquals(WarehousesUiState.Ready(listOf(second), second), viewModel.state.value)
    }

    @Test
    fun `the cached warehouses of another user are never used`() {
        cache.save(99, listOf(second))

        val viewModel = viewModelReturning(WarehousesResult.Unreachable)

        assertEquals(WarehousesUiState.Offline, viewModel.state.value)
    }

    @Test
    fun `signing out clears the cache`() {
        val viewModel = viewModelReturning(WarehousesResult.Loaded(listOf(main)))
        assertNotNull(cache.read(42))

        viewModel.signOut()

        assertNull(sessions.session.value)
        assertNull(cache.read(42))
        assertEquals(WarehousesUiState.Loading, viewModel.state.value)
    }

    @Test
    fun `an expired session clears the cache`() {
        cache.save(42, listOf(main))

        viewModelReturning(WarehousesResult.SessionExpired)

        assertNull(cache.read(42))
    }

    @Test
    fun `returning to the foreground refreshes quietly, dropping a lost warehouse and adding a new one`() {
        val api = FakeWarehouseApi(WarehousesResult.Loaded(listOf(main, second)))
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection, cache)
        val third = warehouse(3, "Third Warehouse")
        val gate = CompletableDeferred<Unit>()
        api.gate = gate
        api.result = WarehousesResult.Loaded(listOf(main, third))

        viewModel.refresh()

        assertEquals(WarehousesUiState.Ready(listOf(main, second), main), viewModel.state.value)

        viewModel.refresh()
        assertEquals(2, api.calls)

        gate.complete(Unit)

        assertEquals(WarehousesUiState.Ready(listOf(main, third), main), viewModel.state.value)
        assertEquals(listOf(main, third), cache.read(42))
    }

    @Test
    fun `losing the chosen warehouse on a refresh falls back to the first one left`() {
        selection.selectedWarehouseId = 2
        val api = FakeWarehouseApi(WarehousesResult.Loaded(listOf(main, second)))
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection, cache)

        api.result = WarehousesResult.Loaded(listOf(main))
        viewModel.refresh()

        assertEquals(WarehousesUiState.Ready(listOf(main), main), viewModel.state.value)
        assertEquals(1L, selection.selectedWarehouseId)
    }

    @Test
    fun `a refresh that fails keeps showing the current warehouses`() {
        val api = FakeWarehouseApi(WarehousesResult.Loaded(listOf(main, second)))
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection, cache)

        api.result = WarehousesResult.Unreachable
        viewModel.refresh()
        api.result = WarehousesResult.ServerError(503)
        viewModel.refresh()

        assertEquals(WarehousesUiState.Ready(listOf(main, second), main), viewModel.state.value)
        assertEquals(3, api.calls)
    }

    @Test
    fun `a refresh that finds the session expired asks to sign in again and clears the cache`() {
        val api = FakeWarehouseApi(WarehousesResult.Loaded(listOf(main)))
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection, cache)

        api.result = WarehousesResult.SessionExpired
        viewModel.refresh()

        assertEquals(WarehousesUiState.SessionExpired, viewModel.state.value)
        assertNull(cache.read(42))
    }

    @Test
    fun `returning to the foreground from the offline screen loads again`() {
        val api = FakeWarehouseApi(WarehousesResult.Unreachable)
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection, cache)
        assertEquals(WarehousesUiState.Offline, viewModel.state.value)

        api.result = WarehousesResult.Loaded(listOf(main))
        viewModel.refresh()

        assertEquals(WarehousesUiState.Ready(listOf(main), main), viewModel.state.value)
    }

    @Test
    fun `a user who gains a warehouse on a refresh leaves the no access state`() {
        val api = FakeWarehouseApi(WarehousesResult.Loaded(emptyList()))
        val viewModel = WarehousesViewModel(api, FakeAuthApi(), sessions, selection, cache)

        api.result = WarehousesResult.Loaded(listOf(second))
        viewModel.refresh()

        assertEquals(WarehousesUiState.Ready(listOf(second), second), viewModel.state.value)
    }

    private fun viewModelReturning(result: WarehousesResult) =
        WarehousesViewModel(FakeWarehouseApi(result), FakeAuthApi(), sessions, selection, cache)

    private fun warehouse(id: Long, name: String) = WarehouseAccess(
        id = id,
        slug = "wh$id",
        code = "WH$id",
        name = name,
        organisation = OrganisationSummary(id = 3, slug = "aw", name = "AW"),
        can = WarehouseAbilities(viewGoodsOut = true, pickAndPack = true),
    )

    private class FakeWarehouseApi(var result: WarehousesResult) : WarehouseApi {
        var gate: CompletableDeferred<Unit>? = null
        var calls = 0

        override suspend fun warehouses(token: String): WarehousesResult {
            calls++
            val answer = result
            gate?.await()
            return answer
        }
    }

    private class FakeAuthApi : AuthApi {
        override suspend fun login(credentials: LoginCredentials): LoginResult = LoginResult.Unreachable
        override suspend fun logout(token: String) = Unit
        override suspend fun requestPasswordResetCode(login: String): PasswordResult = PasswordResult.Unreachable
        override suspend fun resetPassword(reset: PasswordReset): PasswordResult = PasswordResult.Unreachable
        override suspend fun changePassword(token: String, change: PasswordChange): PasswordResult = PasswordResult.Unreachable
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

    private class InMemoryCache : WarehouseCache {
        private var entry: Pair<Long, List<WarehouseAccess>>? = null

        override fun read(userId: Long): List<WarehouseAccess>? = entry?.takeIf { it.first == userId }?.second

        override fun save(userId: Long, warehouses: List<WarehouseAccess>) {
            entry = userId to warehouses
        }

        override fun clear() {
            entry = null
        }
    }
}
