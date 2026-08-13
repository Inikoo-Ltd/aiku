export type PackedFractionData = [number, [number, number]]

export interface UnitsOverPack {
    numerator: number
    denominator: number
}

export const useUnitsOverPack = (fractionData?: PackedFractionData | null): UnitsOverPack | null => {
    if (!fractionData) {
        return null
    }

    const [wholePacks, [looseUnits, unitsPerPack]] = fractionData

    if (!unitsPerPack) {
        return null
    }

    return {
        numerator: wholePacks * unitsPerPack + looseUnits,
        denominator: unitsPerPack,
    }
}
