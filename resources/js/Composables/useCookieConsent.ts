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

/* CookieScript, the banner GTM loaded before this one, keeps the visitor's choice in its own
   first-party cookie: raw JSON with ";" written as "%3B", e.g.
   {"action":"accept","categories":"[\"performance\",\"targeting\"]",...}. categories is a JSON
   string (a plain [] on one of its reject paths) and never lists "strict". Reading it lets visitors
   keep the choice they already made instead of being asked again. Anything that cannot be read is
   null, so the banner shows; a reject is always everything denied, whatever categories says. */
export const LEGACY_CONSENT_COOKIE = "CookieScriptConsent"

const COOKIE_SCRIPT_CATEGORIES: Record<string, ConsentCategory> = {
    performance: "analytics",
    targeting: "marketing",
    functionality: "preferences",
}

export const parseCookieScriptConsent = (value: string | null): ConsentChoice | null => {
    if (!value) {
        return null
    }

    let stored: any
    try {
        stored = JSON.parse(value.replace(/%3B/g, ";"))
    } catch {
        try {
            stored = JSON.parse(decodeURIComponent(value))
        } catch {
            return null
        }
    }

    const rejected: ConsentChoice = { analytics: false, marketing: false, preferences: false }

    if (stored?.action === "reject") {
        return rejected
    }

    if (stored?.action !== "accept") {
        return null
    }

    let categories: unknown = stored.categories
    if (typeof categories === "string") {
        try {
            categories = JSON.parse(categories)
        } catch {
            return null
        }
    }

    if (!Array.isArray(categories)) {
        return null
    }

    const choice = { ...rejected }
    for (const category of categories) {
        const ours = COOKIE_SCRIPT_CATEGORIES[category]
        if (ours) {
            choice[ours] = true
        }
    }

    return choice
}

const readCookie = (name: string): string | null => {
    const entry = document.cookie.split("; ").find(cookie => cookie.startsWith(`${name}=`))
    return entry ? entry.slice(name.length + 1) : null
}

const readConsentCookie = (): string | null => {
    const value = readCookie(CONSENT_COOKIE)
    return value === null ? null : decodeURIComponent(value)
}

/* Counts for the accept / reject / ignore rates in grp (INI-572): "shown" once per browser session
   while the visitor has no choice, "choice" for their first choice only. Counters only, nothing that
   identifies the visitor, so this is sent before any consent; failures are ignored. */
const SHOWN_SESSION_KEY = "iris_cookie_banner_shown"

export const recordConsentStat = (body: { event: "shown" } | ({ event: "choice" } & ConsentChoice)) => {
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? "")

    fetch("/analytics/cookie-consent", {
        method: "POST",
        keepalive: true,
        headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": token },
        body: JSON.stringify(body),
    }).catch(() => {})
}

const recordShownOncePerSession = () => {
    try {
        if (sessionStorage.getItem(SHOWN_SESSION_KEY)) {
            return
        }
        sessionStorage.setItem(SHOWN_SESSION_KEY, "1")
    } catch {
        return
    }

    recordConsentStat({ event: "shown" })
}

const consent = ref<ConsentChoice | null>(null)
const isSettingsOpen = ref(false)

export const useCookieConsent = () => {
    const load = () => {
        consent.value = parseConsent(readConsentCookie())

        if (!consent.value) {
            const legacy = parseCookieScriptConsent(readCookie(LEGACY_CONSENT_COOKIE))
            if (legacy) {
                store(legacy)
            } else {
                recordShownOncePerSession()
            }
        }
    }

    /* A choice made on the banner; the first one is counted, later changes from Cookie settings are not. */
    const save = (choice: ConsentChoice) => {
        if (!consent.value) {
            recordConsentStat({ event: "choice", ...choice })
        }
        store(choice)
    }

    const store = (choice: ConsentChoice) => {
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
