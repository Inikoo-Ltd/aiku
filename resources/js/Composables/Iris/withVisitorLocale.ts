type IrisLanguageData = {
    locale?: string
    website_i18n?: { shop_language?: { code?: string } }
}

export const withVisitorLocale = (href: string, iris?: IrisLanguageData | null): string => {
    const visitorLocale = iris?.locale
    if (!visitorLocale || visitorLocale === iris?.website_i18n?.shop_language?.code || /[?&]locale=/.test(href)) {
        return href
    }

    const [pathAndQuery, hash] = href.split("#", 2)
    const separator = pathAndQuery.includes("?") ? "&" : "?"

    return `${pathAndQuery}${separator}locale=${encodeURIComponent(visitorLocale)}${hash !== undefined ? `#${hash}` : ""}`
}
