// Errors that are not ours to fix: tag manager pixels firing before (or without) their base code,
// in-app browser bridges, browser extensions, chunks of a release that was replaced while the page
// was open, the network dropping, and the moments of a deploy when the app answers 503.
export const sentryIgnoreErrors: (string | RegExp)[] = [
    /\b(fbq|pintrk|ttq|gtag|_learnq)\b.*(is not defined|Can't find variable)|(Can't find variable|is not defined).*\b(fbq|pintrk|ttq|gtag|_learnq)\b/,
    /Java object is gone/,
    /Failed to fetch dynamically imported module/,
    /Importing a module script failed/,
    /error loading dynamically imported module/,
    /^(AxiosError: )?Network Error$/,
    /status code:? 503$/,
]

export const sentryDenyUrls: RegExp[] = [
    /^(chrome|moz|safari|safari-web|ms-browser)-extension:\/\//,
    /^iabjs:\/\//,
]
