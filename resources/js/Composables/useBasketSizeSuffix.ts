export const basketSizeSuffix = (name?: string | null, sizeLabel?: string | null): string => {
    if (!sizeLabel || (name ?? '').trim().toLowerCase().endsWith(sizeLabel.toLowerCase())) {
        return ''
    }

    return ` — ${sizeLabel}`
}
