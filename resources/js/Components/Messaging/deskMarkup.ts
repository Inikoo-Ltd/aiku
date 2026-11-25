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

const MENTION_TOKEN = /^(@U[A-Z0-9]{2,20}|#C[A-Z0-9]{2,20}|!here|!channel)$/

type DeskLabels = Record<string, string>

/** A stored Slack message as the box holds it: entities undone, and a mention that can be a chip written as one; anything else stays as Slack had it. */
export const slackTextToBox = (stored: string, people: { id: string }[] = [], channels: { id: string }[] = []): string => {
    const known = new Set([...people.map((person) => (person.id.startsWith("!") ? person.id : `@${person.id}`)), ...channels.map((channel) => `#${channel.id}`)])
    return stored
        .split(/(<[^<>]*>)/)
        .map((part, index) => {
            if (index % 2) {
                const code = part.slice(1, -1)
                return MENTION_TOKEN.test(code) && known.has(code) ? SEPARATOR + code + SEPARATOR : part
            }
            return part.replaceAll("&lt;", "<").replaceAll("&gt;", ">").replaceAll("&amp;", "&")
        })
        .join("")
}

/** The editor's paragraphs for a box text: chips become mention nodes, labelled from the people and channels the box knows. */
const STYLED = /`([^`\n]+)`|(?<![\w*])\*([^*\s](?:[^*\n]*[^*\s])?)\*(?![\w*])|(?<![\w_])_([^_\s](?:[^_\n]*[^_\s])?)_(?![\w_])|(?<![\w~])~([^~\s](?:[^~\n]*[^~\s])?)~(?![\w~])/g
const STYLED_MARKS = ["code", "bold", "italic", "strike"]

const styledText = (text: string, marks: { type: string }[] = []): DeskNode[] => {
    const nodes: DeskNode[] = []
    let from = 0
    for (const match of text.matchAll(STYLED)) {
        const at = STYLED_MARKS[[1, 2, 3, 4].findIndex((group) => match[group] !== undefined)]
        if (match.index > from) nodes.push({ type: "text", text: text.slice(from, match.index), ...(marks.length ? { marks } : {}) })
        nodes.push(...(at === "code" ? [{ type: "text", text: match[1], marks: [...marks, { type: at }] }] : styledText(match[[1, 2, 3, 4][STYLED_MARKS.indexOf(at)]], [...marks, { type: at }])))
        from = match.index + match[0].length
    }
    if (from < text.length) nodes.push({ type: "text", text: text.slice(from), ...(marks.length ? { marks } : {}) })
    return nodes
}

export const boxTextToDoc = (text: string, people: { id: string; label: string }[] = [], channels: { id: string; label: string }[] = [], styled = false) => {
    const labels: DeskLabels = {}
    people.forEach((person) => (labels[person.id.startsWith("!") ? person.id : `@${person.id}`] = person.label))
    channels.forEach((channel) => (labels[`#${channel.id}`] = channel.label))

    return text.split("\n").map((line) => ({
        type: "paragraph",
        content: line
            .split(new RegExp(`${SEPARATOR}([^${SEPARATOR}]*)${SEPARATOR}`))
            .flatMap((part, index) => {
                if (!part) return []
                if (index % 2 === 0 || !MENTION_TOKEN.test(part)) return styled ? styledText(part) : [{ type: "text", text: part }]
                const id = part.startsWith("!") ? part : part.slice(1)
                return [{ type: "mention", attrs: { id, label: labels[part] ?? id, mentionSuggestionChar: part[0] === "#" ? "#" : "@" } }]
            }),
    }))
}
