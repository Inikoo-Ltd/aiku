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
export const TABLE_BLOCK = 'aiku-table'
export const PRODUCTS_BLOCK = 'aiku-products'

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
            icons: ['facebook', 'instagram', 'linkedin'].map((network) => createSocialIcon(network)),
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

const SOCIAL_ICON_BASE_URL = 'https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets'

export const DEFAULT_SOCIAL_ICON_SET = 't-outline-circle-dark-black'

export const SOCIAL_ICON_SETS: Array<{ label: string, value: string }> = [
    { label: 'Circle · Color', value: 'circle-color' },
    { label: 'Circle · Black', value: 'circle-black' },
    { label: 'Circle · Dark gray', value: 'circle-dark-gray' },
    { label: 'Circle · Gray', value: 'circle-gray' },
    { label: 'Circle · Blue', value: 'circle-blue' },
    { label: 'Outline · Black', value: 't-outline-circle-dark-black' },
    { label: 'Outline · Dark gray', value: 't-outline-circle-dark-gray' },
    { label: 'Outline · White', value: 't-outline-circle-white' },
    { label: 'Logo only · Color', value: 't-only-logo-color' },
    { label: 'Logo only · Dark gray', value: 't-only-logo-dark-gray' },
    { label: 'Logo only · White', value: 't-only-logo-white' },
]

export const SOCIAL_NETWORKS: Array<{ name: string, label: string, url: string }> = [
    { name: 'facebook', label: 'Facebook', url: 'https://www.facebook.com/' },
    { name: 'instagram', label: 'Instagram', url: 'https://www.instagram.com/' },
    { name: 'linkedin', label: 'LinkedIn', url: 'https://www.linkedin.com/' },
    { name: 'x', label: 'X', url: 'https://x.com/' },
    { name: 'youtube', label: 'YouTube', url: 'https://www.youtube.com/' },
    { name: 'tiktok', label: 'TikTok', url: 'https://www.tiktok.com/' },
    { name: 'pinterest', label: 'Pinterest', url: 'https://www.pinterest.com/' },
    { name: 'threads', label: 'Threads', url: 'https://www.threads.net/' },
    { name: 'whatsapp', label: 'WhatsApp', url: 'https://wa.me/' },
    { name: 'telegram', label: 'Telegram', url: 'https://t.me/' },
    { name: 'snapchat', label: 'Snapchat', url: 'https://www.snapchat.com/' },
    { name: 'discord', label: 'Discord', url: 'https://discord.gg/' },
    { name: 'spotify', label: 'Spotify', url: 'https://open.spotify.com/' },
    { name: 'vimeo', label: 'Vimeo', url: 'https://vimeo.com/' },
    { name: 'website', label: 'Website', url: 'https://' },
    { name: 'mail', label: 'Email', url: 'mailto:' },
]

const BEEFREE_ICON_SRC_PATTERN = /\/social-networks-icon-sets\/([^/]+)\/([^/@]+)@2x\.png$/

let socialIconSources: Record<string, string> = {}

export const setSocialIconSources = (sources: Record<string, string> | null | undefined): void => {
    socialIconSources = sources ?? {}
}

export const socialIconSrc = (network: string, iconSet: string = DEFAULT_SOCIAL_ICON_SET): string =>
    socialIconSources[`${iconSet}/${network}`] ?? `${SOCIAL_ICON_BASE_URL}/${iconSet}/${network}@2x.png`

export const socialIconSetOf = (icon: Record<string, any>): string | null =>
    icon.iconSet ?? BEEFREE_ICON_SRC_PATTERN.exec(String(icon.image?.src ?? ''))?.[1] ?? null

const socialIconNetworkOf = (icon: Record<string, any>): string | null =>
    icon.name ?? BEEFREE_ICON_SRC_PATTERN.exec(String(icon.image?.src ?? ''))?.[2] ?? null

export const createSocialIcon = (network: string, iconSet: string = DEFAULT_SOCIAL_ICON_SET): Record<string, any> => {
    const socialNetwork = SOCIAL_NETWORKS.find((candidate) => candidate.name === network)
    const label = socialNetwork?.label ?? network
    const url = socialNetwork?.url ?? `https://www.${network}.com/`

    return {
        id: uuidv4(),
        name: network,
        type: 'follow',
        text: null,
        iconSet,
        image: {
            alt: label,
            title: label,
            src: socialIconSrc(network, iconSet),
            href: url,
            prefix: url,
            target: '_blank',
        },
    }
}

