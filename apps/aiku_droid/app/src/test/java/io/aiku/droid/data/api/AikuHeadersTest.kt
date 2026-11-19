package io.aiku.droid.data.api

import io.aiku.droid.data.settings.AppLanguage
import io.aiku.droid.data.settings.AppLanguageSettings
import io.aiku.droid.data.settings.MemoryPreferences
import io.aiku.droid.data.settings.PreferencesLocaleStore
import okhttp3.Request
import org.junit.Assert.assertEquals
import org.junit.Test

class AikuHeadersTest {

    private val request = Request.Builder().url("https://app.aiku.test/app/api/mobile/profile").build()

    @Test
    fun `every request carries the app key, JSON and the chosen language`() {
        val sent = request.withAikuHeaders(appKey = "key-1", acceptLanguage = "zh-Hans")

        assertEquals("key-1", sent.header("X-Aiku-App-Key"))
        assertEquals("application/json", sent.header("Accept"))
        assertEquals("zh-Hans", sent.header("Accept-Language"))
    }

    @Test
    fun `the Accept-Language header follows the language chosen in the app`() {
        val settings = AppLanguageSettings(PreferencesLocaleStore(MemoryPreferences()) { listOf("fr-FR") })

        assertEquals("fr", request.withAikuHeaders("key-1", settings.acceptLanguage).header("Accept-Language"))

        settings.choose(AppLanguage.Slovak)

        assertEquals("sk", request.withAikuHeaders("key-1", settings.acceptLanguage).header("Accept-Language"))
    }

    @Test
    fun `a language header set earlier is replaced, not repeated`() {
        val sent = request.newBuilder().header("Accept-Language", "en").build().withAikuHeaders("key-1", "sk")

        assertEquals(listOf("sk"), sent.headers("Accept-Language"))
    }
}
