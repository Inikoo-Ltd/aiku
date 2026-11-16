const FORMATTING_ATTRIBUTE = /\s(?:style|class|face|size|color|bgcolor|align|width|height|dir|lang|id|data-[\w-]+)\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi

export const stripPastedFormatting = (html: string): string =>
    html
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<(style|script|meta|link|title)\b[^>]*>[\s\S]*?<\/\1>/gi, '')
        .replace(/<(meta|link)\b[^>]*\/?>/gi, '')
        .replace(/<b\b[^>]*\bid="docs-internal-guid[^"]*"[^>]*>([\s\S]*?)<\/b>/gi, '$1')
        .replace(/<\/?(font|span)\b[^>]*>/gi, '')
        .replace(/<([a-z][\w-]*)\b([^>]*)>/gi, (_tag, name: string, attributes: string) => `<${name}${attributes.replace(FORMATTING_ATTRIBUTE, '')}>`)

export const withoutClipboardNoise = (html: string): string =>
    html
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<(style|script|title)\b[^>]*>[\s\S]*?<\/\1>/gi, '')
        .replace(/<(meta|link)\b[^>]*\/?>/gi, '')
        .trim()

type ClipboardEntry = { types: readonly string[], getType: (type: string) => Promise<Blob> }

export const formattedClipboardContent = async (items: ClipboardEntry[]): Promise<string | null> => {
    const htmlItem = items.find((item) => item.types.includes('text/html'))
    if (htmlItem) {
        return withoutClipboardNoise(await (await htmlItem.getType('text/html')).text()) || null
    }

    return null
}
