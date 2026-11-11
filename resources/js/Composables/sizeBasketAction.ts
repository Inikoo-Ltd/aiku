export interface SizeQuantityChange {
    size: string
    selected: number
    basket: number
}

export interface TranslatableText {
    text: string
    replacements: Record<string, string>
}

const text = (value: string, replacements: Record<string, string> = {}): TranslatableText => ({ text: value, replacements })

export const pendingChanges = (lines: SizeQuantityChange[]): SizeQuantityChange[] =>
    lines.filter((line) => line.selected !== line.basket)

export const sizeBasketButtonLabel = (lines: SizeQuantityChange[]): TranslatableText => {
    const changes = pendingChanges(lines)

    if (!changes.length) {
        return lines.some((line) => line.basket > 0)
            ? text("Change a quantity to update your basket")
            : text("Choose a quantity to add to basket")
    }

    if (changes.length === 1) {
        const [change] = changes

        if (change.basket === 0) {
            return text("Add :quantity × :size to basket", { quantity: String(change.selected), size: change.size })
        }

        if (change.selected === 0) {
            return text("Remove :size from basket", { size: change.size })
        }

        return text("Change :size to :quantity in basket", { quantity: String(change.selected), size: change.size })
    }

    if (changes.every((change) => change.basket === 0)) {
        const containers = changes.reduce((total, change) => total + change.selected, 0)

        return text("Add :containers containers in :sizes sizes to basket", {
            containers: String(containers),
            sizes: String(changes.length)
        })
    }

    return text("Update :sizes sizes in basket", { sizes: String(changes.length) })
}

export const pendingChangeLabel = (change: SizeQuantityChange): string => {
    const difference = change.selected - change.basket

    return `${difference > 0 ? "+" : "−"}${Math.abs(difference)} × ${change.size}`
}

export type HeldQuantityDirection = "none" | "add" | "increase" | "decrease" | "remove"

export const heldQuantityChange = (basket: number, selected: number, size?: string | null): { direction: HeldQuantityDirection, label: TranslatableText } => {
    const quantity = String(selected)

    if (selected === basket) {
        return { direction: "none", label: text(basket > 0 ? "Update basket" : "Add to basket") }
    }

    if (basket <= 0) {
        return {
            direction: "add",
            label: size ? text("Add :quantity × :size to basket", { quantity, size }) : text("Add :quantity to basket", { quantity })
        }
    }

    if (selected <= 0) {
        return { direction: "remove", label: size ? text("Remove :size from basket", { size }) : text("Remove from basket") }
    }

    if (selected > basket) {
        const more = String(selected - basket)

        return {
            direction: "increase",
            label: size
                ? text("Add :more more (:quantity × :size in basket)", { more, quantity, size })
                : text("Add :more more (:quantity in basket)", { more, quantity })
        }
    }

    return {
        direction: "decrease",
        label: size ? text("Reduce basket to :quantity × :size", { quantity, size }) : text("Reduce basket to :quantity", { quantity })
    }
}
