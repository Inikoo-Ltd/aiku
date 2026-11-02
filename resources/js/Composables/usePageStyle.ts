import { get, isPlainObject, set } from "lodash-es"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"

export interface PageStyle {
	colors?: {
		text?: string | null
		background?: string | null
		primary?: string | null
		button_text?: string | null
	}
	font_family?: string | null
	button?: {
		radius?: number | null
	}
	spacing?: {
		vertical?: number | null
		horizontal?: number | null
	}
}

export const PAGE_STYLE_FIELDS = [
	"colors.text",
	"colors.background",
	"colors.primary",
	"colors.button_text",
	"font_family",
	"button.radius",
	"spacing.vertical",
	"spacing.horizontal",
] as const

export type PageStyleField = (typeof PAGE_STYLE_FIELDS)[number]

interface StyleTarget {
	path: string[]
	type: string
	isButton: boolean
}

const SCREENS = ["desktop", "tablet", "mobile"]
const MAIN_CONTAINER = ["container", "properties"]
const UNREPLACEABLE_BACKGROUNDS = ["image", "gradient", "video"]

const isSet = (value: unknown) => value !== null && value !== undefined && value !== ""

const isMainContainer = (path: string[], property: string) =>
	path.length === 3 && path[0] === MAIN_CONTAINER[0] && path[1] === MAIN_CONTAINER[1] && path[2] === property

const collectStyleTargets = (forms: any[], parentPath: string[] = [], inButton = false): StyleTarget[] => {
	const targets: StyleTarget[] = []

	for (const form of forms ?? []) {
		if (!form || form.key === undefined) {
			continue
		}

		const path = [...parentPath, ...(Array.isArray(form.key) ? form.key : [form.key])]
		const isButton = inButton || form.editGlobalStyle === "button" || path.some((segment) => /button/i.test(String(segment)))

		if (Array.isArray(form.replaceForm)) {
			targets.push(...collectStyleTargets(form.replaceForm, path, isButton))
		} else if (form.type) {
			targets.push({ path, type: form.type, isButton })
		}
	}

	return targets
}

const responsiveLayers = (fieldValue: Record<string, any>, path: string[]): Record<string, any>[] => {
	let base = get(fieldValue, path)
	if (!isPlainObject(base)) {
		base = {}
		set(fieldValue, path, base)
	}

	return [base, ...SCREENS.map((screen) => base[screen]).filter((layer) => isPlainObject(layer))]
}

const setOnEveryScreen = (fieldValue: Record<string, any>, path: string[], property: string, value: unknown) => {
	for (const layer of responsiveLayers(fieldValue, path)) {
		set(layer, property, value)
	}
}

const applyBackground = (fieldValue: Record<string, any>, path: string[], color: string, keepImages: boolean) => {
	for (const layer of responsiveLayers(fieldValue, path)) {
		if (keepImages && UNREPLACEABLE_BACKGROUNDS.includes(layer.type)) {
			continue
		}
		layer.type = "color"
		layer.color = color
	}
}

const applyRadius = (fieldValue: Record<string, any>, path: string[], radius: number) => {
	for (const layer of responsiveLayers(fieldValue, path)) {
		layer.rounded = {
			...(isPlainObject(layer.rounded) ? layer.rounded : {}),
			unit: "px",
			topleft: { value: radius },
			topright: { value: radius },
			bottomleft: { value: radius },
			bottomright: { value: radius },
		}
	}
}

const applySpacing = (fieldValue: Record<string, any>, path: string[], sides: string[], value: number) => {
	for (const layer of responsiveLayers(fieldValue, path)) {
		if (isSet(layer.unit) && layer.unit !== "px") {
			continue
		}
		layer.unit = "px"
		for (const side of sides) {
			layer[side] = { ...(isPlainObject(layer[side]) ? layer[side] : {}), value }
		}
	}
}

const applyFieldToTarget = (fieldValue: Record<string, any>, target: StyleTarget, field: PageStyleField, value: any) => {
	const { path, type, isButton } = target

	if (type === "textProperty") {
		if (field === "font_family") {
			setOnEveryScreen(fieldValue, path, "fontFamily", value)
		} else if (field === "colors.text" && !isButton) {
			setOnEveryScreen(fieldValue, path, "color", value)
		} else if (field === "colors.button_text" && isButton) {
			setOnEveryScreen(fieldValue, path, "color", value)
		}
	} else if (type === "background") {
		if (field === "colors.primary" && isButton) {
			applyBackground(fieldValue, path, value, false)
		} else if (field === "colors.background" && !isButton && isMainContainer(path, "background")) {
			applyBackground(fieldValue, path, value, true)
		}
	} else if (type === "border" && field === "button.radius" && isButton) {
		applyRadius(fieldValue, path, value)
	} else if (type === "padding" && isMainContainer(path, "padding")) {
		if (field === "spacing.vertical") {
			applySpacing(fieldValue, path, ["top", "bottom"], value)
		} else if (field === "spacing.horizontal") {
			applySpacing(fieldValue, path, ["left", "right"], value)
		}
	}
}

export const changedPageStyleFields = (previous: PageStyle, next: PageStyle): PageStyleField[] =>
	PAGE_STYLE_FIELDS.filter((field) => isSet(get(next, field)) && get(previous, field) !== get(next, field))

export const filledPageStyleFields = (style: PageStyle): PageStyleField[] =>
	PAGE_STYLE_FIELDS.filter((field) => isSet(get(style, field)))

export const applyPageStyleToBlock = (
	webBlock: { type: string, web_block: { layout: { data: { fieldValue?: Record<string, any> } } } },
	style: PageStyle,
	fields: PageStyleField[],
	webpage?: object
) => {
	if (!fields.length || !webBlock?.web_block?.layout?.data) {
		return
	}

	const fieldValue = (webBlock.web_block.layout.data.fieldValue ??= {})
	const targets = collectStyleTargets(getBlueprint(webBlock.type, webpage) ?? [])

	for (const field of fields) {
		const value = get(style, field)
		for (const target of targets) {
			applyFieldToTarget(fieldValue, target, field, value)
		}
	}
}
