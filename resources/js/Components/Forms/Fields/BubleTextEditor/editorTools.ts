export const DEFAULT_EDITOR_TOOLS: string[] = [
	"heading1", "heading2", "heading3", "fontSize", "bold", "italic", "underline", "bulletList", "query", "fontFamily",
	"orderedList", "blockquote", "divider", "alignLeft", "alignRight", "customLink",
	"alignCenter", "undo", "redo", "highlight", "color", "clear", "image", "video", "table",
]

export const BLOG_EDITOR_TOOLS: string[] = [...DEFAULT_EDITOR_TOOLS, "ctaButton"]

export const fontSizeInPixels = (fontSize: unknown): string => {
	if (!fontSize || typeof fontSize !== "string") {
		return ""
	}
	const size = parseFloat(fontSize)
	if (isNaN(size)) {
		return ""
	}

	return Math.round(/px\s*$/i.test(fontSize) ? size : size * 16).toString()
}
