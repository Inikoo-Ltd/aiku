import { v4 as uuidv4 } from 'uuid'

export type CssStyle = Record<string, string | number | null | undefined>

export interface EmailModule {
    type: string
    uuid?: string
    locked?: boolean
    descriptor: Record<string, any>
    [key: string]: any
}

export interface EmailColumn {
    uuid?: string
    style?: CssStyle
    modules: EmailModule[]
    'grid-columns': number
    [key: string]: any
}

export interface EmailRow {
    type?: string
    uuid?: string
    content: { style: CssStyle, computedStyle?: Record<string, any>, [key: string]: any }
    container: { style: CssStyle, [key: string]: any }
    columns: EmailColumn[]
    [key: string]: any
}

export interface EmailBody {
    type?: string
    container: { style: CssStyle }
    content: { style: CssStyle, computedStyle: Record<string, any> }
    webFonts?: Array<{ name: string, fontFamily: string, url: string | null }>
    [key: string]: any
}

export interface MailshotMetadata {
    subject: string
    name: string | null
    preview_text: string | null
}

export interface EmailJson {
    page: {
        body: EmailBody
        favicon?: string | null
        rows: EmailRow[]
        title?: string | null
        description?: string | null
        template?: Record<string, any>
        [key: string]: any
    }
    comments?: Record<string, any>
    [key: string]: any
}

export const MODULE_PREFIX = 'mailup-bee-newsletter-modules-'

export const MODULE_TYPES = {
    heading: `${MODULE_PREFIX}heading`,
    paragraph: `${MODULE_PREFIX}paragraph`,
    text: `${MODULE_PREFIX}text`,
    list: `${MODULE_PREFIX}list`,
    image: `${MODULE_PREFIX}image`,
    button: `${MODULE_PREFIX}button`,
    divider: `${MODULE_PREFIX}divider`,
    spacer: `${MODULE_PREFIX}spacer`,
    social: `${MODULE_PREFIX}social`,
    icons: `${MODULE_PREFIX}icons`,
    video: `${MODULE_PREFIX}video`,
    html: `${MODULE_PREFIX}html`,
    mergeContent: `${MODULE_PREFIX}merge-content`,
    empty: `${MODULE_PREFIX}empty`,
} as const

export const INLINE_EDITABLE_TYPES: string[] = [
    MODULE_TYPES.heading,
    MODULE_TYPES.paragraph,
    MODULE_TYPES.text,
    MODULE_TYPES.list,
    MODULE_TYPES.button,
]

export const UNSUBSCRIBE_BLOCK = 'aiku-unsubscribe'
export const UNSUBSCRIBE_URL_TAG = '[Unsubscribe Url]'

export const DEFAULT_FONT_FAMILY = 'Arial, Helvetica Neue, Helvetica, sans-serif'
export const DEFAULT_MESSAGE_WIDTH = '650px'

export const shortModuleType = (type: string): string => type.replace(MODULE_PREFIX, '')

const defaultPadding = (padding = '10px'): CssStyle => ({
    'padding-top': padding,
    'padding-right': padding,
    'padding-bottom': padding,
    'padding-left': padding,
})

const defaultTextStyle = (): CssStyle => ({
    color: '#000000',
    direction: 'ltr',
    'font-size': '14px',
    'text-align': 'left',
    'font-family': 'inherit',
    'font-weight': '400',
    'line-height': '120%',
    'letter-spacing': '0px',
})

