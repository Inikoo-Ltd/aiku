/**
 * Author: Vika Aqordi
 * Created on 06-11-2025-16h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import axios from "axios"

/**
 * The #anchor of the page a visitor was sent to login from (e.g. #whatsapp-updates on an
 * email deep link) never reaches the server, but the browser keeps it on the login page.
 * Put it back on the post-login destination, only for same-origin destinations without
 * an anchor of their own, and only when it is a plain id.
 */
export const withCurrentHash = (url: string): string => {
    if (typeof window === 'undefined' || !url) {
        return url
    }

    const hash = window.location.hash
    if (!/^#[A-Za-z][\w-]{0,63}$/.test(hash) || url.includes('#')) {
        return url
    }

    try {
        const target = new URL(url, window.location.origin)
        if (target.origin !== window.location.origin) {
            return url
        }
    } catch {
        return url
    }

    return `${url}${hash}`
}

export const getRefRedirect = async (options: { registered?: boolean } = {}) => {
    try {
        const params = new URLSearchParams()
        const ref = route()?.params?.['ref']

        if (ref) {
            params.set('ref', String(ref))
        }

        if (options.registered) {
            params.set('registered', '1')
        }

        const response = await axios.get(`/json/canonical-redirect?${params.toString()}`)

        if (response.data?.redirect_url) {
            return response.data?.redirect_url
        }

        return '/'
    } catch (error: any) {
        return '/'
    }
}
