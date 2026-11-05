/**
 * Amounts typed or copied from a bank statement come as "146,17", "1.234,56", "1,234.56" or "1 234,56 €".
 * The first number in the text is read; spaces count as thousands only before exactly three digits, so text
 * after the amount ("146,17 ref 123456") is ignored. The last separator is the decimal one, unless it is
 * followed by exactly three digits and is either repeated or the only separator ("1.234", "1.234.567"),
 * which is thousands.
 */
export const parseAmount = (text: string): number | null => {
    const firstNumber = text.match(/-?\s*[.,]?\d(?:[\d.,]|[  ](?=\d{3}(?!\d)))*/)

    if (!firstNumber) {
        return null
    }

    const cleaned = firstNumber[0].replace(/[^\d.,-]/g, "")

    const isNegative = cleaned.startsWith("-")
    const amount = cleaned.replace(/-/g, "")
    const lastSeparatorIndex = Math.max(amount.lastIndexOf("."), amount.lastIndexOf(","))

    let integerPart = amount
    let fractionPart = ""

    if (lastSeparatorIndex >= 0) {
        const separator = amount[lastSeparatorIndex]
        const before = amount.slice(0, lastSeparatorIndex)
        const after = amount.slice(lastSeparatorIndex + 1)
        const hasOtherSeparatorBefore = /[.,]/.test(before.replaceAll(separator, ""))
        const isUsedOnce = !before.includes(separator)
        const isDecimal = hasOtherSeparatorBefore || after.length !== 3 || (isUsedOnce && /^0?$/.test(before))

        if (isDecimal) {
            integerPart = before
            fractionPart = after
        }
    }

    const value = Number(`${integerPart.replace(/[.,]/g, "") || "0"}.${fractionPart || "0"}`)

    return isNegative ? -value : value
}
