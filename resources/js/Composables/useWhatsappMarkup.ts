const ESCAPES: Record<string, string> = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
}

const escapeHtml = (value: string) => value.replace(/[&<>"']/g, (char) => ESCAPES[char])

/**
 * A marker only opens when it follows the start of a line or a non-word character, and
 * only closes when the character after it is not a word character. Without that guard an
 * underscore inside snake_case or an asterisk in a maths expression would open a tag.
 */
const wrap = (text: string, marker: string, tag: string) => {
    const escaped = marker.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
    const pattern = new RegExp(
        `(^|[^\\w${escaped}])${escaped}([^\\s${escaped}][^${escaped}\\n]*[^\\s${escaped}]|[^\\s${escaped}])${escaped}(?![\\w${escaped}])`,
        "g"
    )

    return text.replace(pattern, `$1<${tag}>$2</${tag}>`)
}

/**
 * Renders the markup WhatsApp itself understands, so a message written on a phone reads
 * the same in the inbox instead of showing its raw markers.
 */
export const formatWhatsappMarkup = (value?: string | null): string => {
    if (!value) {
        return ""
    }

    let text = escapeHtml(value)

    text = text.replace(
        /```([\s\S]+?)```/g,
        '<code class="rounded bg-black/10 px-1 py-0.5 font-mono text-[0.9em]">$1</code>'
    )

    text = wrap(text, "*", "strong")
    text = wrap(text, "_", "em")
    text = wrap(text, "~", "s")

    return text
}
