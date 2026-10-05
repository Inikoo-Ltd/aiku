type SelectOption = Record<string, any>

const keyAsValue = (key: string): string | number => (/^\d+$/.test(key) ? Number(key) : key)

export const toSelectOptions = (
    options: unknown,
    valueProp: string = "value",
    labelProp: string = "label",
): unknown => {
    if (!options || Array.isArray(options) || typeof options !== "object") {
        return options
    }

    const entries = Object.entries(options as Record<string, unknown>)
    const holdsObjects = entries.some(([, option]) => option !== null && typeof option === "object")

    if (!holdsObjects) {
        return options
    }

    return entries.map(([key, option]): SelectOption => {
        if (option === null || typeof option !== "object") {
            return { [valueProp]: keyAsValue(key), [labelProp]: option }
        }

        const record = option as SelectOption

        return {
            ...record,
            [valueProp]: record[valueProp] ?? keyAsValue(key),
            [labelProp]: record[labelProp] ?? record.label ?? record.name,
        }
    })
}
