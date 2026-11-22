import { ref } from "vue"

export const CONSENT_COOKIE = "iris_cookie_consent"
export const CONSENT_CATEGORIES = ["analytics", "marketing", "preferences"] as const
export type ConsentCategory = typeof CONSENT_CATEGORIES[number]
export type ConsentChoice = Record<ConsentCategory, boolean>

const CONSENT_MAX_AGE_SECONDS = 60 * 60 * 24 * 180

/* The cookie holds the granted categories joined by dots ("analytics.marketing"), "none" for a
   rejection, and is absent until the visitor has chosen. app-iris.blade.php reads the same format
   to set Google Consent Mode's defaults before GTM loads. */
export const serializeConsent = (choice: ConsentChoice): string => {
    const granted = CONSENT_CATEGORIES.filter(category => choice[category])
    return granted.length ? granted.join(".") : "none"
}

export const parseConsent = (value: string | null): ConsentChoice | null => {
    if (!value) {
        return null
    }

    const granted = value.split(".")

    return {
        analytics: granted.includes("analytics"),
        marketing: granted.includes("marketing"),
        preferences: granted.includes("preferences"),
    }
}

export const consentModeFromChoice = (choice: ConsentChoice) => {
    const state = (granted: boolean) => (granted ? "granted" : "denied")

    return {
        analytics_storage: state(choice.analytics),
        ad_storage: state(choice.marketing),
        ad_user_data: state(choice.marketing),
        ad_personalization: state(choice.marketing),
        personalization_storage: state(choice.preferences),
    }
}

const readConsentCookie = (): string | null => {
    const entry = document.cookie.split("; ").find(cookie => cookie.startsWith(`${CONSENT_COOKIE}=`))
    return entry ? decodeURIComponent(entry.slice(CONSENT_COOKIE.length + 1)) : null
}

const consent = ref<ConsentChoice | null>(null)
const isSettingsOpen = ref(false)

export const useCookieConsent = () => {
    const load = () => {
        consent.value = parseConsent(readConsentCookie())
    }

    const save = (choice: ConsentChoice) => {
        consent.value = choice
        document.cookie = `${CONSENT_COOKIE}=${serializeConsent(choice)}; path=/; max-age=${CONSENT_MAX_AGE_SECONDS}; SameSite=Lax${location.protocol === "https:" ? "; Secure" : ""}`

        const w = window as any
        w.gtmDataLayer = w.gtmDataLayer || []
        w.gtag = w.gtag || function () { w.gtmDataLayer.push(arguments) }
        w.gtag("consent", "update", consentModeFromChoice(choice))
        w.gtmDataLayer.push({ event: "cookie_consent_update" })
        isSettingsOpen.value = false
    }

    const acceptAll = () => save({ analytics: true, marketing: true, preferences: true })
    const rejectAll = () => save({ analytics: false, marketing: false, preferences: false })

    return { consent, isSettingsOpen, load, save, acceptAll, rejectAll }
}
