type DeskNode = {
    type: string
    text?: string
    marks?: { type: string; attrs?: Record<string, any> }[]
    attrs?: Record<string, any>
    content?: DeskNode[]
}

const MARKERS: [string, string][] = [
    ["code", "`"],
    ["bold", "*"],
    ["italic", "_"],
    ["strike", "~"],
]

const bare = (value: string) => value.replace(/^(https?:\/\/|mailto:)/i, "").replace(/^www\./i, "").replace(/\/$/, "").toLowerCase()

const sameAsHref = (text: string, href: string) => text === href || bare(text) === bare(href)

const SEPARATOR = "\uE000"

const mention = (node: DeskNode, tokens: boolean): string => {
    const char = node.attrs?.mentionSuggestionChar === "#" ? "#" : "@"
    const id = String(node.attrs?.id ?? "")
    if (!tokens) return `${char}${node.attrs?.label ?? id}`
    return SEPARATOR + (id.startsWith("!") ? id : char + id) + SEPARATOR
}

const clean = (text: string, tokens: boolean): string => (tokens ? text.replaceAll(SEPARATOR, "") : text)

const inline = (nodes: DeskNode[] = [], tokens = false): string =>
    nodes
        .map((node) => {
            if (node.type === "hardBreak") return "\n"
            if (node.type === "mention") return mention(node, tokens)
            const text = clean(node.text ?? "", tokens)
            const href = node.marks?.find((mark) => mark.type === "link")?.attrs?.href as string | undefined
            const shown = href && !sameAsHref(text.trim(), href) ? `${text} (${href})` : text
            const names = (node.marks ?? []).map((mark) => mark.type)
            const markers = MARKERS.filter(([name]) => names.includes(name)).map(([, marker]) => marker)
            if (!markers.length) return shown
            const [, lead, core, trail] = shown.match(/^(\s*)([\s\S]*?)(\s*)$/) ?? ["", "", shown, ""]
            if (!core) return shown
            return lead + markers.join("") + core + [...markers].reverse().join("") + trail
        })
        .join("")

const list = (node: DeskNode, tokens: boolean, depth = 0): string[] =>
    (node.content ?? []).flatMap((item, position) => {
        const prefix = "    ".repeat(depth) + (node.type === "bulletList" ? "• " : `${(node.attrs?.start ?? 1) + position}. `)
        return (item.content ?? []).flatMap((child, index) => {
            if (child.type === "bulletList" || child.type === "orderedList") return list(child, tokens, depth + 1)
            return [(index === 0 ? prefix : "    ".repeat(depth + 1)) + inline(child.content, tokens)]
        })
    })

const block = (node: DeskNode, tokens: boolean): string[] => {
    if (node.type === "bulletList" || node.type === "orderedList") return list(node, tokens)
    if (node.type === "codeBlock") return ["```\n" + (node.content ?? []).map((child) => clean(child.text ?? "", tokens)).join("") + "\n```"]
    if (node.type === "blockquote") return (node.content ?? []).flatMap((child) => block(child, tokens)).flatMap((line) => line.split("\n")).map((line) => `> ${line}`)
    return [inline(node.content, tokens)]
}

export const deskDocToSlackText = (doc: DeskNode, tokens = false): string => (doc.content ?? []).flatMap((node) => block(node, tokens)).join("\n").replace(/\s+$/, "")

export const mentionsEveryone = (text: string): boolean => text.includes(`${SEPARATOR}!here${SEPARATOR}`) || text.includes(`${SEPARATOR}!channel${SEPARATOR}`)
