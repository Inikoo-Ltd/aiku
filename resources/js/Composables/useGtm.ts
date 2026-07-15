export const GTM_DATA_LAYER_NAME = "gtmDataLayer"

const ALLOWED_GTM_EVENTS = [
    "registrationSuccess",
    "view_item",
    "add_to_cart",
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



interface GtmProduct {
    slug?: string
    name?: string
    price?: number | string
    currency_code?: string
    family_code?: string
    variant_label?: string
}

const toGtmAmount = (value: number | string | undefined): number | undefined => {
    const amount = typeof value === "number" ? value : parseFloat(value ?? "")

    return Number.isFinite(amount) ? Math.round(amount * 100) / 100 : undefined
}

export const buildGtmProductPayload = (
    product: GtmProduct,
    options: { currencyCode?: string, quantity?: number } = {},
): Record<string, any> => {
    const quantity = options.quantity ?? 1
    const price = toGtmAmount(product.price)

    const item = withoutEmptyValues({
        item_id: product.slug,
        item_name: product.name,
        item_category: product.family_code,
        item_variant: product.variant_label,
        price,
        quantity,
    })

    return {
        ecommerce: withoutEmptyValues({
            currency: product.currency_code ?? options.currencyCode,
            value: price === undefined ? undefined : toGtmAmount(price * quantity),
            items: [item],
        }),
    }
}


export const buildRegistrationUserData = (
    contact: RegistrationContact,
): Record<string, any> => {

    return withoutEmptyValues({
        email_address: contact.email?.trim().toLowerCase(),
    })
}
