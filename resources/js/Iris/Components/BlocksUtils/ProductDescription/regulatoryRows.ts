export interface RegulatoryLabelInfoItem {
	show?: boolean
	label?: string
	value?: unknown
}

export const hasRegulatoryValue = (value: unknown): boolean => {
	if (value === null || value === undefined || value === false) {
		return false
	}

	if (typeof value === "string") {
		return value.trim() !== ""
	}

	if (Array.isArray(value)) {
		return value.length > 0
	}

	if (typeof value === "object") {
		return Object.keys(value as object).length > 0
	}

	return true
}

export const hasRegulatoryRowContent = (item?: RegulatoryLabelInfoItem | null): boolean =>
	item?.show === true && hasRegulatoryValue(item.value)
