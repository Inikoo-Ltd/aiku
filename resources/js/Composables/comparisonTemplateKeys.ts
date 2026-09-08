import type { ComparisonTemplate, ComparisonTemplateItem } from "@/Composables/comparisonTemplates"

export const isDynamicComparisonItem = (item: ComparisonTemplateItem): boolean => "show" in item

export const normaliseComparisonKey = (rawKey: string): string => rawKey.trim().replaceAll(/\s+/g, "_")

export const renameComparisonTemplateKey = (
    template: ComparisonTemplate,
    currentKey: string,
    rawKey: string
): ComparisonTemplate | null => {
    const item = template[currentKey]

    if (!item || isDynamicComparisonItem(item)) {
        return null
    }

    const newKey = normaliseComparisonKey(rawKey)

    if (!newKey || newKey === currentKey || newKey in template) {
        return null
    }

    return Object.fromEntries(
        Object.entries(template).map(
            ([key, templateItem]) => key === currentKey ? [newKey, templateItem] : [key, templateItem]
        )
    )
}
