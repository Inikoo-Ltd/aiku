import { ctrans } from "@/Composables/useTrans"

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

const URL_PATTERN = /\b(?:https?:\/\/|www\.)[^\s<>"]+/gi
const TRAILING_PUNCTUATION = /[.,;:!?'")\]*_~]+$/

/**
 * Pulls the links out before escaping and markup, so an underscore or asterisk inside a URL
 * never opens a tag, and puts them back as anchors at the end. Trailing punctuation stays
 * outside the link, except a closing bracket the URL itself opened, as Wikipedia's do.
 */
const LABELLED_LINK = /\[([^[\]\n]+)\]\((https?:\/\/[^\s()<>"]+)\)/gi

type ExtractedLink = { url: string, label: string }

const extractLinks = (value: string, labelledLinks: boolean): [string, ExtractedLink[]] => {
    const links: ExtractedLink[] = []
    value = value.replace(/\u0000/g, "")
    if (labelledLinks) {
        value = value.replace(LABELLED_LINK, (_, label: string, url: string) => {
            links.push({ url, label })
            return `\u0000${links.length - 1}\u0000`
        })
    }

    const text = value.replace(URL_PATTERN, (match) => {
        let url = match.replace(TRAILING_PUNCTUATION, "")
        while (match.length > url.length && match[url.length] === ")" && url.split("(").length > url.split(")").length) {
            url += ")"
        }
        links.push({ url, label: url })
        return `\u0000${links.length - 1}\u0000${match.slice(url.length)}`
    })

    return [text, links]
}

const restoreLinks = (html: string, links: ExtractedLink[]) =>
    html.replace(/\u0000(\d+)\u0000/g, (_, index) => {
        const { url, label } = links[Number(index)]
        const href = /^www\./i.test(url) ? `https://${url}` : url
        return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer nofollow" class="underline break-all">${escapeHtml(label)}</a>`
    })

/**
 * Renders the markup WhatsApp itself understands, so a message written on a phone reads
 * the same in the inbox instead of showing its raw markers. __underline__ is ours, WhatsApp
 * has none, so it is only offered on website and email conversations. The email a customer
 * receives is rendered by SendChatMessageByGmail::markupToHtml, which must stay in step.
 */
export const formatWhatsappMarkup = (value?: string | null, labelledLinks = false): string => {
    if (!value) {
        return ""
    }

    const [withoutLinks, links] = extractLinks(stripPictureTokens(value), labelledLinks)
    let text = escapeHtml(withoutLinks)

    text = text.replace(
        /```([\s\S]+?)```/g,
        '<code class="rounded bg-black/10 px-1 py-0.5 font-mono text-[0.9em]">$1</code>'
    )

    text = wrap(text, "__", "u")
    text = wrap(text, "*", "strong")
    text = wrap(text, "_", "em")
    text = wrap(text, "~", "s")

    return restoreLinks(text, links)
}

const LIST_LINE = /^(• |\d+\. )/

/**
 * A picture pasted into an email reply, kept where it was pasted. SendChatMessageByGmail swaps
 * it for the picture itself; the bubble shows the file beside the text, so it drops the token.
 */
const PICTURE_TOKEN = /\[image:([A-Za-z0-9-]{1,64})\]\n?/g
const PICTURE_LINE = /^\[image:([A-Za-z0-9-]{1,64})\]$/

export const pictureToken = (pictureId: string) => `[image:${pictureId}]`

export const pictureTokenIds = (value?: string | null): string[] =>
    Array.from((value ?? "").matchAll(PICTURE_TOKEN), (match) => match[1])

export const stripPictureTokens = (value?: string | null, keep: string[] = []): string =>
    (value ?? "").replace(PICTURE_TOKEN, (token, pictureId) => keep.includes(pictureId) ? token : "")

export const markupToEditorHtml = (value?: string | null, pictureSource?: (pictureId: string) => string | undefined): string => {
    const lines = (value ?? "").split(/\r?\n/)
    let html = ""
    let index = 0

    while (index < lines.length) {
        const line = lines[index]
        const kind = line.startsWith("• ") ? "ul" : /^\d+\. /.test(line) ? "ol" : null
        const pictureId = line.match(PICTURE_LINE)?.[1]
        const pictureSrc = pictureId ? pictureSource?.(pictureId) : undefined

        if (pictureId && pictureSrc) {
            html += `<img src="${escapeHtml(pictureSrc)}" alt="${escapeHtml(ctrans("Pasted picture"))}" data-picture-id="${pictureId}">`
            index++
            continue
        }

        if (!kind) {
            html += `<p>${formatWhatsappMarkup(line, true)}</p>`
            index++
            continue
        }

        const start = kind === "ol" ? parseInt(line, 10) : 1
        html += start === 1 ? `<${kind}>` : `<${kind} start="${start}">`
        while (index < lines.length && (kind === "ul" ? lines[index].startsWith("• ") : /^\d+\. /.test(lines[index]))) {
            html += `<li><p>${formatWhatsappMarkup(lines[index].replace(LIST_LINE, ""), true)}</p></li>`
            index++
        }
        html += `</${kind}>`
    }

    return html
}

type EditorNode = {
    type: string
    text?: string
    marks?: { type: string, attrs?: Record<string, any> }[]
    attrs?: Record<string, any>
    content?: EditorNode[]
}

const MARKERS: [string, string][] = [
    ["underline", "__"],
    ["bold", "*"],
    ["italic", "_"],
    ["strike", "~"],
    ["code", "```"],
]

const linkHref = (node: EditorNode): string | null => {
    const href = node.marks?.find((mark) => mark.type === "link")?.attrs?.href
    const encoded = typeof href === "string" ? href.replace(/\(/g, "%28").replace(/\)/g, "%29") : ""
    return /^https?:\/\/[^\s<>"]+$/i.test(encoded) ? encoded : null
}

const markersOf = (node: EditorNode): string[] => {
    const names = (node.marks ?? []).map((mark) => mark.type)
    return MARKERS.filter(([name]) => names.includes(name)).map(([, marker]) => marker)
}

const inlineToMarkup = (nodes: EditorNode[] = []): string => {
    let output = ""
    let open: string[] = []
    let pendingSpace = ""

    const moveTo = (wanted: string[]) => {
        let shared = 0
        while (shared < open.length && open[shared] === wanted[shared]) shared++
        for (let i = open.length - 1; i >= shared; i--) output += open[i]
        output += pendingSpace
        pendingSpace = ""
        return shared
    }

    for (let index = 0; index < nodes.length; index++) {
        const node = nodes[index]
        const href = linkHref(node)

        if (href) {
            let label = node.text ?? ""
            while (index + 1 < nodes.length && linkHref(nodes[index + 1]) === href) {
                label += nodes[++index].text ?? ""
            }
            const [, lead, core, trail] = label.replace(/[[\]\n]/g, "").match(/^(\s*)([\s\S]*?)(\s*)$/) ?? ["", "", label, ""]
            const wanted = markersOf(node)
            const shared = moveTo(wanted)
            output += lead
            wanted.slice(shared).forEach((marker) => (output += marker))
            const isBareUrl = core === href || (/^www\./i.test(core) && `https://${core}` === href)
            output += !core ? href : isBareUrl ? core : `[${core}](${href})`
            open = wanted
            pendingSpace = trail
            continue
        }

        if (node.type === "hardBreak") {
            moveTo([])
            open = []
            output += "\n"
            continue
        }

        const text = node.text ?? ""
        const [, lead, core, trail] = text.match(/^(\s*)([\s\S]*?)(\s*)$/) ?? ["", "", text, ""]

        if (!core) {
            pendingSpace += text
            continue
        }

        const wanted = markersOf(node)
        const shared = moveTo(wanted)

        output += lead
        wanted.slice(shared).forEach((marker) => (output += marker))
        output += core
        open = wanted
        pendingSpace = trail
    }

    moveTo([])

    return output
}

/**
 * The markup has one level of list and no picture inside a list line. A nested list (Tab in a
 * list item) comes out as lines of its own, and a picture in an item after the item's line:
 * leaving either out lost it at send while the editor still showed it (INI-144).
 */
const listToMarkup = (list: EditorNode): string[] =>
    (list.content ?? []).flatMap((item, position) => {
        const prefix = list.type === "bulletList" ? "• " : `${(list.attrs?.start ?? 1) + position}. `
        const children = item.content ?? []
        const text = children
            .filter((child) => child.type !== "image" && child.type !== "bulletList" && child.type !== "orderedList")
            .map((child) => inlineToMarkup(child.content))
            .join(" ")

        return [
            prefix + text,
            ...children.flatMap((child) => {
                if (child.type === "bulletList" || child.type === "orderedList") return listToMarkup(child)
                if (child.type === "image" && /^[A-Za-z0-9-]{1,64}$/.test(child.attrs?.pictureId ?? "")) return [pictureToken(child.attrs!.pictureId)]

                return []
            }),
        ]
    })

export const editorDocToMarkup = (doc: EditorNode): string =>
    (doc.content ?? [])
        .flatMap((block) => {
            if (block.type === "bulletList" || block.type === "orderedList") {
                return listToMarkup(block)
            }

            if (block.type === "image") {
                return /^[A-Za-z0-9-]{1,64}$/.test(block.attrs?.pictureId ?? "") ? [pictureToken(block.attrs!.pictureId)] : []
            }

            return [inlineToMarkup(block.content)]
        })
        .join("\n")