const moduleDescriptorFactories: Record<string, () => Record<string, any>> = {
    [MODULE_TYPES.heading]: () => ({
        style: { width: '100%', 'text-align': 'center', ...defaultPadding() },
        heading: {
            text: 'Heading',
            title: 'h1',
            style: { ...defaultTextStyle(), 'font-size': '32px', 'text-align': 'center', 'font-weight': '700', 'link-color': '#0068A5' },
        },
        mobileStyle: {},
        computedStyle: { hideContentOnMobile: false, hideContentOnDesktop: false },
    }),
    [MODULE_TYPES.paragraph]: () => ({
        style: defaultPadding(),
        paragraph: {
            html: '<p>Write your text here</p>',
            style: defaultTextStyle(),
            computedStyle: { linkColor: '#0068A5', paragraphSpacing: '16px' },
        },
        computedStyle: { hideContentOnMobile: false, hideContentOnDesktop: false },
    }),
    [MODULE_TYPES.text]: () => ({
        style: defaultPadding(),
        text: {
            html: '<p>Write your text here</p>',
            style: { ...defaultTextStyle(), 'line-height': '150%' },
            computedStyle: { linkColor: '#0068A5' },
        },
        computedStyle: { hideContentOnMobile: false, hideContentOnDesktop: false },
    }),
    [MODULE_TYPES.list]: () => ({
        style: defaultPadding(),
        list: {
            tag: 'ul',
            html: '<ul><li>First item</li><li>Second item</li></ul>',
            style: defaultTextStyle(),
            computedStyle: { liIndent: '24px', liSpacing: '6px', linkColor: '#0068A5', listStyleType: 'disc', listStylePosition: 'outside' },
        },
        computedStyle: { hideContentOnMobile: false, hideContentOnDesktop: false },
    }),
    [MODULE_TYPES.image]: () => ({
        image: { alt: '', src: '', href: '', target: '_blank', width: null, height: null, percWidth: 100 },
        style: { width: '100%', ...defaultPadding('0px') },
        computedStyle: { class: 'center autowidth', width: '100%', hideContentOnMobile: false },
    }),
    [MODULE_TYPES.button]: () => ({
        style: { 'text-align': 'center', ...defaultPadding() },
        button: {
            href: 'https://',
            label: '<p>Button</p>',
            target: '_blank',
            style: {
                color: '#ffffff',
                width: 'auto',
                'font-size': '16px',
                'font-family': 'inherit',
                'font-weight': '400',
                'line-height': '200%',
                'border-radius': '4px',
                'background-color': '#3AAEE0',
                'padding-top': '5px',
                'padding-right': '20px',
                'padding-bottom': '5px',
                'padding-left': '20px',
                'border-top': '0px solid transparent',
                'border-right': '0px solid transparent',
                'border-bottom': '0px solid transparent',
                'border-left': '0px solid transparent',
            },
        },
        mobileStyle: {},
        computedStyle: { hideContentOnMobile: false },
    }),
    [MODULE_TYPES.divider]: () => ({
        style: defaultPadding(),
        divider: { style: { width: '100%', 'border-top': '1px solid #BBBBBB' } },
        computedStyle: { align: 'center', hideContentOnMobile: false },
    }),
    [MODULE_TYPES.spacer]: () => ({
        spacer: { style: { height: '40px' } },
        computedStyle: {},
    }),
    [MODULE_TYPES.social]: () => ({
        style: { 'text-align': 'center', ...defaultPadding() },
        iconsList: {
            icons: ['facebook', 'instagram', 'linkedin'].map((network) => ({
                id: uuidv4(),
                name: network,
                type: 'follow',
                text: null,
                image: {
                    alt: network,
                    title: network,
                    src: `https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/t-outline-circle-dark-black/${network}@2x.png`,
                    href: `https://www.${network}.com/`,
                    prefix: `https://www.${network}.com/`,
                    target: '_blank',
                },
            })),
        },
        computedStyle: { iconsDefaultWidth: 32, padding: '0 5px 0 5px', hideContentOnMobile: false },
    }),
    [MODULE_TYPES.html]: () => ({
        html: { html: '<div style="padding:10px;text-align:center">Custom HTML</div>' },
        computedStyle: { hideContentOnMobile: false },
    }),
    [MODULE_TYPES.video]: () => ({
        style: { width: '100%', ...defaultPadding('10px') },
        video: {
            src: '',
            thumbSrc: '',
            thumbAlt: '',
            mode: 'thumbnail',
            thumbRatio: '16-9',
            iconType: 1,
            iconSize: 64,
            iconColor1: '#ffffff',
            iconColor2: '#000000',
        },
        computedStyle: { class: 'center', width: '100%', hideContentOnMobile: false },
    }),
    [MODULE_TYPES.icons]: () => ({
        style: {
            color: '#000000',
            'font-size': '14px',
            'text-align': 'center',
            'font-family': 'inherit',
            'font-weight': '400',
            ...defaultPadding(),
        },
        iconsList: {
            icons: ['Free shipping', 'Secure payment', '24/7 support'].map((text) => ({
                id: uuidv4(),
                href: '',
                text,
                image: '',
                title: text,
                width: '64px',
                height: '64px',
                target: '_blank',
                textPosition: 'bottom',
            })),
        },
        computedStyle: {
            layout: 'horizontal',
            iconHeight: '48px',
            iconSpacing: { 'padding-top': '5px', 'padding-right': '15px', 'padding-bottom': '5px', 'padding-left': '15px' },
            itemsSpacing: '0px',
            hideContentOnMobile: false,
            hideContentOnDesktop: false,
        },
    }),
}

export const createIconItem = (): Record<string, any> => ({
    id: uuidv4(),
    href: '',
    text: 'New item',
    image: '',
    title: 'New item',
    width: '64px',
    height: '64px',
    target: '_blank',
    textPosition: 'bottom',
})

export const youtubeVideoId = (url: string): string | null =>
    url.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{6,})/)?.[1] ?? null

