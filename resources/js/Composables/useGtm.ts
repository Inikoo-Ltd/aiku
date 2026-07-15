export const GTM_DATA_LAYER_NAME = "gtmDataLayer"

const ALLOWED_GTM_EVENTS = [
    "registrationSuccess",
    "view_item",
    "purchase",
]

const getGtmDataLayer = (): Record<string, any>[] => {
    const w = window as any
    w[GTM_DATA_LAYER_NAME] = w[GTM_DATA_LAYER_NAME] || []

    return w[GTM_DATA_LAYER_NAME]
}

const pushEventWithEcommerceReset = (event: string, payload: Record<string, any>): void => {
    const gtmDataLayer = getGtmDataLayer()

    if ("ecommerce" in payload) {
        gtmDataLayer.push({ ecommerce: null })
    }

    gtmDataLayer.push({ event, ...payload })
}


export const pushGtmEvent = (event: string, payload: Record<string, any> = {}): void => {
    if (typeof window === "undefined") {
        return
    }

    if (!ALLOWED_GTM_EVENTS.includes(event)) {
        return
    }

    pushEventWithEcommerceReset(event, payload)
}


export const pushServerGtmEvent = (event: string, data: Record<string, any> = {}): void => {
    if (typeof window === "undefined" || !event) {
        return
    }

    if (!ALLOWED_GTM_EVENTS.includes(event)) {
        return
    }

    pushEventWithEcommerceReset(event, data)
}

interface RegistrationContact {
    contact_name?: string
    email?: string
    phone?: string
    contact_address?: Record<string, any>
}



const withoutEmptyValues = (data: Record<string, any>): Record<string, any> => {
    return Object.fromEntries(
        Object.entries(data).filter(([, value]) => value !== undefined && value !== null && value !== ""),
    )
}



export const buildRegistrationUserData = (
    contact: RegistrationContact,
): Record<string, any> => {
    const contactAddress = contact.contact_address ?? {}

    return withoutEmptyValues({
        email_address: contact.email?.trim().toLowerCase(),
    })
}
