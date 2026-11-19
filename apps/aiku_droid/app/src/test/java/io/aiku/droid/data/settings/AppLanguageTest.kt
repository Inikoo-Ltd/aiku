package io.aiku.droid.data.settings

import io.aiku.droid.R
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class AppLanguageTest {

    @Test
    fun `the app offers exactly the 23 Aiku locales with English first`() {
        val aikuLocales = listOf(
            "en", "bg", "cs", "de", "es", "fr", "hi", "hr", "hu", "id", "it", "ja",
            "lv", "ne", "nl", "pl", "pt", "ro", "sk", "sv", "tr", "uk", "zh-Hans",
        )

        assertEquals(aikuLocales, AppLanguage.entries.map { it.tag })
        AppLanguage.entries.forEach { language -> assertEquals(language, AppLanguage.ofTag(language.tag)) }
    }

    @Test
    fun `phone locales with a region or a legacy code match their Aiku language`() {
        assertEquals(AppLanguage.Slovak, AppLanguage.ofTag("sk-SK"))
        assertEquals(AppLanguage.Slovak, AppLanguage.ofTag("SK"))
        assertEquals(AppLanguage.Portuguese, AppLanguage.ofTag("pt-BR"))
        assertEquals(AppLanguage.Spanish, AppLanguage.ofTag("es_419"))
        assertEquals(AppLanguage.Indonesian, AppLanguage.ofTag("in"))
        assertEquals(AppLanguage.Indonesian, AppLanguage.ofTag("id-ID"))
        assertEquals(AppLanguage.English, AppLanguage.ofTag("en-GB"))
    }

    @Test
    fun `only simplified Chinese matches zh-Hans`() {
        assertEquals(AppLanguage.ChineseSimplified, AppLanguage.ofTag("zh-Hans-CN"))
        assertEquals(AppLanguage.ChineseSimplified, AppLanguage.ofTag("zh-CN"))
        assertEquals(AppLanguage.ChineseSimplified, AppLanguage.ofTag("zh"))
        assertNull(AppLanguage.ofTag("zh-TW"))
        assertNull(AppLanguage.ofTag("zh-Hant-HK"))
    }

    @Test
    fun `languages Aiku does not offer and blank tags match nothing`() {
        assertNull(AppLanguage.ofTag("th-TH"))
        assertNull(AppLanguage.ofTag("nb-NO"))
        assertNull(AppLanguage.ofTag(""))
        assertNull(AppLanguage.ofTag(null))
    }

    @Test
    fun `the default is the first phone language Aiku offers, else English`() {
        assertEquals(AppLanguage.Slovak, AppLanguage.defaultFor(listOf("sk-SK", "en-GB")))
        assertEquals(AppLanguage.German, AppLanguage.defaultFor(listOf("th-TH", "de-AT")))
        assertEquals(AppLanguage.English, AppLanguage.defaultFor(listOf("th-TH")))
        assertEquals(AppLanguage.English, AppLanguage.defaultFor(emptyList()))
    }

    @Test
    fun `each language is listed by its own name`() {
        assertEquals(R.string.app_language_sk_native, AppLanguage.Slovak.nativeName)
        assertEquals(R.string.app_language_es_native, AppLanguage.Spanish.nativeName)
        assertEquals(R.string.app_language_zh_hans_native, AppLanguage.ChineseSimplified.nativeName)
    }
}
