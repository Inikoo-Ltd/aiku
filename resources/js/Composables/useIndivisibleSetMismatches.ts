import { computed } from "vue"

export interface IndivisibleSetRow {
    id: number
    state?: string
    is_handled?: boolean
    org_stock_code: string
    quantity_required: number | string
    quantity_picked: number | string | null
    indivisible_set?: {
        transaction_id: number
        sets_ordered: number
        product: { code: string, name: string }
    } | null
}

export interface IncompleteIndivisibleSet {
    transactionId: number
    product: { code: string, name: string }
    setsOrdered: number
    completeSets: number
    partsToPutBack: { id: number, code: string, quantity: number }[]
}

const tolerance = 0.000001

const findIncompleteSet = (parts: IndivisibleSetRow[]): IncompleteIndivisibleSet | null => {
    const set = parts[0].indivisible_set!
    const setsOrdered = Number(set.sets_ordered)
    const handledParts = parts.filter((part) => part.is_handled ?? true)

    if (setsOrdered <= 0 || !handledParts.length) {
        return null
    }

    const setsPickedPerPart = handledParts.map((part) => Number(part.quantity_picked ?? 0) / Number(part.quantity_required) * setsOrdered)
    const completeSets = Math.floor(Math.min(...setsPickedPerPart) + tolerance)

    if (completeSets >= setsOrdered) {
        return null
    }

    const partsToPutBack = parts
        .map((part) => ({
            id: part.id,
            code: part.org_stock_code,
            quantity: Number(part.quantity_picked ?? 0) - Number(part.quantity_required) * completeSets / setsOrdered,
        }))
        .filter((part) => part.quantity > tolerance)

    if (!partsToPutBack.length) {
        return null
    }

    return {
        transactionId: set.transaction_id,
        product: set.product,
        setsOrdered,
        completeSets,
        partsToPutBack,
    }
}

export const useIndivisibleSetMismatches = (rows: () => IndivisibleSetRow[]) => {
    const incompleteSets = computed<IncompleteIndivisibleSet[]>(() => {
        const partsByTransaction = new Map<number, IndivisibleSetRow[]>()

        rows()
            .filter((row) => row.indivisible_set && row.state !== 'cancelled' && Number(row.quantity_required) > 0)
            .forEach((row) => {
                const transactionId = row.indivisible_set!.transaction_id
                partsByTransaction.set(transactionId, [...(partsByTransaction.get(transactionId) ?? []), row])
            })

        return [...partsByTransaction.values()]
            .map(findIncompleteSet)
            .filter((set): set is IncompleteIndivisibleSet => set !== null)
    })

    const quantityToPutBackByItem = computed(() => new Map(
        incompleteSets.value.flatMap((set) => set.partsToPutBack.map((part) => [part.id, part.quantity] as const))
    ))

    const quantityToPutBack = (itemId: number): number => quantityToPutBackByItem.value.get(itemId) ?? 0

    const incompleteSetFor = (transactionId?: number): IncompleteIndivisibleSet | undefined =>
        incompleteSets.value.find((set) => set.transactionId === transactionId)

    return { incompleteSets, quantityToPutBack, incompleteSetFor }
}
