export const roundQuantity = (value: number) => Math.round(Number(value) * 1e6) / 1e6

export const splitQuantity = (value: number, packedIn: number) => {
    const units = Math.round(roundQuantity(Number(value ?? 0)) * packedIn)
    const whole = Math.trunc(units / packedIn)

    return { whole, numerator: units - whole * packedIn }
}

// Stock is stored rounded (2 packs of 6 as 1.999666), so untouched inputs keep the stored value
// instead of drifting and flagging the row as modified.
export const auditedQuantity = (storedQuantity: number, whole: number, numerator: number, packedIn: number) => {
    const original = splitQuantity(storedQuantity, packedIn)

    if (whole === original.whole && numerator === original.numerator) {
        return Number(storedQuantity)
    }

    return roundQuantity(whole + numerator / packedIn)
}
