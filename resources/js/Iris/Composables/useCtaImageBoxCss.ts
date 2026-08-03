/*
 * CTA blocks store per-device image box heights in CMS properties.dimension;
 * inlining them via getStyles(screenType) means SSR renders the desktop height
 * and the box collapses/grows when screenType settles after hydration (Varnish
 * shares one cache across devices). Emit the heights as per-block CSS with the
 * 640/1024 breakpoints instead, so the box is viewport-correct at first paint.
 */
export function ctaImageBoxCss(blockDomId: string, dimension: any): string | null {
    if (!dimension || typeof dimension !== "object") return null

    const heightFor = (screen: string): string | null => {
        const d = dimension[screen] ?? dimension.desktop
        const value = d?.height?.value
        const unit = d?.height?.unit || "px"
        if (value === null || value === undefined || unit === "%") return null
        return `${value}${unit}`
    }

    const mobile = heightFor("mobile")
    const tablet = heightFor("tablet")
    const desktop = heightFor("desktop")
    if (!mobile && !tablet && !desktop) return null

    const rule = (h: string | null) => (h ? `#${blockDomId} .cta-image-slot{height:${h}}` : "")

    return rule(mobile)
        + `@media(min-width:640px){${rule(tablet ?? desktop)}}`
        + `@media(min-width:1024px){${rule(desktop)}}`
}
