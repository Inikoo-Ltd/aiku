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

const inline = (nodes: DeskNode[] = []): string =>
    nodes
        .map((node) => {
            if (node.type === "hardBreak") return "\n"
            if (node.type === "mention") return `@${node.attrs?.label ?? node.attrs?.id ?? ""}`
            const text = node.text ?? ""
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

const list = (node: DeskNode, depth = 0): string[] =>
    (node.content ?? []).flatMap((item, position) => {
        const prefix = "    ".repeat(depth) + (node.type === "bulletList" ? "• " : `${(node.attrs?.start ?? 1) + position}. `)
        return (item.content ?? []).flatMap((child, index) => {
            if (child.type === "bulletList" || child.type === "orderedList") return list(child, depth + 1)
            return [(index === 0 ? prefix : "    ".repeat(depth + 1)) + inline(child.content)]
        })
    })

const block = (node: DeskNode): string[] => {
    if (node.type === "bulletList" || node.type === "orderedList") return list(node)
    if (node.type === "codeBlock") return ["```\n" + (node.content ?? []).map((child) => child.text ?? "").join("") + "\n```"]
    if (node.type === "blockquote") return (node.content ?? []).flatMap(block).flatMap((line) => line.split("\n")).map((line) => `> ${line}`)
    return [inline(node.content)]
}

export const deskDocToSlackText = (doc: DeskNode): string => (doc.content ?? []).flatMap(block).join("\n").replace(/\s+$/, "")
