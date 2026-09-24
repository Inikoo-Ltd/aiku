const IRIS_AUTH_COOKIE = "iris_vua"

/* Storefront HTML comes from Varnish, so the first paint knows nothing about the visitor and
   is_logged_in used to be seeded only from the localStorage snapshot the previous first-hit wrote.
   That snapshot still says "logged out" on the page right after signing in, so the whole storefront
   painted its logged out chrome until the first-hit fetch landed seconds later. The backend sets
   iris_vua on login and forgets it on logout, and the browser already holds it on that first paint. */
export const hasIrisAuthCookie = (): boolean => {
    if (typeof document === "undefined") {
        return false
    }

    return document.cookie
        .split(";")
        .some(cookie => cookie.trim().split("=")[0] === IRIS_AUTH_COOKIE)
}

export const resolveIsLoggedIn = (storedIsLoggedIn: unknown): boolean =>
    hasIrisAuthCookie() || !!storedIsLoggedIn