export const vimeoVideoId = (url: string): string | null =>
    url.match(/vimeo\.com\/(?:video\/)?(\d+)/)?.[1] ?? null

export const videoThumbnailFromUrl = (url: string): string | null => {
    const youtubeId = youtubeVideoId(url)

    return youtubeId ? `https://img.youtube.com/vi/${youtubeId}/hqdefault.jpg` : null
}

export const paletteModuleTypes: Array<{ type: string, label: string, icon: string }> = [
    { type: MODULE_TYPES.heading, label: 'Heading', icon: 'fal fa-heading' },
    { type: MODULE_TYPES.text, label: 'Text', icon: 'fal fa-text' },
    { type: MODULE_TYPES.paragraph, label: 'Paragraph', icon: 'fal fa-paragraph' },
    { type: MODULE_TYPES.list, label: 'List', icon: 'fal fa-list-ul' },
    { type: MODULE_TYPES.image, label: 'Image', icon: 'fal fa-image' },
    { type: MODULE_TYPES.button, label: 'Button', icon: 'fal fa-square' },
    { type: MODULE_TYPES.divider, label: 'Divider', icon: 'fal fa-minus' },
    { type: MODULE_TYPES.spacer, label: 'Spacer', icon: 'fal fa-arrows-v' },
    { type: MODULE_TYPES.social, label: 'Social', icon: 'fal fa-share-alt' },
    { type: MODULE_TYPES.video, label: 'Video', icon: 'fal fa-video' },
    { type: MODULE_TYPES.icons, label: 'Icons', icon: 'fal fa-icons' },
    { type: MODULE_TYPES.html, label: 'HTML', icon: 'fal fa-code' },
    { type: UNSUBSCRIBE_BLOCK, label: 'Unsubscribe', icon: 'fal fa-user-slash' },
]

export const rowLayouts: number[][] = [
    [12],
    [6, 6],
    [4, 4, 4],
    [3, 3, 3, 3],
    [4, 8],
    [8, 4],
]

export const isUnsubscribeModule = (module: EmailModule | null | undefined): boolean =>
    module?.type === MODULE_TYPES.html && !!module.descriptor?.aikuUnsubscribe

export const moduleDisplayName = (module: EmailModule): string =>
    isUnsubscribeModule(module) ? 'unsubscribe' : shortModuleType(module.type).replace('-', ' ')

export const createUnsubscribeModule = (): EmailModule => ({
    type: MODULE_TYPES.html,
    uuid: uuidv4(),
    locked: false,
    descriptor: {
        html: { html: '' },
        style: { 'padding-top': '20px', 'padding-right': '10px', 'padding-bottom': '20px', 'padding-left': '10px' },
        aikuUnsubscribe: {
            text: "Don't want to receive these emails?",
            linkLabel: 'Unsubscribe',
            backgroundColor: '#666666',
            textColor: '#ffffff',
            linkColor: '#ffffff',
            fontFamily: 'Arial, Helvetica Neue, Helvetica, sans-serif',
            fontSize: '12px',
            align: 'center',
            underline: true,
            linkBold: false,
        },
        computedStyle: { hideContentOnMobile: false },
    },
})

export const createModule = (type: string): EmailModule => {
    if (type === UNSUBSCRIBE_BLOCK) {
        return createUnsubscribeModule()
    }

    return {
        type,
        uuid: uuidv4(),
        locked: false,
        descriptor: moduleDescriptorFactories[type]?.() ?? { computedStyle: {} },
    }
}

export const createMergeContentModule = (name: string, value: string): EmailModule => ({
    type: MODULE_TYPES.mergeContent,
    uuid: uuidv4(),
    locked: false,
    descriptor: {
        style: defaultPadding('0px'),
        mergeContent: { name, value },
        computedStyle: { hideContentOnMobile: false },
    },
})

export const createRow = (gridColumns: number[], width: string): EmailRow => ({
    type: gridColumns.length === 1 ? 'one-column-empty' : 'custom',
    uuid: uuidv4(),
    empty: false,
    locked: false,
    synced: false,
    content: {
        style: {
            color: '#000000',
            width,
            'background-color': 'transparent',
            'background-image': 'none',
            'background-repeat': 'no-repeat',
            'background-position': 'top left',
            ...defaultPadding('0px'),
        },
        computedStyle: { verticalAlign: 'top', rowColStackOnMobile: true, hideContentOnMobile: false, hideContentOnDesktop: false },
    },
    container: {
        style: {
            'background-color': 'transparent',
            'background-image': 'none',
            'background-repeat': 'no-repeat',
            'background-position': 'top left',
        },
    },
    columns: gridColumns.map((gridColumn) => ({
        uuid: uuidv4(),
        style: { 'background-color': 'transparent', 'padding-top': '5px', 'padding-right': '0px', 'padding-bottom': '5px', 'padding-left': '0px' },
        modules: [],
        'grid-columns': gridColumn,
    })),
})

