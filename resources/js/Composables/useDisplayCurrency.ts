export interface DisplayCurrency {
    code: string
    name: string
    rate: number
    fraction_digits: number
}

export const indicativeAmount = (
    displayCurrency: DisplayCurrency | null | undefined,
    shopCurrencyCode: string | null | undefined,
    amountCurrencyCode: string | null | undefined,
    amount: number | string | null | undefined,
): number | null => {
    if (!displayCurrency || !shopCurrencyCode || amount === null || amount === undefined || amount === "" || Number.isNaN(Number(amount)) || displayCurrency.code === shopCurrencyCode || amountCurrencyCode?.toUpperCase() !== shopCurrencyCode.toUpperCase()) {
        return null
    }

    const factor = 10 ** displayCurrency.fraction_digits

    return Math.round(Number(amount ?? 0) * displayCurrency.rate * factor) / factor
}

export const formatIndicative = (
    locale: { currencyFormat: Function, currencyFormatRrp: Function, currencyFormatUnitCost: Function },
    layout: { retina?: { display_currency?: DisplayCurrency | null, currency?: { code?: string } } } | null | undefined,
    currencyCode: string | null | undefined,
    amount: number | string | null | undefined,
    rrp = false,
): string => {
    const displayCurrency = layout?.retina?.display_currency
    const value = indicativeAmount(displayCurrency, layout?.retina?.currency?.code, currencyCode, amount)
    if (value === null || !displayCurrency) {
        return rrp ? locale.currencyFormatRrp(currencyCode ?? "", amount ?? 0) : locale.currencyFormat(currencyCode ?? "", amount ?? 0)
    }

    return `≈ ${locale.currencyFormatUnitCost(displayCurrency.code, value)}`
}
