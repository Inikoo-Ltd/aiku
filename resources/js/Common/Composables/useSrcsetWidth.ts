export function widestSrcsetWidth(srcset?: string | null): number | undefined {
    const widths = [...(srcset ?? '').matchAll(/\s(\d+)w(?=\s*,|\s*$)/g)].map((match) => Number(match[1]))

    return widths.length ? Math.max(...widths) : undefined
}
