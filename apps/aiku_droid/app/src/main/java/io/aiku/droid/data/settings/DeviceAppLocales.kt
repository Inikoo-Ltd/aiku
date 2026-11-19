package io.aiku.droid.data.settings

import android.app.LocaleManager
import android.content.Context
import android.content.SharedPreferences
import android.content.res.Configuration
import android.content.res.Resources
import android.os.Build
import android.os.LocaleList
import androidx.annotation.RequiresApi
import androidx.core.content.edit
import java.util.Locale

private const val LANGUAGE_PREFERENCES = "aiku_language"
private const val CHOSEN_LANGUAGE_KEY = "chosen_language"

class PreferencesLocaleStore(
    private val preferences: SharedPreferences,
    private val phoneLocales: () -> List<String>,
) : AppLocaleStore {

    override val chosenTag: String? get() = preferences.getString(CHOSEN_LANGUAGE_KEY, null)

    override val phoneTags: List<String> get() = phoneLocales()

    override val isAppliedBySystem: Boolean = false

    override fun store(tag: String) {
        preferences.edit(commit = true) { putString(CHOSEN_LANGUAGE_KEY, tag) }
    }
}

@RequiresApi(Build.VERSION_CODES.TIRAMISU)
private class SystemLocaleStore(private val localeManager: LocaleManager) : AppLocaleStore {

    override val chosenTag: String?
        get() = localeManager.applicationLocales.takeUnless { it.isEmpty }?.get(0)?.toLanguageTag()

    override val phoneTags: List<String> get() = localeManager.systemLocales.toTags()

    override val isAppliedBySystem: Boolean = true

    override fun store(tag: String) {
        localeManager.applicationLocales = LocaleList.forLanguageTags(tag)
    }
}

object DeviceAppLocales {

    fun store(context: Context): AppLocaleStore =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            SystemLocaleStore(context.getSystemService(LocaleManager::class.java))
        } else {
            PreferencesLocaleStore(context.getSharedPreferences(LANGUAGE_PREFERENCES, Context.MODE_PRIVATE)) {
                Resources.getSystem().configuration.locales.toTags()
            }
        }

    fun localised(base: Context): Context {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            return base
        }
        val chosen = base.getSharedPreferences(LANGUAGE_PREFERENCES, Context.MODE_PRIVATE).getString(CHOSEN_LANGUAGE_KEY, null)
        val language = AppLanguage.ofTag(chosen) ?: return base
        val locales = LocaleList(Locale.forLanguageTag(language.tag))
        Locale.setDefault(locales[0])
        LocaleList.setDefault(locales)
        val configuration = Configuration(base.resources.configuration).apply { setLocales(locales) }
        return base.createConfigurationContext(configuration)
    }
}

private fun LocaleList.toTags(): List<String> = (0 until size()).map { get(it).toLanguageTag() }
