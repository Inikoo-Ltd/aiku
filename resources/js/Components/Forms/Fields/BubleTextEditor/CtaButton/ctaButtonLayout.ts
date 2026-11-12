export type CtaButtonAlignment = "left" | "center" | "right"
export type CtaButtonSize = "small" | "medium" | "large"
export type CtaButtonShape = "square" | "rounded" | "pill"
export type CtaButtonTarget = "_self" | "_blank"

export interface CtaButtonAttributes {
	label: string
	href: string
	target: CtaButtonTarget
	alignment: CtaButtonAlignment
	size: CtaButtonSize
	shape: CtaButtonShape
	backgroundColor: string | null
	textColor: string | null
}

export const CTA_BUTTON_THEME_BACKGROUND = "var(--theme-color-4, #4f46e5)"
export const CTA_BUTTON_THEME_TEXT = "var(--theme-color-5, #ffffff)"

export const CTA_BUTTON_ALIGNMENTS: CtaButtonAlignment[] = ["left", "center", "right"]
export const CTA_BUTTON_SIZES: CtaButtonSize[] = ["small", "medium", "large"]
export const CTA_BUTTON_SHAPES: CtaButtonShape[] = ["square", "rounded", "pill"]

const SIZE_STYLES: Record<CtaButtonSize, string> = {
	small: "padding: 0.5rem 1.25rem; font-size: 0.875rem;",
	medium: "padding: 0.75rem 1.75rem; font-size: 1rem;",
	large: "padding: 1rem 2.5rem; font-size: 1.125rem;",
}

const SHAPE_RADIUS: Record<CtaButtonShape, string> = {
	square: "0",
	rounded: "0.375rem",
	pill: "9999px",
}

const SAFE_SCHEME_PATTERN = /^(https?|mailto|tel|sms):/i
const SCHEME_PATTERN = /^[a-z][a-z0-9+.-]*:/i
const HEX_COLOR_PATTERN = /^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i
const RGB_COLOR_PATTERN = /^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/i

export const defaultCtaButtonAttributes = (): CtaButtonAttributes => ({
	label: "Buy now",
	href: "",
	target: "_self",
	alignment: "center",
	size: "medium",
	shape: "rounded",
	backgroundColor: null,
	textColor: null,
})

const oneOf = <T extends string>(value: unknown, allowed: T[], fallback: T): T =>
	allowed.includes(value as T) ? (value as T) : fallback

export const safeCtaButtonHref = (href: unknown): string => {
	const value = String(href ?? "").trim().replace(/[\u0000-\u001F\u007F]/g, "")
	if (!value || value.startsWith("//")) {
		return ""
	}

	if (SCHEME_PATTERN.test(value) && !SAFE_SCHEME_PATTERN.test(value)) {
		return ""
	}

	return value
}

export const safeCtaButtonColor = (color: unknown): string | null => {
	const value = String(color ?? "").trim()

	return HEX_COLOR_PATTERN.test(value) || RGB_COLOR_PATTERN.test(value) ? value : null
}

export const normaliseCtaButtonAttributes = (attributes: Partial<Record<keyof CtaButtonAttributes, unknown>>): CtaButtonAttributes => {
	const defaults = defaultCtaButtonAttributes()
	const label = String(attributes.label ?? "").trim()

	return {
		label: label || defaults.label,
		href: safeCtaButtonHref(attributes.href),
		target: attributes.target === "_blank" ? "_blank" : "_self",
		alignment: oneOf(attributes.alignment, CTA_BUTTON_ALIGNMENTS, defaults.alignment),
		size: oneOf(attributes.size, CTA_BUTTON_SIZES, defaults.size),
		shape: oneOf(attributes.shape, CTA_BUTTON_SHAPES, defaults.shape),
		backgroundColor: safeCtaButtonColor(attributes.backgroundColor),
		textColor: safeCtaButtonColor(attributes.textColor),
	}
}

export const ctaButtonWrapperStyle = (alignment: CtaButtonAlignment): string =>
	`text-align: ${oneOf(alignment, CTA_BUTTON_ALIGNMENTS, "center")}; margin: 1.25rem 0;`

export const ctaButtonStyle = (attributes: CtaButtonAttributes): string =>
	[
		"display: inline-block;",
		SIZE_STYLES[oneOf(attributes.size, CTA_BUTTON_SIZES, "medium")],
		"font-weight: 600; line-height: 1.25; text-align: center; text-decoration: none;",
		`border-radius: ${SHAPE_RADIUS[oneOf(attributes.shape, CTA_BUTTON_SHAPES, "rounded")]};`,
		`background-color: ${safeCtaButtonColor(attributes.backgroundColor) ?? CTA_BUTTON_THEME_BACKGROUND};`,
		`color: ${safeCtaButtonColor(attributes.textColor) ?? CTA_BUTTON_THEME_TEXT};`,
	].join(" ")

export const ctaButtonRel = (attributes: Pick<CtaButtonAttributes, "target">): string | null =>
	attributes.target === "_blank" ? "noopener" : null
