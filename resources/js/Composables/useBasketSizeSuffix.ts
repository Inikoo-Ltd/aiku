const normaliseSize = (text: string): string =>
    text.toLowerCase().replace(/\s+/g, '').replace(/(litres?|liters?|ltrs?)/g, 'l')

const nameAlreadyHasSize = (name: string, sizeLabel: string): boolean => {
    const size = normaliseSize(sizeLabel).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

    return new RegExp(`(^|[^0-9.])${size}($|[^a-z0-9])`).test(normaliseSize(name))
}

export const basketSizeSuffix = (name?: string | null, sizeLabel?: string | null): string => {
    if (!sizeLabel || nameAlreadyHasSize(name ?? '', sizeLabel)) {
        return ''
    }

    return ` — ${sizeLabel}`
}

export const productNameWithPackAndSize = (product: { name: string, units?: number | string | null, size_label?: string | null }): string => {
    const units = Number(product.units) || 1
    const name = `${product.name}${basketSizeSuffix(product.name, product.size_label)}`

    return units > 1 ? `[${units}x] ${name}` : name
}
