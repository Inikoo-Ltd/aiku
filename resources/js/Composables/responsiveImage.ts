export type ScreenType = "mobile" | "tablet" | "desktop"

const screenTypes: string[] = ["desktop", "tablet", "mobile"]

export const resolveResponsiveImage = <T extends Record<string, any>>(
	source: Record<string, any> | null | undefined,
	screenType: ScreenType
): T | null => {
	if (!source || typeof source !== "object") {
		return null
	}

	const image = source[screenType] ?? source.desktop
	if (image) {
		return image
	}

	return Object.keys(source).some((key) => !screenTypes.includes(key)) ? (source as T) : null
}
