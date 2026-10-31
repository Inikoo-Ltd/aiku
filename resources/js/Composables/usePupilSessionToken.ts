/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import axios from "axios"
import { http } from "@inertiajs/vue3"

/*
 * The embedded app runs as an SPA, so Shopify's token handler partial is never rendered and
 * nothing puts a session token on our requests. App Bridge mints one on demand and they last
 * about a minute, so it is fetched per request rather than held: a cached token is expired by
 * the time a merchant gets round to opening the chat.
 */
const isOwnRequest = (url?: string): boolean => {
    if (!url) {
        return true
    }

    if (!/^https?:\/\//i.test(url)) {
        return true
    }

    return url.startsWith(window.location.origin)
}

const addSessionToken = async <T extends { url?: string, headers?: Record<string, unknown> }>(config: T): Promise<T> => {
    const shopify = (window as any).shopify

    if (!shopify?.idToken || !config.headers || !isOwnRequest(config.url)) {
        return config
    }

    try {
        const token = await shopify.idToken()
        config.headers.Authorization = `Bearer ${token}`
        ;(window as any).sessionToken = token
    } catch (error) {
        console.error("Could not get a Shopify session token", error)
    }

    return config
}

// Inertia visits go through Inertia's own http client, not axios, so both get the token.
export const usePupilSessionToken = () => {
    axios.interceptors.request.use(addSessionToken)
    http.onRequest(addSessionToken)
}
