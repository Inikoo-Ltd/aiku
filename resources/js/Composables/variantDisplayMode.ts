export type VariantDisplayMode = "default" | "sizes_table" | "price_ladder"

export interface VariantDisplaySetting {
    variant_display?: VariantDisplayMode | null
    variant_sizes_table?: boolean | null
    variant_price_ladder?: boolean | null
}

export const chosenVariantDisplay = (setting: VariantDisplaySetting | null | undefined): VariantDisplayMode => {
    if (setting?.variant_display === "sizes_table" || setting?.variant_display === "price_ladder") {
        return setting.variant_display
    }

    if (setting?.variant_display === "default") {
        return "default"
    }

    if (setting?.variant_price_ladder) {
        return "price_ladder"
    }

    return setting?.variant_sizes_table ? "sizes_table" : "default"
}

export const variantDisplayMode = (
    setting: VariantDisplaySetting | null | undefined,
    canShow: { sizesTable: boolean, priceLadder: boolean }
): VariantDisplayMode => {
    const chosen = chosenVariantDisplay(setting)

    if (chosen === "sizes_table" && canShow.sizesTable) {
        return "sizes_table"
    }

    if (chosen === "price_ladder" && canShow.priceLadder) {
        return "price_ladder"
    }

    return "default"
}
