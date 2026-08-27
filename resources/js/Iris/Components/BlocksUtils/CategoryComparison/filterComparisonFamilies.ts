import type { ComparisonFamily } from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

const containsSearch = (value: string | undefined | null, search: string) =>
    typeof value === "string" && value.toLowerCase().includes(search)

export const filterComparisonFamilies = (
    families: ComparisonFamily[],
    search: string
): ComparisonFamily[] => {
    const normalisedSearch = search.trim().toLowerCase()

    if (!normalisedSearch) {
        return families
    }

    return families.filter(
        family =>
            containsSearch(family.name, normalisedSearch) ||
            containsSearch(family.code, normalisedSearch)
    )
}
