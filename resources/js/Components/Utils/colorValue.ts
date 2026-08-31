import { normaliseColor } from './savedColors'

export interface ColorValue {
	rgba: { r: number; g: number; b: number; a: number }
	hsv: { h: number; s: number; v: number }
	hex: string
}

const round = (value: number): number => parseFloat(value.toFixed(2))

const rgbToHsv = (r: number, g: number, b: number): ColorValue['hsv'] => {
	const red = r / 255
	const green = g / 255
	const blue = b / 255
	const max = Math.max(red, green, blue)
	const min = Math.min(red, green, blue)
	const delta = max - min

	let h = 0

	if (delta !== 0) {
		if (max === red) {
			h = green >= blue ? (60 * (green - blue)) / delta : (60 * (green - blue)) / delta + 360
		} else if (max === green) {
			h = (60 * (blue - red)) / delta + 120
		} else {
			h = (60 * (red - green)) / delta + 240
		}
	}

	return {
		h: Math.floor(h),
		s: max === 0 ? 0 : round(1 - min / max),
		v: round(max),
	}
}

export const hexToColorValue = (candidate: unknown): ColorValue | null => {
	const hex = normaliseColor(candidate)

	if (!hex) {
		return null
	}

	const r = parseInt(hex.slice(1, 3), 16)
	const g = parseInt(hex.slice(3, 5), 16)
	const b = parseInt(hex.slice(5, 7), 16)
	const a = hex.length === 9 ? round(parseInt(hex.slice(7, 9), 16) / 255) : 1

	return { rgba: { r, g, b, a }, hsv: rgbToHsv(r, g, b), hex }
}

export const colorValueToHex = ({ hex, rgba }: ColorValue): string =>
	rgba.a >= 1 ? hex.toLowerCase() : `${hex.toLowerCase()}${Math.round(rgba.a * 255).toString(16).padStart(2, '0')}`