export const applySocialIconSet = (icons: Array<Record<string, any>>, iconSet: string): void => {
    icons.forEach((icon) => {
        const network = socialIconNetworkOf(icon)
        if (network && socialIconSetOf(icon)) {
            icon.iconSet = iconSet
            icon.image.src = socialIconSrc(network, iconSet)
        }
    })
}

export const youtubeVideoId = (url: string): string | null =>
    url.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{6,})/)?.[1] ?? null

export const vimeoVideoId = (url: string): string | null =>
    url.match(/vimeo\.com\/(?:video\/)?(\d+)/)?.[1] ?? null

export const videoThumbnailFromUrl = (url: string): string | null => {
    const youtubeId = youtubeVideoId(url)

    return youtubeId ? `https://img.youtube.com/vi/${youtubeId}/hqdefault.jpg` : null
}

export const isVideoPageUrl = (url: string | null | undefined): boolean =>
    !!url && (!!youtubeVideoId(url) || !!vimeoVideoId(url))

export const videoEmailThumbnailKey = (video: Record<string, any> | undefined): string =>
    JSON.stringify([video?.src ?? '', video?.thumbSrc ?? '', video?.thumbRatio ?? '16-9', String(video?.iconType ?? 1), String(video?.iconSize ?? 64), video?.iconColor1 ?? '#ffffff', video?.iconColor2 ?? '#000000'])

export const hasCurrentVideoEmailThumbnail = (video: Record<string, any> | undefined): boolean =>
    !!video?.emailThumbnail?.src && video.emailThumbnail.key === videoEmailThumbnailKey(video)

