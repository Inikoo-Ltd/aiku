/**
 * Dedicated Google Tag Manager data layer.
 *
 * GTM is configured (via the `&l=gtmDataLayer` parameter in its loader snippet)
 * to read ONLY `window.gtmDataLayer`, never the shared `window.dataLayer` used
 * by Luigisbox. This keeps Luigisbox analytics events out of GTM. Push GTM
 * events exclusively through the helpers below so nothing unintended leaks in.
 */

export const GTM_DATA_LAYER_NAME = "gtmDataLayer"

// Client-side events GTM is allowed to receive. Anything not listed is ignored.
const ALLOWED_GTM_EVENTS = [
    "add_to_cart",
    "registrationSuccess",
]

const getGtmDataLayer = (): Record<string, any>[] => {
    const w = window as any
    w[GTM_DATA_LAYER_NAME] = w[GTM_DATA_LAYER_NAME] || []

    return w[GTM_DATA_LAYER_NAME]
}

/**
 * Push a whitelisted client event to GTM's dedicated data layer.
 * Events outside ALLOWED_GTM_EVENTS are dropped.
 */
export const pushGtmEvent = (event: string, payload: Record<string, any> = {}): void => {
    if (typeof window === "undefined") {
        return
    }

    if (!ALLOWED_GTM_EVENTS.includes(event)) {
        return
    }

    getGtmDataLayer().push({ event, ...payload })
}

/**
 * Push a server-defined (trusted) GTM event to GTM's dedicated data layer.
 * Used for events emitted through Inertia flash props.
 */
export const pushServerGtmEvent = (event: string, data: Record<string, any> = {}): void => {
    if (typeof window === "undefined" || !event) {
        return
    }

    getGtmDataLayer().push({ event, ...data })
}
