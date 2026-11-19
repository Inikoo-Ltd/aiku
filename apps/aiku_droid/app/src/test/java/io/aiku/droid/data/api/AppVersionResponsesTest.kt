package io.aiku.droid.data.api

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class AppVersionResponsesTest {

    @Test
    fun `the android block is read and the ios block ignored`() {
        val body = """
            {"data": {"android": {"minimum_version_code": 3, "latest_version_code": 5, "latest_version_name": "0.5.0", "update_url": "https://appdistribution.firebase.dev/i/abc"},
                      "ios": {"minimum_version_code": 9, "latest_version_code": 9, "latest_version_name": "9.0.0", "update_url": null}}}
        """.trimIndent()

        assertEquals(
            GoodsOutResult.Loaded(PlatformVersion(minimumVersionCode = 3, latestVersionCode = 5, latestVersionName = "0.5.0", updateUrl = "https://appdistribution.firebase.dev/i/abc")),
            AppVersionResponses.parseAndroid(200, body),
        )
    }

    @Test
    fun `nulls, strings and blank links fall back to safe values`() {
        val body = """{"data": {"android": {"minimum_version_code": "2", "latest_version_code": null, "latest_version_name": null, "update_url": "  ", "extra": true}}}"""

        assertEquals(
            GoodsOutResult.Loaded(PlatformVersion(minimumVersionCode = 2, latestVersionCode = 2, latestVersionName = "", updateUrl = null)),
            AppVersionResponses.parseAndroid(200, body),
        )
    }

    @Test
    fun `missing codes default to the first version`() {
        val parsed = AppVersionResponses.parseAndroid(200, """{"data": {"android": {}}}""")

        assertEquals(GoodsOutResult.Loaded(PlatformVersion(minimumVersionCode = 1, latestVersionCode = 1, latestVersionName = "", updateUrl = null)), parsed)
    }

    @Test
    fun `a latest version below the minimum is raised to the minimum`() {
        val parsed = AppVersionResponses.parseAndroid(200, """{"data": {"android": {"minimum_version_code": 4, "latest_version_code": 2, "latest_version_name": "0.4.0"}}}""")

        assertEquals(4, (parsed as GoodsOutResult.Loaded).value.latestVersionCode)
    }

    @Test
    fun `a body without the android block or with broken JSON is a server error`() {
        assertEquals(GoodsOutResult.ServerError(200), AppVersionResponses.parseAndroid(200, """{"data": {"ios": {}}}"""))
        assertEquals(GoodsOutResult.ServerError(200), AppVersionResponses.parseAndroid(200, "<html>"))
    }

    @Test
    fun `error statuses are passed on as failures`() {
        assertTrue(AppVersionResponses.parseAndroid(404, "{}") is GoodsOutResult.NotFound)
        assertEquals(GoodsOutResult.ServerError(500), AppVersionResponses.parseAndroid(500, ""))
    }
}
