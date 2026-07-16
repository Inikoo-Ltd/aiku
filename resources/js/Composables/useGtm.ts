export const GTM_DATA_LAYER_NAME = "gtmDataLayer"

const ALLOWED_GTM_EVENTS = [
    "registrationSuccess",
    "view_item",
    "add_to_cart",
    "remove_from_cart",
    "purchase",
]

const GTM_EVENT_CALLBACK_TIMEOUT_MS = 2000

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


export const pushGtmEventAndWaitForTags = (
    event: string,
    payload: Record<string, any> = {},
    timeoutMs: number = GTM_EVENT_CALLBACK_TIMEOUT_MS,
): Promise<void> => {
    if (typeof window === "undefined") {
        return Promise.resolve()
    }

    if (!ALLOWED_GTM_EVENTS.includes(event)) {
        return Promise.resolve()
    }

    return new Promise((resolve) => {
        let hasSettled = false

        const settle = (): void => {
            if (hasSettled) {
                return
            }

            hasSettled = true
            window.clearTimeout(timeoutId)
            resolve()
        }

        const timeoutId = window.setTimeout(settle, timeoutMs)

        pushEventWithEcommerceReset(event, {
            ...payload,
            eventTimeout: timeoutMs,
            eventCallback: settle,
        })
    })
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

interface CountryAddressData {
    code?: string
    phone_code?: string | number
}



const withoutEmptyValues = (data: Record<string, any>): Record<string, any> => {
    return Object.fromEntries(
        Object.entries(data).filter(([, value]) => value !== undefined && value !== null && value !== ""),
    )
}



interface GtmProduct {
    slug?: string | null
    name?: string | null
    price?: number | string | null
    currency_code?: string | null
    family_code?: string | null
    variant_label?: string | null
    discounted_price?: number
}

const toGtmAmount = (value: number | string | undefined | null): number | undefined => {
    const amount = typeof value === "number" ? value : parseFloat(value ?? "")

    return Number.isFinite(amount) ? Math.round(amount * 100) / 100 : undefined
}

export const buildGtmProductPayload = (
    product: GtmProduct,
    options: { currencyCode?: string, quantity?: number } = {},
): Record<string, any> => {
    const quantity = options.quantity ?? 1
    const price = toGtmAmount(product.price)
    const discount = toGtmAmount(product.price - product.discounted_price)

    const item = withoutEmptyValues({
        item_id: product.slug,
        item_name: product.name,
        item_category: product.family_code,
        price,
        quantity,
        discount,
    })

    return {
        ecommerce: withoutEmptyValues({
            currency: product.currency_code ?? options.currencyCode,
            value: price === undefined ? undefined : toGtmAmount(price * quantity),
            items: [item],
        }),
    }
}


const GOOGLE_MAIL_DOMAINS = ["gmail.com", "googlemail.com"]

/**
 * Google normalises gmail.com/googlemail.com usernames by stripping dots and any
 * "+suffix" before hashing, so the same is done here to keep match rates intact.
 */
const normaliseEmailAddress = (email?: string): string | undefined => {
    const trimmedEmail = email?.trim().toLowerCase()
    const separatorIndex = trimmedEmail?.lastIndexOf("@") ?? -1

    if (!trimmedEmail || separatorIndex < 1) {
        return undefined
    }

    const username = trimmedEmail.slice(0, separatorIndex)
    const domain = trimmedEmail.slice(separatorIndex + 1)

    if (!domain || !GOOGLE_MAIL_DOMAINS.includes(domain)) {
        return trimmedEmail
    }

    const normalisedUsername = username.split("+")[0].replace(/\./g, "")

    return normalisedUsername ? `${normalisedUsername}@${domain}` : undefined
}

const splitContactName = (contactName?: string): { first_name?: string, last_name?: string } => {
    const nameParts = contactName?.trim().split(/\s+/).filter(Boolean) ?? []

    if (nameParts.length === 0) {
        return {}
    }

    return {
        first_name: nameParts[0],
        last_name: nameParts.slice(1).join(" ") || undefined,
    }
}

/**
 * Google only accepts phone numbers in E.164 notation, so a number that cannot be
 * resolved to one (no country dialling code known) is left out rather than sent
 * in a format that would be rejected downstream.
 */
const toE164PhoneNumber = (phone?: string, phoneCode?: string | number): string | undefined => {
    const trimmedPhone = phone?.trim()

    if (!trimmedPhone) {
        return undefined
    }

    if (trimmedPhone.startsWith("+")) {
        const internationalDigits = trimmedPhone.replace(/\D/g, "")

        return internationalDigits ? `+${internationalDigits}` : undefined
    }

    const nationalDigits = trimmedPhone.replace(/\D/g, "").replace(/^0+/, "")
    const diallingCode = `${phoneCode ?? ""}`.replace(/\D/g, "")

    if (!nationalDigits || !diallingCode) {
        return undefined
    }

    return `+${diallingCode}${nationalDigits}`
}

const buildRegistrationAddress = (
    contact: RegistrationContact,
    country: CountryAddressData | undefined,
): Record<string, any> => {
    const contactAddress = contact.contact_address ?? {}

    const street = [contactAddress.address_line_1, contactAddress.address_line_2]
        .map((addressLine) => addressLine?.trim())
        .filter(Boolean)
        .join(" ")

    return withoutEmptyValues({
        ...splitContactName(contact.contact_name),
        street,
        city: contactAddress.locality ?? contactAddress.dependent_locality,
        region: contactAddress.administrative_area,
        postal_code: contactAddress.postal_code,
        country: country?.code,
    })
}

export const buildRegistrationUserData = (
    contact: RegistrationContact,
    countriesAddressData: Record<string, CountryAddressData> = {},
): Record<string, any> => {
    const country = countriesAddressData?.[contact.contact_address?.country_id]
    const address = buildRegistrationAddress(contact, country)

    return withoutEmptyValues({
        email: normaliseEmailAddress(contact.email),
        phone_number: toE164PhoneNumber(contact.phone, country?.phone_code),
        address: Object.keys(address).length > 0 ? address : undefined,
    })
}
