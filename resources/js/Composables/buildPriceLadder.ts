export interface LadderProduct {
    id: number
    price: number | string
    stock?: number | string | null
    is_coming_soon?: boolean
    is_on_demand?: boolean
    pre_order?: { max_quantity?: number | null } | null
    size_resolved?: boolean
    size_litres?: number | string | null
    size_kilograms?: number | string | null
    size_label?: string | null
    step_discount?: { steps?: { min_quantity: number | string, percentage_off: number | string }[] } | null
}

export type LadderMeasure = "kg" | "L"

export interface LadderOption {
    key: string
    productId: number
    minQuantity: number
    percentageOff: number
    measure: LadderMeasure
    amount: number
    containerAmount: number
    containerLabel: string
    total: number
    grossTotal: number
    pricePerMeasure: number
    isStep: boolean
    isAvailable: boolean
}

const roundMoney = (value: number): number => Math.round(value * 100) / 100

const containerAmount = (product: LadderProduct, measure: LadderMeasure): number =>
    Number(measure === "kg" ? product.size_kilograms : product.size_litres) || 0

export const ladderMeasure = (products: LadderProduct[]): LadderMeasure | null => {
    if (!products.length || products.some((product) => !product.size_resolved)) {
        return null
    }

    if (products.every((product) => Number(product.size_kilograms) > 0)) {
        return "kg"
    }

    if (products.every((product) => Number(product.size_litres) > 0)) {
        return "L"
    }

    return null
}

const maxOrderable = (product: LadderProduct): number => {
    if (Number(product.price) <= 0) {
        return 0
    }

    if (Number(product.stock) > 0 && !product.is_coming_soon) {
        return Number(product.stock)
    }

    if (product.pre_order) {
        return product.pre_order.max_quantity ?? Infinity
    }

    return product.is_on_demand && !product.is_coming_soon ? Infinity : 0
}

export const buildPriceLadder = (products: LadderProduct[]): LadderOption[] => {
    const measure = ladderMeasure(products)

    if (!measure) {
        return []
    }

    return products
        .flatMap((product) => {
            const size = containerAmount(product, measure)
            const price = Number(product.price) || 0
            const limit = maxOrderable(product)
            const steps = (product.step_discount?.steps ?? [])
                .map((step) => ({ minQuantity: Number(step.min_quantity), percentageOff: Number(step.percentage_off) || 0 }))
                .filter((step) => step.minQuantity > 1 && step.percentageOff > 0)

            return [{ minQuantity: 1, percentageOff: 0 }, ...steps].map((tier) => {
                const grossTotal = roundMoney(price * tier.minQuantity)
                const total = roundMoney(grossTotal * (1 - tier.percentageOff))
                const amount = Math.round(size * tier.minQuantity * 1000000) / 1000000

                return {
                    key: `${product.id}:${tier.minQuantity}`,
                    productId: product.id,
                    minQuantity: tier.minQuantity,
                    percentageOff: tier.percentageOff,
                    measure,
                    amount,
                    containerAmount: size,
                    containerLabel: product.size_label ?? "",
                    total,
                    grossTotal,
                    pricePerMeasure: amount > 0 ? roundMoney(total / amount) : 0,
                    isStep: tier.minQuantity > 1,
                    isAvailable: limit >= tier.minQuantity
                }
            })
        })
        .sort((a, b) => a.amount - b.amount || a.containerAmount - b.containerAmount)
}

export const activeLadderOption = (options: LadderOption[], productId: number, quantity: number): LadderOption | null =>
    options
        .filter((option) => option.productId === productId && option.minQuantity <= Math.max(1, quantity))
        .sort((a, b) => a.minQuantity - b.minQuantity)
        .pop() ?? null

export const basketLadderOptionKeys = (options: LadderOption[], basketQuantities: Record<number, number>): Set<string> => {
    const keys = new Set<string>()

    for (const productId of new Set(options.map((option) => option.productId))) {
        const quantity = Number(basketQuantities[productId]) || 0

        if (quantity <= 0) {
            continue
        }

        const option = activeLadderOption(options, productId, quantity)

        if (option) {
            keys.add(option.key)
        }
    }

    return keys
}

export const sharesAmountWithAnotherOption = (options: LadderOption[], option: LadderOption): boolean =>
    options.some((other) => other.key !== option.key && other.amount === option.amount)

export const formatLadderAmount = (amount: number, measure: LadderMeasure): string => {
    const trimmed = (value: number) => String(Math.round(value * 1000) / 1000)

    if (measure === "L" && amount < 1) {
        return `${trimmed(amount * 1000)} ml`
    }

    return `${trimmed(amount)} ${measure}`
}
