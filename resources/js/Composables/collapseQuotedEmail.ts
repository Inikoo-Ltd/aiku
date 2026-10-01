import { ctrans } from "@/Composables/useTrans"

const QUOTE_CONTAINERS = ".gmail_quote, .yahoo_quoted"

const NOT_NEW_WORDS = "blockquote, .gmail_quote, .yahoo_quoted, .gmail_attr, .gmail_signature, .gmail_signature_prefix"

const FORWARDED = /^\s*-{3,}\s*(Forwarded message|Mensaje reenviado|Weitergeleitete Nachricht|Message transféré|Messaggio inoltrato|Preposlaná správa|Přeposlaná zpráva)/i

const LINE_BREAKS = new Set(["BR", "P", "DIV", "LI", "UL", "OL", "TR", "TD", "TABLE", "H1", "H2", "H3", "H4", "H5", "H6", "BLOCKQUOTE", "HR", "PRE"])

const BLOCKS = [...LINE_BREAKS].filter((tag) => tag !== "BR").join(",")

const STARTER = /^\s*(On|El|Am|Le|Il|Op|Dňa|Dne|W dniu|From|De|Von|Od|Da|Van|Från|Fra|-{2,})/

type QuoteHeader = { matches: RegExp; isReply: boolean; unless?: RegExp }

const QUOTE_HEADERS: QuoteHeader[] = [
    {
        matches: /^\s*((On|El|Am|Le|Il|Op|Dňa|Dne|W dniu)\s[^\n]*\d[^\n]*(wrote|escribió|schrieb|a écrit|ha scritto|schreef|napísal|napsal|napisał)\s*:)\s*(\n|$)/i,
        isReply: true,
    },
    {
        matches: /^\s*(From|De|Von|Od|Da|Van|Från|Fra)\s*:[^\n]*(\n\s*(Sent|Enviado|Gesendet|Odoslané|Odesláno|Envoyé|Inviato|Verzonden|Skickat|Sendt|Date|Fecha|Datum|To|Para|An|Komu|À|A|Aan|Till|Til|Cc|Subject|Asunto|Betreff|Predmet|Předmět|Objet|Oggetto|Onderwerp|Ämne|Emne)\s*:[^\n]*){2}/,
        isReply: false,
        unless: /\n\s*(Subject|Asunto|Betreff|Predmet|Předmět|Objet|Oggetto|Onderwerp|Ämne|Emne)\s*:\s*(FW|Fwd|WG|RV|TR|PD|Fw)\s*:/i,
    },
    {
        matches: /^\s*-{2,}\s*(Original Message|Mensaje original|Ursprüngliche Nachricht|Pôvodná správa|Původní zpráva)/i,
        isReply: false,
    },
]

const isElement = (node: Node): node is Element => node.nodeType === Node.ELEMENT_NODE

const isForward = (element: Element): boolean =>
    element.matches(".gmail_quote") && FORWARDED.test((element.textContent ?? "").slice(0, 200))

const linesFrom = (page: Document, node: Node, length: number): string => {
    const walker = page.createTreeWalker(page.body, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT)
    walker.currentNode = node

    const blockOf = (inner: Node): Element | null | undefined => inner.parentElement?.closest(BLOCKS)

    let text = node.textContent ?? ""
    let block = blockOf(node)

    for (let next = walker.nextNode(); next && text.length < length; next = walker.nextNode()) {
        if (isElement(next)) {
            text += LINE_BREAKS.has(next.tagName) ? "\n" : ""
        } else {
            text += (blockOf(next) !== block ? "\n" : "") + (next.textContent ?? "")
            block = blockOf(next)
        }
    }

    return text.slice(0, length)
}

const isNewWords = (node: Node, start: Node): boolean => {
    const quoted = node.parentElement?.closest(NOT_NEW_WORDS)

    return !quoted || (quoted.contains(start) && !quoted.matches("blockquote, .yahoo_quoted"))
}

const hasNewWordsFrom = (page: Document, start: Node, header = ""): boolean => {
    const walker = page.createTreeWalker(page.body, NodeFilter.SHOW_TEXT)
    walker.currentNode = start

    let text = start.nodeType === Node.TEXT_NODE ? start.textContent ?? "" : ""

    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (isNewWords(node, start)) {
            text += node.textContent ?? ""
        }

        if (text.length > header.length && text.replace(header, "").trim() !== "") {
            return true
        }
    }

    return text.replace(header, "").trim() !== ""
}

const startsWith = (page: Document, element: Element, node: Node): boolean => {
    const range = page.createRange()
    range.setStart(element, 0)
    range.setEndBefore(node)

    return range.toString().trim() === ""
}

const headerStart = (page: Document, node: Node): Node | null => {
    const text = node.textContent ?? ""

    if (!STARTER.test(text)) {
        return null
    }

    const lines = linesFrom(page, node, 400)
    const header = QUOTE_HEADERS.find(({ matches, unless }) => matches.test(lines) && !unless?.test(lines))

    if (!header) {
        return null
    }

    const parent = node.parentElement
    const quote = parent?.closest("blockquote")
    const start = quote && startsWith(page, quote, node) ? quote : parent && parent !== page.body && parent.textContent?.trim() === text.trim() ? parent : node

    if (header.isReply && hasNewWordsFrom(page, start, lines.match(header.matches)?.[1] ?? "")) {
        return null
    }

    return start
}

const quoteStart = (page: Document): Node | null => {
    const walker = page.createTreeWalker(page.body, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
        acceptNode: (node) => (isElement(node) && isForward(node) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
    })

    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (isElement(node)) {
            if (node.matches(QUOTE_CONTAINERS) && !hasNewWordsFrom(page, node)) {
                return node
            }
        } else {
            const start = headerStart(page, node)

            if (start) {
                return start
            }
        }
    }

    return null
}

export const collapseQuotedEmail = (page: Document): void => {
    const body = page.body

    if (!body || body.querySelector(":scope > details[data-quoted]")) {
        return
    }

    const start = quoteStart(page)

    if (!start) {
        return
    }

    const before = page.createRange()
    before.setStart(body, 0)
    before.setEndBefore(start)

    if (before.toString().trim() === "") {
        return
    }

    const quoted = page.createRange()
    quoted.setStartBefore(start)
    quoted.setEnd(body, body.childNodes.length)

    const details = page.createElement("details")
    details.setAttribute("data-quoted", "")

    const summary = page.createElement("summary")
    summary.textContent = "•••"
    summary.title = ctrans("Show quoted text")
    summary.style.cssText = "display:inline-block;cursor:pointer;list-style:none;padding:0 8px;margin:8px 0;border-radius:4px;background:#e5e7eb;color:#4b5563;font-size:12px;line-height:18px;"

    details.append(summary, quoted.extractContents())
    body.append(details)

    details.querySelectorAll(".gmail_signature_prefix, .gmail_signature").forEach((signature) => {
        if (!signature.parentElement?.closest("blockquote, .gmail_quote")) {
            body.append(signature)
        }
    })
}
