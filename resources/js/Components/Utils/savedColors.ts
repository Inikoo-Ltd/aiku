export const SAVED_COLORS_STORAGE_KEY = 'aiku.editor.savedColors'
export const MAX_SAVED_COLORS = 16

const HEX_COLOR_PATTERN = /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/

const expandShorthand = (hex: string): string =>
	`#${hex.slice(1).split('').map(character => character + character).join('')}`

const dropOpaqueAlpha = (hex: string): string =>
	hex.length === 9 && hex.endsWith('ff') ? hex.slice(0, 7) : hex

export const normaliseColor = (color: unknown): string | null => {
	if (typeof color !== 'string') {
		return null
	}

	const hex = color.trim().toLowerCase()

	if (!HEX_COLOR_PATTERN.test(hex)) {
		return null
	}

	return dropOpaqueAlpha(hex.length <= 5 ? expandShorthand(hex) : hex)
}

export const parseSavedColors = (rawStorageValue: string | null): string[] => {
	if (!rawStorageValue) {
		return []
	}

	let parsed: unknown

	try {
		parsed = JSON.parse(rawStorageValue)
	} catch {
		return []
	}

	if (!Array.isArray(parsed)) {
		return []
	}

	const colors: string[] = []

	for (const candidate of parsed) {
		const color = normaliseColor(candidate)

		if (color && !colors.includes(color)) {
			colors.push(color)
		}
	}

	return colors.slice(0, MAX_SAVED_COLORS)
}

export const withSavedColor = (colors: string[], candidate: unknown): string[] => {
	const color = normaliseColor(candidate)

	if (!color) {
		return colors
	}

	return [color, ...colors.filter(saved => saved !== color)].slice(0, MAX_SAVED_COLORS)
}

export const withoutSavedColor = (colors: string[], candidate: unknown): string[] => {
	const color = normaliseColor(candidate)

	if (!color) {
		return colors
	}

	return colors.filter(saved => saved !== color)
}
