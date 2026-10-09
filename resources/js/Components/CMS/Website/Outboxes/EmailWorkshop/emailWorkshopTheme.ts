import { DEFAULT_FONT_FAMILY, EmailJson, EmailModule, MODULE_TYPES } from './emailWorkshopBlocks'

export interface EmailTheme {
    color: string[]
    fontFamily: string | null
    source: 'website' | 'palette'
}

export interface WebsiteTheme {
    color?: string[]
    fontFamily?: string | null
}

const THEME_PRIMARY = 0
const THEME_ACCENT = 2
const THEME_BUTTON = 4
const THEME_BUTTON_TEXT = 5
const THEME_HIGHLIGHT = 6
const THEME_TEXT = 7

const EMAIL_SAFE_FONT_NAMES = ['arial', 'georgia', 'helvetica', 'tahoma', 'times new roman', 'trebuchet ms', 'verdana']

const themeColor = (theme: EmailTheme, index: number, fallback: string): string => theme.color?.[index] || fallback

export const primaryFontName = (fontFamily: string | null | undefined): string =>
    String(fontFamily ?? '').split(',')[0].replace(/['"]/g, '').trim()

export const emailFontFamily = (fontFamily: string | null | undefined): string => {
    const name = primaryFontName(fontFamily)
    if (!name) {
        return DEFAULT_FONT_FAMILY
    }

    return EMAIL_SAFE_FONT_NAMES.includes(name.toLowerCase())
        ? String(fontFamily)
        : `'${name}', Arial, Helvetica Neue, Helvetica, sans-serif`
}

const googleFontUrl = (name: string): string =>
    `https://fonts.googleapis.com/css2?family=${encodeURIComponent(name).replace(/%20/g, '+')}:wght@400;700&display=swap`

export const websiteThemeAsEmailTheme = (websiteTheme: WebsiteTheme | null | undefined): EmailTheme | null =>
    websiteTheme?.color?.length
        ? { color: [...websiteTheme.color], fontFamily: websiteTheme.fontFamily ?? null, source: 'website' }
        : null

const setColor = (style: Record<string, any> | undefined, color: string) => {
    if (style) {
        style.color = color
    }
}

export const applyThemeToModule = (module: EmailModule, theme: EmailTheme): void => {
    const descriptor = module.descriptor ?? {}
    const primary = themeColor(theme, THEME_PRIMARY, '#000000')
    const accent = themeColor(theme, THEME_ACCENT, '#0068A5')
    const text = themeColor(theme, THEME_TEXT, '#000000')

    switch (module.type) {
        case MODULE_TYPES.heading:
            setColor(descriptor.heading?.style, primary)
            if (descriptor.heading?.style) {
                descriptor.heading.style['link-color'] = accent
            }
            break
        case MODULE_TYPES.paragraph:
        case MODULE_TYPES.text:
        case MODULE_TYPES.list: {
            const key = module.type === MODULE_TYPES.paragraph ? 'paragraph' : module.type === MODULE_TYPES.text ? 'text' : 'list'
            setColor(descriptor[key]?.style, text)
            if (descriptor[key]) {
                descriptor[key].computedStyle = { ...descriptor[key].computedStyle, linkColor: accent }
            }
            break
        }
        case MODULE_TYPES.button:
            if (descriptor.button?.style) {
                descriptor.button.style['background-color'] = themeColor(theme, THEME_BUTTON, '#3AAEE0')
                descriptor.button.style.color = themeColor(theme, THEME_BUTTON_TEXT, '#ffffff')
            }
            break
        case MODULE_TYPES.divider:
            if (descriptor.divider?.style) {
                descriptor.divider.style['border-top'] = `1px solid ${themeColor(theme, THEME_HIGHLIGHT, '#BBBBBB')}`
            }
            break
        case MODULE_TYPES.video:
            if (descriptor.video) {
                descriptor.video.iconColor2 = themeColor(theme, THEME_BUTTON, '#000000')
                descriptor.video.iconColor1 = themeColor(theme, THEME_BUTTON_TEXT, '#ffffff')
            }
            break
    }
}

export const applyEmailTheme = (email: EmailJson, theme: EmailTheme): void => {
    const body = email.page.body
    const fontFamily = emailFontFamily(theme.fontFamily)
    const fontName = primaryFontName(fontFamily)

    email.page.aikuTheme = { color: [...theme.color], fontFamily: theme.fontFamily, source: theme.source }

    body.content.style['font-family'] = fontFamily
    body.content.style.color = themeColor(theme, THEME_TEXT, '#000000')
    body.content.computedStyle.linkColor = themeColor(theme, THEME_ACCENT, '#0068A5')

    if (!EMAIL_SAFE_FONT_NAMES.includes(fontName.toLowerCase())) {
        body.webFonts = [
            ...(body.webFonts ?? []).filter((font) => primaryFontName(font.fontFamily) !== fontName),
            { name: fontName, fontFamily, url: googleFontUrl(fontName) },
        ]
    }

    for (const row of email.page.rows) {
        for (const column of row.columns) {
            for (const module of column.modules) {
                applyThemeToModule(module, theme)
            }
        }
    }
}
