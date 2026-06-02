import { trans } from "laravel-vue-i18n"

const translationsObject = {
    translations: {
        next: "Next",
        no_results_found: "No results found",
        of: "of",
        per_page: "per page",
        previous: "Previous",
        results: "results",
        to: "to"
    }
};

export default translationsObject.translations;

export function getTranslations() {
    return {
        next: trans("Next"),
        no_results_found: trans("No results found"),
        of: trans("of"),
        per_page: trans("per page"),
        previous: trans("Previous"),
        results: trans("results"),
        to: trans("to")
    };
}

export function setTranslation(key, value) {
    translationsObject.translations[key] = value;
}

export function setTranslations(translations) {
    translationsObject.translations = translations;
}