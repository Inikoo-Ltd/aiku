/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

export type WordDiffPart = { text: string; type: "same" | "added" | "removed" }

const MAX_CELLS = 4_000_000

/**
 * Word level diff (longest common subsequence over words and the spaces between them). Returns null when the texts are
 * too long to compare in the browser, so the caller can fall back to showing before and after side by side.
 */
export const wordDiff = (before: string, after: string): WordDiffPart[] | null => {
    const from = before.split(/(\s+)/).filter((token) => token !== "")
    const to = after.split(/(\s+)/).filter((token) => token !== "")

    if ((from.length + 1) * (to.length + 1) > MAX_CELLS) return null

    const common = Array.from({ length: from.length + 1 }, () => new Uint32Array(to.length + 1))
    for (let i = from.length - 1; i >= 0; i--) {
        for (let j = to.length - 1; j >= 0; j--) {
            common[i][j] = from[i] === to[j] ? common[i + 1][j + 1] + 1 : Math.max(common[i + 1][j], common[i][j + 1])
        }
    }

    const parts: WordDiffPart[] = []
    const push = (text: string, type: WordDiffPart["type"]) => {
        const last = parts[parts.length - 1]
        if (last?.type === type) last.text += text
        else parts.push({ text, type })
    }

    let i = 0
    let j = 0
    while (i < from.length && j < to.length) {
        if (from[i] === to[j]) {
            push(from[i], "same")
            i++
            j++
        } else if (common[i + 1][j] >= common[i][j + 1]) {
            push(from[i++], "removed")
        } else {
            push(to[j++], "added")
        }
    }
    while (i < from.length) push(from[i++], "removed")
    while (j < to.length) push(to[j++], "added")

    return parts
}