export const moveVideoPageUrlOutOfThumbnail = (video: Record<string, any> | undefined): void => {
    if (!video || !isVideoPageUrl(video.thumbSrc)) {
        return
    }
    video.src ||= video.thumbSrc
    video.thumbSrc = videoThumbnailFromUrl(video.src)
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
    { type: TABLE_BLOCK, label: 'Table', icon: 'fal fa-table' },
    { type: MODULE_TYPES.html, label: 'HTML', icon: 'fal fa-code' },
    { type: PRODUCTS_BLOCK, label: 'Products', icon: 'fal fa-cubes' },
    { type: MODULE_TYPES.mergeContent, label: 'Dynamic content', icon: 'fal fa-puzzle-piece' },
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

export const MIN_COLUMN_WIDTH = 10

export const columnWidthPercent = (column: EmailColumn): number => {
    const gridColumns = column['grid-columns'] ?? 12
    const exactWidth = Number(column.aikuWidth)

    return Number.isFinite(exactWidth) && Math.abs(exactWidth / 100 * 12 - gridColumns) < 1 ? exactWidth : gridColumns / 12 * 100
}

const gridColumnsForWidths = (widths: number[]): number[] => {
    const exact = widths.map((width) => width / 100 * 12)
    const gridColumns = exact.map((value) => Math.max(1, Math.floor(value)))
    const byRemainder = exact.map((value, index) => ({ index, remainder: value - Math.floor(value) })).sort((a, b) => b.remainder - a.remainder)
    for (let step = 0; gridColumns.reduce((sum, value) => sum + value, 0) < 12; step++) {
        gridColumns[byRemainder[step % byRemainder.length].index] += 1
    }
    for (let step = 0; gridColumns.reduce((sum, value) => sum + value, 0) > 12; step++) {
        const index = byRemainder[byRemainder.length - 1 - (step % byRemainder.length)].index
        if (gridColumns[index] > 1) {
            gridColumns[index] -= 1
        }
    }

    return gridColumns
}

export const setColumnWidths = (row: EmailRow, widths: number[]): void => {
    const gridColumns = gridColumnsForWidths(widths)
    row.columns.forEach((column, index) => {
        column.aikuWidth = Math.round(widths[index] * 100) / 100
        column['grid-columns'] = gridColumns[index]
    })
}

export const resizeColumn = (row: EmailRow, index: number, width: number): void => {
    const widths = row.columns.map(columnWidthPercent)
    const neighbour = index < widths.length - 1 ? index + 1 : index - 1
    if (neighbour < 0) {
        return
    }
    const pairTotal = widths[index] + widths[neighbour]
    widths[index] = Math.min(Math.max(width, MIN_COLUMN_WIDTH), pairTotal - MIN_COLUMN_WIDTH)
    widths[neighbour] = pairTotal - widths[index]
    setColumnWidths(row, widths)
}

export const canAddColumn = (row: EmailRow): boolean =>
    Math.max(...row.columns.map(columnWidthPercent)) / 2 >= MIN_COLUMN_WIDTH

export const addColumn = (row: EmailRow): boolean => {
    if (!canAddColumn(row)) {
        return false
    }
    const widths = row.columns.map(columnWidthPercent)
    const widest = widths.indexOf(Math.max(...widths))
    widths[widest] /= 2
    widths.push(widths[widest])
    row.columns.push(createColumn(1))
    setColumnWidths(row, widths)

    return true
}

export const removeColumn = (row: EmailRow, index: number): boolean => {
    if (row.columns.length <= 1 || !row.columns[index]) {
        return false
    }
    const widths = row.columns.map(columnWidthPercent)
    const neighbour = index > 0 ? index - 1 : 1
    widths[neighbour] += widths[index]
    row.columns[neighbour].modules.push(...row.columns[index].modules)
    widths.splice(index, 1)
    row.columns.splice(index, 1)
    setColumnWidths(row, widths)

    return true
}

const THIRD = 100 / 3

export const columnRatioPresets = (columnCount: number): number[][] => ({
    2: [[50, 50], [40, 60], [60, 40], [THIRD, 2 * THIRD], [2 * THIRD, THIRD], [30, 70], [70, 30], [25, 75], [75, 25]],
    3: [[THIRD, THIRD, THIRD], [50, 25, 25], [25, 50, 25], [25, 25, 50], [20, 60, 20], [40, 30, 30]],
    4: [[25, 25, 25, 25], [40, 20, 20, 20], [20, 20, 20, 40], [30, 20, 20, 30]],
}[columnCount] ?? [])

export const isUnsubscribeModule = (module: EmailModule | null | undefined): boolean =>
    module?.type === MODULE_TYPES.html && !!module.descriptor?.aikuUnsubscribe

export const isTableModule = (module: EmailModule | null | undefined): boolean =>
    module?.type === MODULE_TYPES.html && !!module.descriptor?.aikuTable

export const moduleDisplayName = (module: EmailModule): string => {
    if (isUnsubscribeModule(module)) {
        return 'unsubscribe'
    }
    if (isTableModule(module)) {
        return 'table'
    }
    if (dynamicContentSource(module) === 'products') {
        return 'products'
    }

    return shortModuleType(module.type).replace('-', ' ')
}

export interface EmailTableSettings {
    hasHeader: boolean
    header: string[]
    rows: string[][]
    fontFamily: string
    fontSize: string
    textColor: string
    backgroundColor: string
    headerBackgroundColor: string
    headerTextColor: string
    headerBold: boolean
    borderColor: string
    borderWidth: string
    cellPadding: string
    striped: boolean
    stripeColor: string
    align: string
}

export const createTableModule = (): EmailModule => ({
    type: MODULE_TYPES.html,
    uuid: uuidv4(),
    locked: false,
    descriptor: {
        html: { html: '' },
        style: defaultPadding(),
        aikuTable: {
            hasHeader: true,
            header: ['Product', 'Quantity', 'Price'],
            rows: [
                ['Item 1', '1', '£10.00'],
                ['Item 2', '2', '£20.00'],
            ],
            fontFamily: 'Arial, Helvetica Neue, Helvetica, sans-serif',
            fontSize: '14px',
            textColor: '#374151',
            backgroundColor: '#ffffff',
            headerBackgroundColor: '#f3f4f6',
            headerTextColor: '#111827',
            headerBold: true,
            borderColor: '#e5e7eb',
            borderWidth: '1px',
            cellPadding: '8px',
            striped: false,
            stripeColor: '#f9fafb',
            align: 'left',
        } satisfies EmailTableSettings,
        computedStyle: { hideContentOnMobile: false, hideContentOnDesktop: false },
    },
})

export const tableColumnCount = (table: EmailTableSettings): number =>
    Math.max(table.header?.length ?? 0, ...(table.rows ?? []).map((row) => row.length), 1)

export const addTableRow = (table: EmailTableSettings): void => {
    table.rows.push(Array.from({ length: tableColumnCount(table) }, () => ''))
}

export const removeTableRow = (table: EmailTableSettings, index: number): void => {
    if (table.rows.length > 1) {
        table.rows.splice(index, 1)
    }
}

export const addTableColumn = (table: EmailTableSettings): void => {
    const columnCount = tableColumnCount(table)
    table.header = [...Array.from({ length: columnCount }, (_, index) => table.header?.[index] ?? ''), `Column ${columnCount + 1}`]
    table.rows = table.rows.map((row) => [...Array.from({ length: columnCount }, (_, index) => row[index] ?? ''), ''])
}

export const removeTableColumn = (table: EmailTableSettings, index: number): void => {
    if (tableColumnCount(table) <= 1) {
        return
    }
    table.header.splice(index, 1)
    table.rows.forEach((row) => row.splice(index, 1))
}

export const isUnsubscribeMergeContent = (module: EmailModule): boolean =>
    module?.type === MODULE_TYPES.mergeContent && /^\s*\[unsubscribe\]\s*$/i.test(String(module.descriptor?.mergeContent?.value ?? ''))

export const isUnsubscribeMergeTag = (tag: { value?: string } | null | undefined): boolean =>
    /^\[unsubscribe\]$/i.test(String(tag?.value ?? ''))

export const emailHasUnsubscribeBlock = (email: EmailJson): boolean =>
    email.page.rows.some((row) => row.columns.some((column) => column.modules.some(isUnsubscribeModule)))

export const rowHasUnsubscribeBlock = (row: EmailRow): boolean =>
    row.columns.some((column) => column.modules.some(isUnsubscribeModule))

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
    if (type === TABLE_BLOCK) {
        return createTableModule()
    }
    if (type === PRODUCTS_BLOCK) {
        return createMergeContentModule('', '', 'products')
    }
    if (type === MODULE_TYPES.mergeContent) {
        return createMergeContentModule('', '', 'blocks')
    }

    return {
        type,
        uuid: uuidv4(),
        locked: false,
        descriptor: moduleDescriptorFactories[type]?.() ?? { computedStyle: {} },
    }
}

