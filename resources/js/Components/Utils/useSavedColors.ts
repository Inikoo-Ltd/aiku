import { ref } from 'vue'
import {
	SAVED_COLORS_STORAGE_KEY,
	normaliseColor,
	parseSavedColors,
	withSavedColor,
	withoutSavedColor,
} from './savedColors'

const savedColors = ref<string[]>([])
let isHydrated = false

const readStoredColors = (): string | null => {
	try {
		return window.localStorage.getItem(SAVED_COLORS_STORAGE_KEY)
	} catch {
		return null
	}
}

const storeColors = (colors: string[]): void => {
	try {
		window.localStorage.setItem(SAVED_COLORS_STORAGE_KEY, JSON.stringify(colors))
	} catch {
		return
	}
}

const commit = (colors: string[]): void => {
	if (colors === savedColors.value) {
		return
	}

	savedColors.value = colors
	storeColors(colors)
}

export const useSavedColors = () => {
	if (!isHydrated) {
		savedColors.value = parseSavedColors(readStoredColors())
		isHydrated = true
	}

	const saveColor = (color: unknown): void => commit(withSavedColor(savedColors.value, color))

	const forgetColor = (color: unknown): void => commit(withoutSavedColor(savedColors.value, color))

	const isColorSaved = (color: unknown): boolean => {
		const normalised = normaliseColor(color)

		return normalised !== null && savedColors.value.includes(normalised)
	}

	return { savedColors, saveColor, forgetColor, isColorSaved }
}
