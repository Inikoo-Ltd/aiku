package io.aiku.droid.data.settings

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class AppLanguageSettingsTest {

    private class SystemStore(var phone: List<String>) : AppLocaleStore {
        override var chosenTag: String? = null
        override val phoneTags: List<String> get() = phone
        override val isAppliedBySystem: Boolean = true
        val stored = mutableListOf<String>()

        override fun store(tag: String) {
            stored += tag
            chosenTag = tag
        }
    }

    private val preferences = MemoryPreferences()
    private var phoneLanguages = listOf("de-DE")

    private fun deviceStore() = PreferencesLocaleStore(preferences) { phoneLanguages }

    @Test
    fun `without a choice the app follows the phone language`() {
        val settings = AppLanguageSettings(deviceStore())

        assertEquals(AppLanguage.German, settings.current.value)
        assertEquals("de", settings.acceptLanguage)
    }

    @Test
    fun `without a choice an unsupported phone language falls back to English`() {
        phoneLanguages = listOf("th-TH")

        assertEquals(AppLanguage.English, AppLanguageSettings(deviceStore()).current.value)
    }

    @Test
    fun `a choice is applied at once, remembered on the phone and asks for the screen to be rebuilt`() {
        val settings = AppLanguageSettings(deviceStore())

        val mustRebuild = settings.choose(AppLanguage.Slovak)

        assertTrue(mustRebuild)
        assertEquals(AppLanguage.Slovak, settings.current.value)
        assertEquals("sk", settings.acceptLanguage)
        assertEquals("sk", preferences.getString("chosen_language", null))
        assertEquals(AppLanguage.Slovak, AppLanguageSettings(deviceStore()).current.value)
    }

    @Test
    fun `a remembered choice wins over the phone language`() {
        AppLanguageSettings(deviceStore()).choose(AppLanguage.ChineseSimplified)
        phoneLanguages = listOf("es-ES")

        val settings = AppLanguageSettings(deviceStore())

        assertEquals(AppLanguage.ChineseSimplified, settings.current.value)
        assertEquals("zh-Hans", settings.acceptLanguage)
    }

    @Test
    fun `choosing the language already shown keeps the screen and remembers it`() {
        val settings = AppLanguageSettings(deviceStore())

        assertFalse(settings.choose(AppLanguage.German))
        assertEquals("de", preferences.getString("chosen_language", null))
        assertFalse(settings.choose(AppLanguage.German))
    }

    @Test
    fun `when the system applies the language the app does not rebuild the screen itself`() {
        val store = SystemStore(phone = listOf("en-GB"))
        val settings = AppLanguageSettings(store)

        assertFalse(settings.choose(AppLanguage.Polish))
        assertEquals(listOf("pl"), store.stored)
        assertEquals(AppLanguage.Polish, settings.current.value)
    }

    @Test
    fun `the screen is rebuilt by the app only when the language changed and the system does not apply it`() {
        val deviceSettings = AppLanguageSettings(deviceStore())
        val systemStore = SystemStore(phone = listOf("en-GB"))
        val systemSettings = AppLanguageSettings(systemStore)

        assertFalse(deviceSettings.mustRecreate(shown = AppLanguage.German))
        deviceSettings.choose(AppLanguage.French)
        systemSettings.choose(AppLanguage.French)

        assertTrue(deviceSettings.mustRecreate(shown = AppLanguage.German))
        assertFalse(deviceSettings.mustRecreate(shown = AppLanguage.French))
        assertFalse(systemSettings.mustRecreate(shown = AppLanguage.English))
    }

    @Test
    fun `a language changed in the system settings is picked up on refresh`() {
        val store = SystemStore(phone = listOf("en-GB"))
        val settings = AppLanguageSettings(store)
        store.chosenTag = "hu"

        settings.refresh()

        assertEquals(AppLanguage.Hungarian, settings.current.value)
        assertEquals("hu", settings.acceptLanguage)
    }

    @Test
    fun `a stored tag Aiku no longer offers falls back to the phone language`() {
        preferences.values["chosen_language"] = "xx"

        val settings = AppLanguageSettings(deviceStore())

        assertEquals(AppLanguage.German, settings.current.value)
        assertNull(AppLanguage.ofTag(deviceStore().chosenTag))
    }
}
