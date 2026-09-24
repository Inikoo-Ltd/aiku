/**
 * The colour a user picks for an organisation is a single colour: the left navigation background.
 * The navigation styling needs a small palette around it (idle text, active item, text on it), so
 * the rest is derived here, keeping the same slot order the app themes use.
 */

const HEX_COLOUR = /^#[0-9a-f]{6}$/i

type Rgb = { r: number, g: number, b: number }

const toRgb = (colour: string): Rgb => ({
    r: parseInt(colour.slice(1, 3), 16),
    g: parseInt(colour.slice(3, 5), 16),
    b: parseInt(colour.slice(5, 7), 16),
})

const toHex = ({ r, g, b }: Rgb) => '#' + [r, g, b].map(channel => Math.round(channel).toString(16).padStart(2, '0')).join('')

const mix = (colour: string, towards: string, weight: number) => {
    const from = toRgb(colour)
    const to = toRgb(towards)

    return toHex({
        r: from.r + (to.r - from.r) * weight,
        g: from.g + (to.g - from.g) * weight,
        b: from.b + (to.b - from.b) * weight,
    })
}

const luminance = (colour: string) => {
    const { r, g, b } = toRgb(colour)
    const channels = [r, g, b].map(channel => {
        const ratio = channel / 255

        return ratio <= 0.03928 ? ratio / 12.92 : Math.pow((ratio + 0.055) / 1.055, 2.4)
    })

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]
}

const contrast = (first: string, second: string) => {
    const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a)

    return (lighter + 0.05) / (darker + 0.05)
}

const readableOn = (colour: string) => (contrast(colour, '#ffffff') >= contrast(colour, '#1f2937') ? '#ffffff' : '#1f2937')

export const buildNavigationTheme = (colour?: string | null): string[] | null => {
    if (!colour || !HEX_COLOUR.test(colour)) {
        return null
    }

    const background = colour.toLowerCase()
    const isLightBackground = luminance(background) > 0.45
    const text = readableOn(background)
    const activeBackground = isLightBackground ? mix(background, '#000000', 0.28) : mix(background, '#ffffff', 0.24)
    const activeText = readableOn(activeBackground)

    return [
        background,
        text,
        activeBackground,
        activeText,
        background,
        text,
        mix(text, background, 0.35),
        activeBackground,
    ]
}
