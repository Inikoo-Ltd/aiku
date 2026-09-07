export interface BlogHeading {
	id: string
	text: string
	level: number
}

export interface BlogTableOfContents {
	html: string
	headings: BlogHeading[]
}

const HEADING_TAG_PATTERN = /<(h[1-6])\b([^>]*)>([\s\S]*?)<\/\1\s*>/gi
const PARAGRAPH_PATTERN = /<p\b([^>]*)>([\s\S]*?)<\/p\s*>/gi
const FULLY_BOLD_PATTERN = /^\s*<(strong|b)\b[^>]*>([\s\S]*)<\/\1\s*>\s*$/i
const CLOSING_BOLD_PATTERN = /<\/(strong|b)\s*>/i
const LINK_OR_IMAGE_PATTERN = /<(a|img)\b/i
const ID_ATTRIBUTE_PATTERN = /\bid\s*=\s*(?:"([^"]*)"|'([^']*)')/i
const MAX_BOLD_HEADING_LENGTH = 80
const MIN_HEADINGS = 2

export const htmlToPlainText = (html: string): string =>
	(html ?? "")
		.replace(/<[^>]*>/g, " ")
		.replace(/&nbsp;/g, " ")
		.replace(/&lt;/g, "<")
		.replace(/&gt;/g, ">")
		.replace(/&quot;/g, '"')
		.replace(/&#39;/g, "'")
		.replace(/&amp;/g, "&")
		.replace(/\s+/g, " ")
		.trim()

const readId = (attributes: string): string | null => {
	const match = attributes.match(ID_ATTRIBUTE_PATTERN)

	return match ? (match[1] ?? match[2]) : null
}

const withId = (attributes: string, id: string): string =>
	readId(attributes) ? attributes : ` id="${id}"${attributes}`

const collectHeadingTags = (content: string): BlogTableOfContents => {
	const headings: BlogHeading[] = []

	const html = content.replace(HEADING_TAG_PATTERN, (match, tag: string, attributes: string, inner: string) => {
		const text = htmlToPlainText(inner)
		if (!text) {
			return match
		}

		const id = readId(attributes) ?? `heading-${headings.length}`
		headings.push({ id, text, level: Number(tag.slice(1)) })

		return `<${tag}${withId(attributes, id)}>${inner}</${tag}>`
	})

	return { html, headings }
}

const collectBoldParagraphs = (content: string): BlogTableOfContents => {
	const headings: BlogHeading[] = []

	const html = content.replace(PARAGRAPH_PATTERN, (match, attributes: string, inner: string) => {
		const bold = inner.match(FULLY_BOLD_PATTERN)
		if (!bold) {
			return match
		}

		const boldContent = bold[2]
		if (CLOSING_BOLD_PATTERN.test(boldContent) || LINK_OR_IMAGE_PATTERN.test(boldContent)) {
			return match
		}

		const text = htmlToPlainText(boldContent)
		if (!text || text.length > MAX_BOLD_HEADING_LENGTH) {
			return match
		}

		const id = readId(attributes) ?? `heading-${headings.length}`
		headings.push({ id, text, level: 2 })

		return `<p${withId(attributes, id)}>${inner}</p>`
	})

	if (headings.length < MIN_HEADINGS) {
		return { html: content, headings: [] }
	}

	return { html, headings }
}

export const buildBlogTableOfContents = (content: string): BlogTableOfContents => {
	if (!content) {
		return { html: "", headings: [] }
	}

	const fromHeadingTags = collectHeadingTags(content)
	if (fromHeadingTags.headings.length >= MIN_HEADINGS) {
		return fromHeadingTags
	}

	return collectBoldParagraphs(content)
}
