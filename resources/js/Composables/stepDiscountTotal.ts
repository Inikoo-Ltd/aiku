export interface PercentageStep {
    min_quantity: number
    percentage_off: number | string
}

const roundMoney = (value: number): number => Math.round(value * 100) / 100

export const stepPercentageOffForQuantity = (steps: PercentageStep[] | null | undefined, quantity: number): number =>
    [...(steps ?? [])]
        .sort((a, b) => Number(a.min_quantity) - Number(b.min_quantity))
        .reduce((percentageOff, step) => (quantity >= Number(step.min_quantity) ? Number(step.percentage_off) || 0 : percentageOff), 0)

export const stepDiscountTotal = (basePrice: number, steps: PercentageStep[] | null | undefined, quantity: number): { gross: number, net: number } => {
    const safeQuantity = Math.max(0, Number(quantity) || 0)
    const gross = (Number(basePrice) || 0) * safeQuantity
    const percentageOff = Math.min(1, Math.max(0, stepPercentageOffForQuantity(steps, safeQuantity)))

    return {
        gross: roundMoney(gross),
        net: roundMoney(gross * (1 - percentageOff))
    }
}
