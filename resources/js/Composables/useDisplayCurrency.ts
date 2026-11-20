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
    if (!displayCurrency || !shopCurrencyCode || displayCurrency.code === shopCurrencyCode || amountCurrencyCode?.toUpperCase() !== shopCurrencyCode.toUpperCase()) {
        return null
    }

    const factor = 10 ** displayCurrency.fraction_digits

    return Math.round(Number(amount ?? 0) * displayCurrency.rate * factor) / factor
}