export const isEmptyMergeContent = (module: EmailModule | null | undefined): boolean =>
    module?.type === MODULE_TYPES.mergeContent && !String(module.descriptor?.mergeContent?.value ?? '').trim()

export type DynamicContentSource = 'products' | 'blocks'

export const dynamicContentSource = (module: EmailModule | null | undefined): DynamicContentSource | null =>
    module?.type === MODULE_TYPES.mergeContent ? module.descriptor?.aikuDynamicSource ?? null : null

export const createMergeContentModule = (name: string, value: string, source?: DynamicContentSource): EmailModule => ({
    type: MODULE_TYPES.mergeContent,
    uuid: uuidv4(),
    locked: false,
    descriptor: {
        style: defaultPadding('0px'),
        mergeContent: { name, value },
        computedStyle: { hideContentOnMobile: false },
        ...(source ? { aikuDynamicSource: source } : {}),
    },
})

const createColumn = (gridColumns: number): EmailColumn => ({
    uuid: uuidv4(),
    style: { 'background-color': 'transparent', 'padding-top': '5px', 'padding-right': '0px', 'padding-bottom': '5px', 'padding-left': '0px' },
    modules: [],
    'grid-columns': gridColumns,
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
    columns: gridColumns.map(createColumn),
})

export interface ProductCard {
    code: string
    name: string | null
    description: string | null
    product_image: string | null
    url: string | null
}

export interface ProductCardAppearance {
    productsPerRow: number
    showDescription: boolean
    buttonLabel: string
    buttonColor: string
}

const escapeText = (value: unknown): string =>
    String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

export const readableTextColor = (hexColor: string): string => {
    const match = hexColor.match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i)
    if (!match) {
        return '#ffffff'
    }
    const [red, green, blue] = match.slice(1).map((channel) => parseInt(channel, 16))

    return (0.299 * red + 0.587 * green + 0.114 * blue) / 255 > 0.6 ? '#111111' : '#ffffff'
}

