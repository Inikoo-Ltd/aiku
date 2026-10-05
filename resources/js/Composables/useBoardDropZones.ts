/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

import { ref } from "vue"

const glowColours: Record<string, string> = {
    gray: "156 163 175",
    blue: "96 165 250",
    green: "34 197 94",
    red: "248 113 113",
    amber: "251 191 36",
}

const glowFor = (colour: string) => {
    const rgb = glowColours[colour] ?? glowColours.gray
    return `0 0 0 1px rgb(${rgb} / 0.35), 0 0 16px 2px rgb(${rgb} / 0.22)`
}

export const useBoardDropZones = <Item>(canDrop: (item: Item, fromColumn: string, toColumn: string) => boolean) => {
    const dragged = ref<{ item: Item; fromColumn: string } | null>(null)

    const startDrag = (item: Item | undefined, fromColumn: string) => {
        dragged.value = item ? { item, fromColumn } : null
    }

    const endDrag = () => {
        dragged.value = null
    }

    const dropZone = (column: string, colour: string): { class: string; style: Record<string, string> } => {
        const current = dragged.value
        if (!current || current.fromColumn === column) {
            return { class: "", style: {} }
        }

        return canDrop(current.item, current.fromColumn, column)
            ? { class: "", style: { boxShadow: glowFor(colour) } }
            : { class: "opacity-50 grayscale", style: {} }
    }

    return { startDrag, endDrag, dropZone }
}
