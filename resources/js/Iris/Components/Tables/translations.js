import { ctrans } from "@/Composables/useTrans"

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
        next: ctrans("Next"),
        no_results_found: ctrans("No results found"),
        of: ctrans("of"),
        per_page: ctrans("per page"),
        previous: ctrans("Previous"),
        results: ctrans("results"),
        to: ctrans("to")
    };
}

export function setTranslation(key, value) {
    translationsObject.translations[key] = value;
}

export function setTranslations(translations) {
    translationsObject.translations = translations;
}