const createProductCardModules = (product: ProductCard, appearance: ProductCardAppearance, create: (type: string) => EmailModule): EmailModule[] => {
    const title = product.name || product.code
    const url = product.url ?? ''
    const modules: EmailModule[] = []

    if (product.product_image) {
        const image = create(MODULE_TYPES.image)
        Object.assign(image.descriptor.image, { src: product.product_image, href: url, alt: title })
        modules.push(image)
    }

    const heading = create(MODULE_TYPES.heading)
    heading.descriptor.heading.title = 'h3'
    heading.descriptor.heading.text = escapeText(title)
    heading.descriptor.heading.style['font-size'] = '18px'
    modules.push(heading)

    if (appearance.showDescription && product.description) {
        const paragraph = create(MODULE_TYPES.paragraph)
        paragraph.descriptor.paragraph.html = product.description
        paragraph.descriptor.paragraph.style['text-align'] = 'center'
        modules.push(paragraph)
    }

    const button = create(MODULE_TYPES.button)
    button.descriptor.button.href = url
    button.descriptor.button.label = `<p>${escapeText(appearance.buttonLabel || 'SHOP NOW')}</p>`
    button.descriptor.button.style['background-color'] = appearance.buttonColor
    button.descriptor.button.style.color = readableTextColor(appearance.buttonColor)
    modules.push(button)

    return modules
}

export const createProductRows = (
    products: ProductCard[],
    appearance: ProductCardAppearance,
    width: string,
    create: (type: string) => EmailModule = createModule,
): EmailRow[] => {
    const perRow = Math.min(3, Math.max(1, appearance.productsPerRow))
    const rows: EmailRow[] = []
    for (let index = 0; index < products.length; index += perRow) {
        const row = createRow(Array(perRow).fill(12 / perRow), width)
        products.slice(index, index + perRow).forEach((product, columnIndex) => {
            row.columns[columnIndex].modules = createProductCardModules(product, appearance, create)
        })
        rows.push(row)
    }

    return rows
}

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
            column.modules = column.modules.map((module) => {
                if (isUnsubscribeMergeContent(module)) {
                    return { ...createUnsubscribeModule(), uuid: module.uuid ?? uuidv4() }
                }
                module.uuid ??= uuidv4()
                module.descriptor ??= {}
                if (module.type === MODULE_TYPES.video) {
                    moveVideoPageUrlOutOfThumbnail(module.descriptor.video)
                }

                return module
            })
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

export interface ModulePlaceholder {
    icon: string
    title: string
    hint: string
    size: 'large' | 'small'
}

const isBlankHtml = (html: unknown): boolean =>
    String(html ?? '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() === ''

export const modulePlaceholder = (module: EmailModule): ModulePlaceholder | null => {
    const descriptor = module.descriptor ?? {}

    switch (module.type) {
        case MODULE_TYPES.image:
            return descriptor.image?.src ? null : { icon: 'fal fa-image', title: 'Image', hint: 'Upload or browse an image', size: 'large' }
        case MODULE_TYPES.video:
            return descriptor.video?.thumbSrc || descriptor.video?.src ? null : { icon: 'fal fa-video', title: 'Video', hint: 'Add a YouTube or Vimeo link', size: 'large' }
        case MODULE_TYPES.heading:
        case MODULE_TYPES.paragraph:
        case MODULE_TYPES.text:
        case MODULE_TYPES.list:
            return isBlankHtml(getModuleText(module)) ? { icon: 'fal fa-text', title: 'Text', hint: 'Click to start writing', size: 'small' } : null
        case MODULE_TYPES.html:
            if (isUnsubscribeModule(module) || isTableModule(module)) {
                return null
            }
            return isBlankHtml(descriptor.html?.html) && !/<(img|table|iframe)/i.test(String(descriptor.html?.html ?? ''))
                ? { icon: 'fal fa-code', title: 'HTML', hint: 'Add your own HTML', size: 'small' }
                : null
        case MODULE_TYPES.social:
            return descriptor.iconsList?.icons?.length ? null : { icon: 'fal fa-share-alt', title: 'Social', hint: 'Add social links', size: 'small' }
        case MODULE_TYPES.icons:
            return descriptor.iconsList?.icons?.length ? null : { icon: 'fal fa-icons', title: 'Icons', hint: 'Add icon items', size: 'small' }
        case MODULE_TYPES.mergeContent:
            if (!isEmptyMergeContent(module)) {
                return null
            }
            return {
                products: { icon: 'fal fa-cubes', title: 'Products', hint: 'Pick products from the shop', size: 'small' },
                blocks: { icon: 'fal fa-puzzle-piece', title: 'Dynamic content', hint: 'Pick a dynamic block', size: 'small' },
            }[dynamicContentSource(module) ?? ''] ?? { icon: 'fal fa-puzzle-piece', title: 'Dynamic content', hint: 'Pick products or a dynamic block', size: 'small' }
        default:
            return null
    }
}
