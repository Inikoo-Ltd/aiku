package io.aiku.droid.data.settings

import androidx.annotation.StringRes
import io.aiku.droid.R
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import java.util.Locale

enum class AppLanguage(val tag: String, @StringRes val nativeName: Int, @StringRes val label: Int) {
    English("en", R.string.app_language_en_native, R.string.app_language_en),
    Bulgarian("bg", R.string.app_language_bg_native, R.string.app_language_bg),
    Czech("cs", R.string.app_language_cs_native, R.string.app_language_cs),
    German("de", R.string.app_language_de_native, R.string.app_language_de),
    Spanish("es", R.string.app_language_es_native, R.string.app_language_es),
    French("fr", R.string.app_language_fr_native, R.string.app_language_fr),
    Hindi("hi", R.string.app_language_hi_native, R.string.app_language_hi),
    Croatian("hr", R.string.app_language_hr_native, R.string.app_language_hr),
    Hungarian("hu", R.string.app_language_hu_native, R.string.app_language_hu),
    Indonesian("id", R.string.app_language_id_native, R.string.app_language_id),
    Italian("it", R.string.app_language_it_native, R.string.app_language_it),
    Japanese("ja", R.string.app_language_ja_native, R.string.app_language_ja),
    Latvian("lv", R.string.app_language_lv_native, R.string.app_language_lv),
    Nepali("ne", R.string.app_language_ne_native, R.string.app_language_ne),
    Dutch("nl", R.string.app_language_nl_native, R.string.app_language_nl),
    Polish("pl", R.string.app_language_pl_native, R.string.app_language_pl),
    Portuguese("pt", R.string.app_language_pt_native, R.string.app_language_pt),
    Romanian("ro", R.string.app_language_ro_native, R.string.app_language_ro),
    Slovak("sk", R.string.app_language_sk_native, R.string.app_language_sk),
    Swedish("sv", R.string.app_language_sv_native, R.string.app_language_sv),
    Turkish("tr", R.string.app_language_tr_native, R.string.app_language_tr),
    Ukrainian("uk", R.string.app_language_uk_native, R.string.app_language_uk),
    ChineseSimplified("zh-Hans", R.string.app_language_zh_hans_native, R.string.app_language_zh_hans),
    ;

    companion object {
        private const val CHINESE = "zh"
        private const val SIMPLIFIED_SCRIPT = "Hans"
        private val SimplifiedChineseRegions = setOf("", "CN", "SG", "MY")

        fun ofTag(tag: String?): AppLanguage? {
            val locale = tag?.trim()?.takeIf { it.isNotEmpty() }?.let { Locale.forLanguageTag(it.replace('_', '-')) } ?: return null
            val language = locale.toLanguageTag().substringBefore('-').lowercase(Locale.ROOT)
            if (language == CHINESE) {
                val isSimplified = locale.script.equals(SIMPLIFIED_SCRIPT, ignoreCase = true) ||
                    (locale.script.isEmpty() && locale.country.uppercase(Locale.ROOT) in SimplifiedChineseRegions)
                return ChineseSimplified.takeIf { isSimplified }
            }
            return entries.firstOrNull { it.tag == language }
        }

        fun defaultFor(phoneTags: List<String>): AppLanguage = phoneTags.firstNotNullOfOrNull(::ofTag) ?: English

        fun ofAccountCode(code: String?): AppLanguage? = entries.firstOrNull { it.tag == code }
    }
}

interface AppLocaleStore {
    val chosenTag: String?
    val phoneTags: List<String>
    val isAppliedBySystem: Boolean
    fun store(tag: String)
}

class AppLanguageSettings(private val store: AppLocaleStore) {

    private val mutableCurrent = MutableStateFlow(resolve())

    val current: StateFlow<AppLanguage> = mutableCurrent.asStateFlow()

    val acceptLanguage: String get() = mutableCurrent.value.tag

    fun choose(language: AppLanguage): Boolean {
        val isChange = language != mutableCurrent.value
        if (!isChange && AppLanguage.ofTag(store.chosenTag) == language) {
            return false
        }
        store.store(language.tag)
        mutableCurrent.value = language
        return isChange && !store.isAppliedBySystem
    }

    fun refresh() {
        mutableCurrent.value = resolve()
    }

    fun mustRecreate(shown: AppLanguage): Boolean = !store.isAppliedBySystem && mutableCurrent.value != shown

    private fun resolve(): AppLanguage = AppLanguage.ofTag(store.chosenTag) ?: AppLanguage.defaultFor(store.phoneTags)
}
