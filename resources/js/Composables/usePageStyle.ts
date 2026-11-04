import { cloneDeep, get, isPlainObject, set } from "lodash-es"

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

export interface PageStyleChange {
	path: string[]
	value: unknown
}

export interface PageStyleWebBlock {
	type: string
	uses_website_layout?: boolean
	web_block: { layout: { data: { fieldValue?: Record<string, any> } } }
}

interface StyleTarget {
	path: string[]
	type: string
	isButton: boolean
}

interface Layer {
	path: string[]
	value: Record<string, any>
}

const SCREENS = ["desktop", "tablet", "mobile"]
const MAIN_CONTAINER = ["container", "properties"]
const UNREPLACEABLE_BACKGROUNDS = new Set(["image", "gradient", "video"])

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

interface StyleWriter {
	changes: PageStyleChange[]
	write: (layer: Layer, property: string, value: unknown) => void
	layers: (path: string[]) => Layer[]
}

const createStyleWriter = (fieldValue: Record<string, any>): StyleWriter => {
	const changes: PageStyleChange[] = []

	const write = (layer: Layer, property: string, value: unknown) => {
		layer.value[property] = value
		changes.push({ path: [...layer.path, property], value: cloneDeep(value) })
	}

	const layers = (path: string[]): Layer[] => {
		let base = get(fieldValue, path)
		if (!isPlainObject(base)) {
			base = {}
			set(fieldValue, path, base)
		}

		return [
			{ path, value: base },
			...SCREENS.filter((screen) => isPlainObject(base[screen])).map((screen) => ({ path: [...path, screen], value: base[screen] })),
		]
	}

	return { changes, write, layers }
}

const setOnEveryScreen = (writer: StyleWriter, path: string[], property: string, value: unknown) => {
	for (const layer of writer.layers(path)) {
		writer.write(layer, property, value)
	}
}

const applyBackground = (writer: StyleWriter, path: string[], color: string, keepImages: boolean) => {
	for (const layer of writer.layers(path)) {
		if (keepImages && UNREPLACEABLE_BACKGROUNDS.has(layer.value.type)) {
			continue
		}
		writer.write(layer, "type", "color")
		writer.write(layer, "color", color)
	}
}

const applyRadius = (writer: StyleWriter, path: string[], radius: number) => {
	for (const layer of writer.layers(path)) {
		writer.write(layer, "rounded", {
			...(isPlainObject(layer.value.rounded) ? layer.value.rounded : {}),
			unit: "px",
			topleft: { value: radius },
			topright: { value: radius },
			bottomleft: { value: radius },
			bottomright: { value: radius },
		})
	}
}

const applySpacing = (writer: StyleWriter, path: string[], sides: string[], value: number) => {
	for (const layer of writer.layers(path)) {
		if (isSet(layer.value.unit) && layer.value.unit !== "px") {
			continue
		}
		writer.write(layer, "unit", "px")
		for (const side of sides) {
			writer.write(layer, side, { ...(isPlainObject(layer.value[side]) ? layer.value[side] : {}), value })
		}
	}
}

type TargetApplier = (writer: StyleWriter, target: StyleTarget, field: PageStyleField, value: any) => void

const SPACING_SIDES: Partial<Record<PageStyleField, string[]>> = {
	"spacing.vertical": ["top", "bottom"],
	"spacing.horizontal": ["left", "right"],
}

const applyTextTarget: TargetApplier = (writer, { path, isButton }, field, value) => {
	if (field === "font_family") {
		setOnEveryScreen(writer, path, "fontFamily", value)
		return
	}
	const colourField = isButton ? "colors.button_text" : "colors.text"
	if (field === colourField) {
		setOnEveryScreen(writer, path, "color", value)
	}
}

const applyBackgroundTarget: TargetApplier = (writer, { path, isButton }, field, value) => {
	if (field === "colors.primary" && isButton) {
		applyBackground(writer, path, value, false)
	} else if (field === "colors.background" && !isButton && isMainContainer(path, "background")) {
		applyBackground(writer, path, value, true)
	}
}

const applyBorderTarget: TargetApplier = (writer, { path, isButton }, field, value) => {
	if (field === "button.radius" && isButton) {
		applyRadius(writer, path, value)
	}
}

const applyPaddingTarget: TargetApplier = (writer, { path }, field, value) => {
	const sides = SPACING_SIDES[field]
	if (sides && isMainContainer(path, "padding")) {
		applySpacing(writer, path, sides, value)
	}
}

const TARGET_APPLIERS: Record<string, TargetApplier> = {
	textProperty: applyTextTarget,
	background: applyBackgroundTarget,
	border: applyBorderTarget,
	padding: applyPaddingTarget,
}

const applyFieldToTarget: TargetApplier = (writer, target, field, value) => {
	TARGET_APPLIERS[target.type]?.(writer, target, field, value)
}

export const changedPageStyleFields = (previous: PageStyle, next: PageStyle): PageStyleField[] =>
	PAGE_STYLE_FIELDS.filter((field) => isSet(get(next, field)) && get(previous, field) !== get(next, field))

export const filledPageStyleFields = (style: PageStyle): PageStyleField[] =>
	PAGE_STYLE_FIELDS.filter((field) => isSet(get(style, field)))

export const usesWebsiteLayout = (webBlock: PageStyleWebBlock) => webBlock?.uses_website_layout === true

export const applyPageStyleToBlock = (
	webBlock: PageStyleWebBlock,
	style: PageStyle,
	fields: PageStyleField[],
	blueprint: any[] | null | undefined
): PageStyleChange[] => {
	if (!fields.length || usesWebsiteLayout(webBlock) || !webBlock?.web_block?.layout?.data) {
		return []
	}

	const data = webBlock.web_block.layout.data
	data.fieldValue ??= {}
	const writer = createStyleWriter(data.fieldValue)
	const targets = collectStyleTargets(blueprint ?? [])

	for (const field of fields) {
		const value = get(style, field)
		for (const target of targets) {
			applyFieldToTarget(writer, target, field, value)
		}
	}

	return writer.changes
}
