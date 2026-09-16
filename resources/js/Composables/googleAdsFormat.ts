/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

/*
 * Google never reports an impression share below 10% or above 90%: it sends 9.99% and 90.01% instead.
 * A period made only of such days lands on those values too, so they are printed as the bounds they
 * are rather than as measurements.
 */
export const impressionShareLabel = (value: number | null): string => {
    if (value === null) return "—"
    if (value < 10) return "< 10%"
    if (value > 90) return "> 90%"

    return value.toFixed(1) + "%"
}
