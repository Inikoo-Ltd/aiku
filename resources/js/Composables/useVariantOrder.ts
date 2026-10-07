type VariantOption = {
    variant_position?: number | null
    variant_label?: string | null
    is_leader?: boolean
}

const compareLabels = (a: VariantOption, b: VariantOption): number => {
    if (!a.variant_label) return 1
    if (!b.variant_label) return -1

    return a.variant_label.localeCompare(b.variant_label, undefined, { numeric: true, sensitivity: "base" })
}

const hasPosition = (option: VariantOption): boolean => option.variant_position !== null && option.variant_position !== undefined

/**
 * Options follow the order saved on the variant; options without a saved position come after,
 * leader first when asked, then by label.
 */
export const compareVariantOptions = (a: VariantOption, b: VariantOption, leaderFirst = false): number => {
    if (hasPosition(a) && hasPosition(b)) return (a.variant_position as number) - (b.variant_position as number)
    if (hasPosition(a)) return -1
    if (hasPosition(b)) return 1

    if (leaderFirst && a.is_leader !== b.is_leader) return a.is_leader ? -1 : 1

    return compareLabels(a, b)
}

export const sortVariantOptions = <T extends VariantOption>(options: T[], leaderFirst = false): T[] =>
    [...options].sort((a, b) => compareVariantOptions(a, b, leaderFirst))

export const hasSavedVariantOrder = (options: VariantOption[]): boolean => options.some(hasPosition)
