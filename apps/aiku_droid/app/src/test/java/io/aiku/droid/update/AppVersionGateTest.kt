package io.aiku.droid.update

import io.aiku.droid.data.api.AppVersionApi
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PlatformVersion
import io.aiku.droid.data.settings.MemoryPreferences
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.cancel
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

private const val INSTALLED_VERSION_CODE = 3
private const val MINUTE = 60_000L

private class FakeAppVersionApi : AppVersionApi {
    var result: GoodsOutResult<PlatformVersion> = GoodsOutResult.Unreachable
    var calls = 0

    override suspend fun androidVersion(): GoodsOutResult<PlatformVersion> {
        calls++
        return result
    }
}

@OptIn(ExperimentalCoroutinesApi::class)
class AppVersionGateTest {

    private val scope = CoroutineScope(UnconfinedTestDispatcher())
    private val api = FakeAppVersionApi()
    private val preferences = MemoryPreferences()
    private var now = 0L

    @After
    fun stopScope() {
        scope.cancel()
    }

    private fun gate() = AppVersionGate(api, INSTALLED_VERSION_CODE, preferences, scope, clock = { now })

    private fun answer(minimum: Int, latest: Int, name: String = "0.$latest.0", url: String? = null) {
        api.result = GoodsOutResult.Loaded(PlatformVersion(minimumVersionCode = minimum, latestVersionCode = latest, latestVersionName = name, updateUrl = url))
    }

    @Test
    fun `an installed version below the minimum requires an update with the latest name and link`() {
        answer(minimum = 4, latest = 5, url = "https://example.test/update")
        val gate = gate()

        gate.check()

        assertEquals(AppUpdate.Required(latestVersionName = "0.5.0", updateUrl = "https://example.test/update"), gate.update.value)
        assertNull(gate.banner.value)
    }

    @Test
    fun `a required update without a link still blocks the app`() {
        answer(minimum = 4, latest = 4)
        val gate = gate()

        gate.check()

        assertEquals(AppUpdate.Required(latestVersionName = "0.4.0", updateUrl = null), gate.update.value)
    }

    @Test
    fun `a newer optional version shows the banner`() {
        answer(minimum = 1, latest = 4)
        val gate = gate()

        gate.check()

        val available = AppUpdate.Available(latestVersionCode = 4, latestVersionName = "0.4.0", updateUrl = null)
        assertEquals(available, gate.update.value)
        assertEquals(available, gate.banner.value)
    }

    @Test
    fun `a dismissed banner stays hidden for that version, also after a restart, and returns for the next one`() {
        answer(minimum = 1, latest = 4)
        val gate = gate()
        gate.check()

        gate.dismissBanner()

        assertNull(gate.banner.value)
        val restarted = gate()
        restarted.check()
        assertNull(restarted.banner.value)

        answer(minimum = 1, latest = 5)
        val next = gate()
        next.check()
        assertEquals(5, next.banner.value?.latestVersionCode)
    }

    @Test
    fun `the installed version being the latest needs nothing`() {
        answer(minimum = 1, latest = INSTALLED_VERSION_CODE)
        val gate = gate()

        gate.check()

        assertEquals(AppUpdate.None, gate.update.value)
        assertNull(gate.banner.value)
    }

    @Test
    fun `a network failure or a server error never blocks the app`() {
        val gate = gate()

        gate.check()
        assertEquals(AppUpdate.None, gate.update.value)

        api.result = GoodsOutResult.ServerError(500)
        gate.check()
        assertEquals(AppUpdate.None, gate.update.value)
        assertNull(gate.banner.value)
    }

    @Test
    fun `a failed check keeps the last answer`() {
        answer(minimum = 4, latest = 4)
        val gate = gate()
        gate.check()

        api.result = GoodsOutResult.Unreachable
        now += 11 * MINUTE
        gate.check()

        assertEquals(AppUpdate.Required(latestVersionName = "0.4.0", updateUrl = null), gate.update.value)
    }

    @Test
    fun `the server is asked at most every ten minutes after an answer`() {
        answer(minimum = 1, latest = 1)
        val gate = gate()

        gate.check()
        now += 9 * MINUTE
        gate.check()
        assertEquals(1, api.calls)

        now += 1 * MINUTE
        gate.check()
        assertEquals(2, api.calls)
    }

    @Test
    fun `after a network failure the next foreground asks again`() {
        val gate = gate()

        gate.check()
        now += 1 * MINUTE
        answer(minimum = 4, latest = 4)
        gate.check()

        assertEquals(2, api.calls)
        assertEquals(AppUpdate.Required(latestVersionName = "0.4.0", updateUrl = null), gate.update.value)
    }
}
