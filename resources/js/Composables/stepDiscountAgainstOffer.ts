export interface StepDiscountStepLike {
    min_quantity: number
    percentage_off: number
    is_popular?: boolean
}

const MEMBER_OFFER_TYPE = "Category Quantity Ordered Order Interval"

export function activeSpecialOfferPercentageOff(
    bestOffer: { type?: string } | null | undefined,
    offersData: { best_percentage_off?: { percentage_off?: number | string } } | null | undefined,
    isMemberPriceActive: boolean
): number {
    if (!bestOffer) {
        return 0
    }

    if (bestOffer.type === MEMBER_OFFER_TYPE && !isMemberPriceActive) {
        return 0
    }

    return Number(offersData?.best_percentage_off?.percentage_off) || 0
}

export function stepsBeatingSpecialOffer<T extends StepDiscountStepLike>(steps: T[], specialOfferPercentageOff: number): T[] {
    if (!(specialOfferPercentageOff > 0)) {
        return steps
    }

    return steps.filter((step) => Number(step.percentage_off) > specialOfferPercentageOff)
}

export function popularStepMinQuantity(allSteps: StepDiscountStepLike[], visibleSteps: StepDiscountStepLike[]): number | null {
    if (!visibleSteps.length) {
        return null
    }

    const flagged = allSteps.find((step) => step.is_popular)
    if (flagged) {
        return visibleSteps.some((step) => step.min_quantity === flagged.min_quantity) ? flagged.min_quantity : null
    }

    return visibleSteps[Math.floor(visibleSteps.length / 2)].min_quantity
}