export const createEmptyEmail = (): EmailJson => ({
    page: {
        title: null,
        description: null,
        template: { name: 'template-base', type: 'basic', version: '2.0.0' },
        body: {
            type: 'mailup-bee-page-properties',
            container: { style: { 'background-color': '#FFFFFF' } },
            content: {
                style: { color: '#000000', 'font-family': DEFAULT_FONT_FAMILY },
                computedStyle: { linkColor: '#0068A5', messageWidth: DEFAULT_MESSAGE_WIDTH, messageBackgroundColor: 'transparent' },
            },
            webFonts: [],
        },
        rows: [createRow([12], DEFAULT_MESSAGE_WIDTH)],
    },
    comments: {},
})

const refreshUuids = <T>(value: T): T => {
    if (Array.isArray(value)) {
        return value.map(refreshUuids) as T
    }
    if (value && typeof value === 'object') {
        const copy: Record<string, any> = {}
        for (const [key, item] of Object.entries(value)) {
            copy[key] = key === 'uuid' ? uuidv4() : refreshUuids(item)
        }
        return copy as T
    }
    return value
}

export const duplicateWithNewUuids = <T>(value: T): T => refreshUuids(JSON.parse(JSON.stringify(value)))

export const normaliseEmailJson = (source: any): EmailJson => {
    let candidate = source
    if (typeof candidate === 'string') {
        try {
            candidate = JSON.parse(candidate)
        } catch {
            candidate = null
        }
    }
    if (candidate?.layout?.page) {
        candidate = candidate.layout
    }
    if (!candidate?.page?.body || !Array.isArray(candidate?.page?.rows)) {
        return createEmptyEmail()
    }

    const email: EmailJson = JSON.parse(JSON.stringify(candidate))
    email.page.body.content ??= { style: {}, computedStyle: {} }
    email.page.body.content.style ??= {}
    email.page.body.content.computedStyle ??= {}
    email.page.body.container ??= { style: {} }
    email.page.body.container.style ??= {}
    for (const row of email.page.rows) {
        row.uuid ??= uuidv4()
        row.content ??= { style: {} }
        row.content.style ??= {}
        row.container ??= { style: {} }
        row.container.style ??= {}
        row.columns = Array.isArray(row.columns) ? row.columns : []
        for (const column of row.columns) {
            column.uuid ??= uuidv4()
            column.style ??= {}
            column.modules = Array.isArray(column.modules) ? column.modules : []
            for (const module of column.modules) {
                module.uuid ??= uuidv4()
                module.descriptor ??= {}
            }
        }
    }

    return email
}

export const RICH_TEXT_TOGGLES = ['bold', 'italic', 'underline', 'strikethrough', 'link', 'color', 'fontSize', 'alignLeft', 'alignCenter', 'alignRight', 'bulletList', 'orderedList', 'clear', 'undo', 'redo']
export const INLINE_TEXT_TOGGLES = ['bold', 'italic', 'underline', 'link', 'color', 'clear', 'undo', 'redo']

const unwrapParagraphs = (html: string): string =>
    html
        .replace(/^\s*<p[^>]*>/i, '')
        .replace(/<\/p>\s*$/i, '')
        .replace(/<\/p>\s*<p[^>]*>/gi, '<br>')

export const moduleTextToggles = (module: EmailModule): string[] =>
    [MODULE_TYPES.heading, MODULE_TYPES.button].includes(module.type as any) ? INLINE_TEXT_TOGGLES : RICH_TEXT_TOGGLES

export const getModuleText = (module: EmailModule): string => {
    const descriptor = module.descriptor ?? {}
    switch (module.type) {
        case MODULE_TYPES.heading:
            return descriptor.heading?.text ?? ''
        case MODULE_TYPES.paragraph:
            return descriptor.paragraph?.html ?? ''
        case MODULE_TYPES.text:
            return descriptor.text?.html ?? ''
        case MODULE_TYPES.list:
            return descriptor.list?.html ?? ''
        case MODULE_TYPES.button:
            return descriptor.button?.label ?? ''
        default:
            return ''
    }
}

export const setModuleText = (module: EmailModule, html: string): void => {
    const descriptor = module.descriptor
    switch (module.type) {
        case MODULE_TYPES.heading:
            descriptor.heading.text = unwrapParagraphs(html)
            break
        case MODULE_TYPES.paragraph:
            descriptor.paragraph.html = html
            break
        case MODULE_TYPES.text:
            descriptor.text.html = html
            break
        case MODULE_TYPES.list:
            descriptor.list.html = html
            break
        case MODULE_TYPES.button:
            descriptor.button.label = html
            break
    }
}
