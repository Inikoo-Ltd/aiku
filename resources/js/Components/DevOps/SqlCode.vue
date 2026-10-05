<script setup lang="ts">
import { computed } from "vue"

const props = defineProps<{ sql: string, compact?: boolean }>()

const KEYWORDS = new Set(("select from where and or not in is null as on join left right inner outer full cross lateral group by order having limit offset "
    + "insert into values update set delete returning union all distinct case when then else end exists between like ilike asc desc with "
    + "conflict do nothing coalesce count sum min max avg true false over partition").split(" "))
const BREAK_BEFORE = new Set(["from", "where", "group", "order", "having", "limit", "offset", "values", "set", "returning", "union", "left", "right", "inner", "join", "on"])
const INDENT_BEFORE = new Set(["and", "or"])

type Token = { kind: "keyword" | "string" | "identifier" | "number" | "binding" | "text" | "newline", text: string }

const tokens = computed<Token[]>(() => {
    const result: Token[] = []
    const pattern = /('(?:[^']|'')*')|("(?:[^"]|"")*")|(\b\d+(?:\.\d+)?\b)|(\?|\$\d+)|([A-Za-z_][A-Za-z0-9_]*)|(\s+)|(.)/g
    let previousWord = ""
    for (const match of props.sql.matchAll(pattern)) {
        const [text, string, identifier, number, binding, word, space] = match
        if (string) {
            result.push({ kind: "string", text })
        } else if (identifier) {
            result.push({ kind: "identifier", text })
        } else if (number) {
            result.push({ kind: "number", text })
        } else if (binding) {
            result.push({ kind: "binding", text })
        } else if (word) {
            const lower = word.toLowerCase()
            const isKeyword = KEYWORDS.has(lower)
            const joinsPrevious = lower === "join" && ["left", "right", "inner", "outer", "cross", "full"].includes(previousWord)
            if (!props.compact && isKeyword && !joinsPrevious && result.length && (BREAK_BEFORE.has(lower) || INDENT_BEFORE.has(lower))) {
                while (result.length && result[result.length - 1].kind === "text" && !result[result.length - 1].text.trim()) {
                    result.pop()
                }
                result.push({ kind: "newline", text: INDENT_BEFORE.has(lower) ? "\n    " : "\n" })
            }
            result.push({ kind: isKeyword ? "keyword" : "text", text: isKeyword ? word.toUpperCase() : word })
            previousWord = lower
        } else if (space) {
            result.push({ kind: "text", text: result.length && result[result.length - 1].kind === "newline" ? "" : " " })
        } else {
            result.push({ kind: "text", text })
        }
    }
    return result
})

const classes: Record<Token["kind"], string> = {
    keyword: "text-sky-700 font-semibold",
    string: "text-emerald-700",
    identifier: "text-violet-700",
    number: "text-amber-700",
    binding: "text-rose-600 font-semibold",
    text: "text-gray-800",
    newline: "",
}
</script>

<template>
    <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-5" :class="compact ? 'line-clamp-2' : ''"><span v-for="(token, index) in tokens" :key="index" :class="classes[token.kind]">{{ token.text }}</span></pre>
</template>
