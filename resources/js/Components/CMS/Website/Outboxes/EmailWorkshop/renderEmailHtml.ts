import {
    CssStyle,
    DEFAULT_FONT_FAMILY,
    DEFAULT_MESSAGE_WIDTH,
    EmailColumn,
    EmailJson,
    EmailModule,
    EmailRow,
    MODULE_TYPES,
    UNSUBSCRIBE_URL_TAG,
    isTableModule,
    isUnsubscribeModule,
    tableColumnCount,
    hasCurrentVideoEmailThumbnail,
} from './emailWorkshopBlocks'

const PADDING_KEYS = ['padding-top', 'padding-right', 'padding-bottom', 'padding-left']
const NON_CSS_KEYS = ['link-color']
const MSO_TABLE = 'mso-table-lspace:0;mso-table-rspace:0'
const PRESENTATION_TABLE = 'width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation"'

export interface RenderContext {
    columnWidth: number
    linkColor: string
    mobileRules: string[]
    moduleIndex: number
    isCanvas: boolean
}

export const escapeAttribute = (value: unknown): string =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')

export const styleToString = (style: CssStyle | undefined | null, excludedKeys: string[] = []): string =>
    Object.entries(style ?? {})
        .filter(([key, value]) => value !== null && value !== undefined && value !== '' && !excludedKeys.includes(key) && !NON_CSS_KEYS.includes(key))
        .map(([key, value]) => `${key}:${value}`)
        .join(';')

const pickStyle = (style: CssStyle | undefined | null, keys: string[]): CssStyle =>
    Object.fromEntries(Object.entries(style ?? {}).filter(([key]) => keys.includes(key)))

export const modulePaddingStyle = (module: EmailModule): string =>
    styleToString(pickStyle(module.descriptor?.style, PADDING_KEYS))

export const pixels = (value: unknown, fallback = 0): number => {
    const parsed = parseFloat(String(value ?? ''))
    return Number.isFinite(parsed) ? parsed : fallback
}

const horizontalPadding = (style: CssStyle | undefined | null): number =>
    pixels(style?.['padding-left']) + pixels(style?.['padding-right'])

export const applyLinkColor = (html: string, linkColor: string | undefined): string => {
    if (!linkColor) {
        return html
    }

    return html.replace(/<a\b(?![^>]*\sstyle=)/gi, `<a style="text-decoration:underline;color:${escapeAttribute(linkColor)}"`)
}

const paragraphSpacingHtml = (html: string, spacing: string | undefined): string => {
    const paragraphs = html.match(/<p(?=[\s>])[^>]*>/gi) ?? []
    let seen = 0

    return html.replace(/<p(?=[\s>])([^>]*)>/gi, (_match, attributes: string) => {
        seen += 1
        const margin = seen === paragraphs.length ? '0' : `0 0 ${spacing ?? '16px'} 0`
        const styleMatch = attributes.match(/\sstyle="([^"]*)"/i)
        if (styleMatch) {
            return `<p${attributes.replace(styleMatch[0], ` style="margin:${margin};${styleMatch[1]}"`)}>`
        }

        return `<p${attributes} style="margin:${margin}">`
    })
}

const visibilityClasses = (module: EmailModule): string => {
    const computedStyle = module.descriptor?.computedStyle ?? {}
    const classes: string[] = []
    if (computedStyle.hideContentOnMobile) {
        classes.push('mobile_hide')
    }
    if (computedStyle.hideContentOnDesktop) {
        classes.push('desktop_hide')
    }

    return classes.join(' ')
}

const blockTable = (blockClass: string, module: EmailModule, context: RenderContext, inner: string, extraPadStyle = ''): string => {
    const moduleClass = `block-${context.moduleIndex}`
    const padStyle = [styleToString(pickStyle(module.descriptor?.style, PADDING_KEYS)), extraPadStyle].filter(Boolean).join(';')

    return `<table class="${blockClass} ${moduleClass} ${visibilityClasses(module)}" ${PRESENTATION_TABLE} style="${MSO_TABLE};word-break:break-word"><tr><td class="pad" style="${padStyle}">${inner}</td></tr></table>`
}

const registerMobileStyle = (module: EmailModule, context: RenderContext, contentSelector: string): void => {
    const mobileStyle: CssStyle = module.descriptor?.mobileStyle ?? {}
    if (!mobileStyle || Array.isArray(mobileStyle) || Object.keys(mobileStyle).length === 0) {
        return
    }

    const selector = `.block-${context.moduleIndex}`
    const paddingRules = styleToString(pickStyle(mobileStyle, PADDING_KEYS))
    const contentRules = styleToString(mobileStyle, PADDING_KEYS)
    if (paddingRules) {
        context.mobileRules.push(`${selector} .pad{${paddingRules.split(';').map((rule) => `${rule}!important`).join(';')}}`)
    }
    if (contentRules) {
        context.mobileRules.push(`${selector} ${contentSelector}{${contentRules.split(';').map((rule) => `${rule}!important`).join(';')}}`)
    }
}

const alignmentFromClass = (className: string | undefined, fallback = 'center'): string => {
    if (className?.includes('left')) {
        return 'left'
    }
    if (className?.includes('right')) {
        return 'right'
    }

    return className?.includes('center') ? 'center' : fallback
}

export const imageDisplayWidth = (module: EmailModule, columnWidth: number): number => {
    const descriptor = module.descriptor ?? {}
    const availableWidth = Math.max(columnWidth - horizontalPadding(descriptor.style), 1)
    const percWidth = pixels(descriptor.image?.percWidth, 0)
    if (percWidth > 0) {
        return Math.round(availableWidth * percWidth / 100)
    }
    if (String(descriptor.computedStyle?.class ?? '').includes('fixedwidth')) {
        return Math.min(Math.round(pixels(descriptor.computedStyle?.width, availableWidth)), Math.round(availableWidth))
    }

    return Math.round(availableWidth)
}

const renderImage = (module: EmailModule, context: RenderContext): string => {
    const image = module.descriptor?.image ?? {}
    if (!image.src && !context.isCanvas) {
        return ''
    }
    const width = imageDisplayWidth(module, context.columnWidth)
    const align = alignmentFromClass(module.descriptor?.computedStyle?.class)
    const borderRadius = module.descriptor?.style?.['border-radius'] ?? '0px'
    const imageTag = image.src
        ? `<img src="${escapeAttribute(image.src)}" alt="${escapeAttribute(image.alt)}" title="${escapeAttribute(image.alt)}" width="${width}" style="display:block;height:auto;border:0;width:100%;border-radius:${escapeAttribute(borderRadius)}">`
        : `<div style="height:120px;background:#f3f4f6;border:1px dashed #d1d5db"></div>`
    const linked = image.href
        ? `<a href="${escapeAttribute(image.href)}" target="${escapeAttribute(image.target ?? '_blank')}" style="outline:none" tabindex="-1">${imageTag}</a>`
        : imageTag

    return blockTable('image_block', module, context, `<div class="alignment" align="${align}" style="line-height:10px"><div style="max-width:${width}px">${linked}</div></div>`, 'width:100%')
}

const renderHeading = (module: EmailModule, context: RenderContext): string => {
    const heading = module.descriptor?.heading ?? {}
    const tag = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(heading.title) ? heading.title : 'h1'
    const linkColor = heading.style?.['link-color'] ?? context.linkColor
    registerMobileStyle(module, context, tag)

    return blockTable('heading_block', module, context, `<${tag} style="margin:0;${styleToString(heading.style)}"><span>${applyLinkColor(heading.text ?? '', linkColor)}</span></${tag}>`, 'width:100%;text-align:center')
}

const renderParagraph = (module: EmailModule, context: RenderContext): string => {
    const paragraph = module.descriptor?.paragraph ?? {}
    const linkColor = paragraph.computedStyle?.linkColor ?? context.linkColor
    const html = paragraphSpacingHtml(applyLinkColor(paragraph.html ?? '', linkColor), paragraph.computedStyle?.paragraphSpacing)
    registerMobileStyle(module, context, '.pad > div')

    return blockTable('paragraph_block', module, context, `<div style="${styleToString(paragraph.style)}">${html}</div>`)
}

const renderText = (module: EmailModule, context: RenderContext): string => {
    const text = module.descriptor?.text ?? {}
    const linkColor = text.computedStyle?.linkColor ?? context.linkColor

    return blockTable('text_block', module, context, `<div style="${styleToString(text.style)}">${applyLinkColor(text.html ?? '', linkColor)}</div>`)
}

const withStyleAttribute = (attributes: string, style: string): string => {
    const styleMatch = attributes.match(/\sstyle="([^"]*)"/i)

    return styleMatch
        ? attributes.replace(styleMatch[0], ` style="${style};${styleMatch[1]}"`)
        : `${attributes} style="${style}"`
}

export const unwrapListItemParagraphs = (html: string): string =>
    html
        .replace(/(<li(?=[\s>])[^>]*>)\s*<p(?=[\s>])[^>]*>/gi, '$1')
        .replace(/<\/p>\s*(<\/li>|<ul|<ol)/gi, '$1')
        .replace(/<\/p>\s*<p(?=[\s>])[^>]*>/gi, '<br>')

export const listMarkerType = (tag: string, listStyleType: string | undefined): string => {
    if (listStyleType && listStyleType !== 'revert') {
        return listStyleType
    }

    return tag === 'ol' ? 'decimal' : 'disc'
}

const renderList = (module: EmailModule, context: RenderContext): string => {
    const list = module.descriptor?.list ?? {}
    const computedStyle = list.computedStyle ?? {}
    const linkColor = computedStyle.linkColor ?? context.linkColor
    const indent = computedStyle.liIndent ?? '24px'
    const position = computedStyle.listStylePosition === 'inside' ? 'inside' : 'outside'
    const itemStyle = `margin:0 0 ${computedStyle.liSpacing ?? '0px'} 0;padding:0`
    const html = unwrapListItemParagraphs(applyLinkColor(list.html ?? '', linkColor))
        .replace(/<(ul|ol)(?=[\s>])([^>]*)>/gi, (_match, tag: string, attributes: string) =>
            `<${tag}${withStyleAttribute(attributes, `margin:0;padding:0 0 0 ${indent};list-style-type:${listMarkerType(tag.toLowerCase(), computedStyle.listStyleType)};list-style-position:${position}`)}>`)
        .replace(/<li(?=[\s>])([^>]*)>/gi, (_match, attributes: string) => `<li${withStyleAttribute(attributes, itemStyle)}>`)

    return blockTable('list_block', module, context, `<div style="${styleToString(list.style)}">${html}</div>`)
}

const renderButton = (module: EmailModule, context: RenderContext): string => {
    const button = module.descriptor?.button ?? {}
    const align = module.descriptor?.style?.['text-align'] ?? 'center'
    const buttonStyle = styleToString(button.style, ['width', 'max-width'])
    const widthStyle = button.style?.width && button.style.width !== 'auto' ? `;width:${button.style.width}` : ''
    registerMobileStyle(module, context, 'a.button')

    return blockTable(
        'button_block',
        module,
        context,
        `<div class="alignment" align="${escapeAttribute(align)}"><a class="button" href="${escapeAttribute(button.href)}" target="${escapeAttribute(button.target ?? '_blank')}" style="display:inline-block;text-decoration:none;text-align:center;mso-border-alt:none;word-break:keep-all;${buttonStyle}${widthStyle}"><span style="word-break:break-word">${button.label ?? ''}</span></a></div>`,
        `text-align:${escapeAttribute(align)}`,
    )
}

const renderDivider = (module: EmailModule, context: RenderContext): string => {
    const dividerStyle = module.descriptor?.divider?.style ?? {}
    const align = module.descriptor?.computedStyle?.align ?? 'center'
    const width = dividerStyle.width ?? '100%'

    return blockTable(
        'divider_block',
        module,
        context,
        `<div class="alignment" align="${escapeAttribute(align)}"><table border="0" cellpadding="0" cellspacing="0" role="presentation" width="${escapeAttribute(width)}" style="${MSO_TABLE};width:${escapeAttribute(width)}"><tr><td class="divider_inner" style="font-size:1px;line-height:1px;${styleToString(dividerStyle, ['width'])}"><span style="word-break:break-word">&#8202;</span></td></tr></table></div>`,
    )
}

const renderSpacer = (module: EmailModule, context: RenderContext): string => {
    const height = module.descriptor?.spacer?.style?.height ?? '20px'

    return `<div class="spacer_block block-${context.moduleIndex} ${visibilityClasses(module)}" style="height:${escapeAttribute(height)};line-height:${escapeAttribute(height)};font-size:1px">&#8202;</div>`
}

const renderSocial = (module: EmailModule, context: RenderContext): string => {
    const icons: any[] = module.descriptor?.iconsList?.icons ?? []
    const align = module.descriptor?.style?.['text-align'] ?? 'center'
    const iconWidth = pixels(module.descriptor?.computedStyle?.iconsDefaultWidth, 32)
    const iconPadding = module.descriptor?.computedStyle?.padding ?? '0 5px 0 5px'
    registerMobileStyle(module, context, '.alignment')
    const cells = icons
        .map((icon) => {
            const image = icon.image ?? {}

            return `<td style="padding:${escapeAttribute(iconPadding)}"><a href="${escapeAttribute(image.href)}" target="${escapeAttribute(image.target ?? '_blank')}"><img src="${escapeAttribute(image.src)}" width="${iconWidth}" height="auto" alt="${escapeAttribute(image.alt)}" title="${escapeAttribute(image.title)}" style="display:block;height:auto;border:0"></a></td>`
        })
        .join('')

    return blockTable(
        'social_block',
        module,
        context,
        `<div class="alignment" align="${escapeAttribute(align)}"><table class="social-table" border="0" cellpadding="0" cellspacing="0" role="presentation" style="${MSO_TABLE};display:inline-block"><tr>${cells}</tr></table></div>`,
        `text-align:${escapeAttribute(align)}`,
    )
}

const escapeHtmlText = (value: unknown): string =>
    String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

const cssUrl = (url: string): string => url.replace(/["'()\s]/g, (character) => encodeURIComponent(character))

const linkWrap = (href: string | undefined, target: string | undefined, content: string): string =>
    href ? `<a href="${escapeAttribute(href)}" target="${escapeAttribute(target ?? '_blank')}" style="text-decoration:none;color:inherit">${content}</a>` : content

const renderIconItem = (icon: Record<string, any>, module: EmailModule, context: RenderContext): string => {
    const descriptor = module.descriptor ?? {}
    const iconHeight = pixels(descriptor.computedStyle?.iconHeight, 32)
    const ratio = pixels(icon.width, 0) && pixels(icon.height, 0) ? pixels(icon.width) / pixels(icon.height) : 1
    const iconWidth = Math.round(iconHeight * ratio)
    const textStyle = styleToString(pickStyle(descriptor.style, ['color', 'font-size', 'font-family', 'font-weight']))
    const spacing = styleToString(descriptor.computedStyle?.iconSpacing)

    let image = ''
    if (icon.image) {
        image = `<img src="${escapeAttribute(icon.image)}" width="${iconWidth}" height="${iconHeight}" alt="${escapeAttribute(icon.title ?? icon.text)}" title="${escapeAttribute(icon.title ?? icon.text)}" style="display:block;width:${iconWidth}px;height:auto;margin:0 auto;border:0">`
    } else if (context.isCanvas) {
        image = `<div style="width:${iconHeight}px;height:${iconHeight}px;margin:0 auto;background:#f3f4f6;border:1px dashed #d1d5db;border-radius:6px"></div>`
    }
    const text = icon.text ? `<span style="${textStyle};line-height:1.3">${escapeHtmlText(icon.text)}</span>` : ''
    const linkedImage = image ? linkWrap(icon.href, icon.target, image) : ''
    const linkedText = text ? linkWrap(icon.href, icon.target, text) : ''
    const position = icon.textPosition ?? 'right'

    let content: string
    if (!linkedText) {
        content = linkedImage
    } else if (!linkedImage) {
        content = linkedText
    } else if (position === 'bottom' || position === 'top') {
        const imageRow = `<tr><td align="center" style="text-align:center">${linkedImage}</td></tr>`
        const textRow = `<tr><td align="center" style="text-align:center;padding-${position === 'bottom' ? 'top' : 'bottom'}:6px">${linkedText}</td></tr>`
        content = `<table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="${MSO_TABLE}">${position === 'bottom' ? imageRow + textRow : textRow + imageRow}</table>`
    } else {
        const imageCell = `<td valign="middle" style="vertical-align:middle">${linkedImage}</td>`
        const textCell = `<td valign="middle" style="vertical-align:middle;padding-${position === 'left' ? 'right' : 'left'}:8px">${linkedText}</td>`
        content = `<table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="${MSO_TABLE}"><tr>${position === 'left' ? textCell + imageCell : imageCell + textCell}</tr></table>`
    }

    return `<td class="icon-item" valign="top" style="vertical-align:top;text-align:center;${spacing}">${content}</td>`
}

const renderIcons = (module: EmailModule, context: RenderContext): string => {
    const descriptor = module.descriptor ?? {}
    const icons: Record<string, any>[] = descriptor.iconsList?.icons ?? []
    const align = descriptor.style?.['text-align'] ?? 'center'
    const isVertical = descriptor.computedStyle?.layout === 'vertical'
    const stackClass = !isVertical && descriptor.computedStyle?.stackOnMobile !== false ? 'icons-stack' : ''
    const items = icons.map((icon) => renderIconItem(icon, module, context))
    const rows = isVertical ? items.map((item) => `<tr>${item}</tr>`).join('') : `<tr>${items.join('')}</tr>`

    return blockTable(
        `icons_block ${stackClass}`,
        module,
        context,
        `<table class="icons-row" align="${escapeAttribute(align)}" border="0" cellpadding="0" cellspacing="0" role="presentation" style="${MSO_TABLE};display:inline-block">${rows}</table>`,
        `text-align:${escapeAttribute(align)}`,
    )
}

export const videoHeight = (width: number, ratio: string | undefined): number => {
    const [ratioWidth, ratioHeight] = String(ratio ?? '16-9').split('-').map(Number)

    return Math.round(width * (ratioHeight || 9) / (ratioWidth || 16))
}

const renderVideo = (module: EmailModule, context: RenderContext): string => {
    const video = module.descriptor?.video ?? {}
    if (!video.thumbSrc && !context.isCanvas) {
        return ''
    }
    const width = Math.max(Math.round(context.columnWidth - horizontalPadding(module.descriptor?.style)), 1)
    const height = videoHeight(width, video.thumbRatio)
    const href = escapeAttribute(video.src || '#')
    if (hasCurrentVideoEmailThumbnail(video)) {
        return blockTable(
            'video_block',
            module,
            context,
            `<a href="${href}" target="_blank" title="${escapeAttribute(video.thumbAlt)}" style="display:block;text-decoration:none"><img src="${escapeAttribute(video.emailThumbnail.src)}" width="${width}" alt="${escapeAttribute(video.thumbAlt)}" style="display:block;width:100%;max-width:${width}px;height:auto;border:0"></a>`,
        )
    }
    const thumbnail = video.thumbSrc ? cssUrl(String(video.thumbSrc)) : ''
    const isPlayButtonVisible = String(video.iconType ?? 1) !== '0'
    const buttonSize = pixels(video.iconSize, 64)
    const triangleHeight = Math.round(buttonSize * 0.38)
    const triangleWidth = Math.round(triangleHeight * 0.88)
    const triangleTop = Math.round((buttonSize - triangleHeight) / 2)
    const triangleLeft = Math.round((buttonSize - triangleWidth) / 2 + buttonSize * 0.04)
    const playButton = isPlayButtonVisible
        ? `<span style="display:inline-block;vertical-align:middle;width:${buttonSize}px;height:${buttonSize}px;border-radius:50%;background-color:${escapeAttribute(video.iconColor2 ?? '#000000')};line-height:0;font-size:0;text-align:left"><span style="display:block;width:0;height:0;margin:${triangleTop}px 0 0 ${triangleLeft}px;border-style:solid;border-width:${Math.round(triangleHeight / 2)}px 0 ${Math.round(triangleHeight / 2)}px ${triangleWidth}px;border-color:transparent transparent transparent ${escapeAttribute(video.iconColor1 ?? '#ffffff')}"></span></span>`
        : ''
    const backgroundStyle = thumbnail ? `background-image:url(${thumbnail});background-size:cover;background-position:center;background-repeat:no-repeat;` : ''
    const outlookOpen = thumbnail
        ? `<!--[if gte mso 9]><v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:${width}px;height:${height}px;"><v:fill type="frame" src="${escapeAttribute(video.thumbSrc)}" color="#111111" /><v:textbox inset="0,0,0,0"><![endif]-->`
        : ''
    const outlookClose = thumbnail ? '<!--[if gte mso 9]></v:textbox></v:rect><![endif]-->' : ''

    return blockTable(
        'video_block',
        module,
        context,
        `<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="${MSO_TABLE}"><tr><td ${thumbnail ? `background="${escapeAttribute(video.thumbSrc)}"` : ''} bgcolor="#111111" width="${width}" height="${height}" valign="middle" align="center" style="${backgroundStyle}background-color:#111111;height:${height}px;text-align:center;vertical-align:middle">${outlookOpen}<a href="${href}" target="_blank" title="${escapeAttribute(video.thumbAlt)}" style="display:block;height:${height}px;line-height:${height}px;text-align:center;text-decoration:none">${playButton}</a>${outlookClose}</td></tr></table>`,
    )
}

export const makeFixedWidthRowsFluid = (html: string): string =>
    html.replace(/<table\b[^>]*\bclass="[^"]*\brow-content\b[^"]*"[^>]*>/gi, (tableTag) =>
        tableTag
            .replace(/\swidth="\d+"/i, ' width="100%"')
            .replace(/(?<![-\w])width\s*:\s*(\d+)px/i, 'width:100%;max-width:$1px'))

const renderMergeContent = (module: EmailModule, context: RenderContext): string =>
    blockTable('merge_content_block', module, context, makeFixedWidthRowsFluid(module.descriptor?.mergeContent?.value ?? ''))

export const renderUnsubscribeContent = (module: EmailModule): string => {
    const settings = module.descriptor?.aikuUnsubscribe ?? {}
    const padding = styleToString(pickStyle(module.descriptor?.style, PADDING_KEYS))
    const background = escapeAttribute(settings.backgroundColor ?? '#666666')
    const align = escapeAttribute(settings.align ?? 'center')
    const textStyle = [
        `font-family:${escapeAttribute(settings.fontFamily ?? 'Arial, Helvetica, sans-serif')}`,
        `font-size:${escapeAttribute(settings.fontSize ?? '12px')}`,
        'line-height:150%',
        `color:${escapeAttribute(settings.textColor ?? '#ffffff')}`,
        `text-align:${align}`,
    ].join(';')
    const linkStyle = [
        `color:${escapeAttribute(settings.linkColor ?? '#ffffff')}`,
        `text-decoration:${settings.underline === false ? 'none' : 'underline'}`,
        `font-weight:${settings.linkBold ? 'bold' : 'normal'}`,
    ].join(';')
    const intro = settings.text ? `${escapeHtmlText(settings.text)} ` : ''
    const link = `<a ses:no-track href="${UNSUBSCRIBE_URL_TAG}" target="_blank" style="${linkStyle}">${escapeHtmlText(settings.linkLabel || 'Unsubscribe')}</a>`

    return `<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" bgcolor="${background}" style="${MSO_TABLE};background-color:${background}"><tr><td align="${align}" style="${padding};${textStyle}">${intro}${link}</td></tr></table>`
}

export const tableCellStyles = (settings: Record<string, any>) => {
    const border = `${settings.borderWidth ?? '1px'} solid ${settings.borderColor ?? '#e5e7eb'}`
    const base = `padding:${settings.cellPadding ?? '8px'};border:${border};text-align:${settings.align ?? 'left'};vertical-align:top`

    return {
        table: `border-collapse:collapse;width:100%;font-family:${settings.fontFamily ?? 'Arial, Helvetica, sans-serif'};font-size:${settings.fontSize ?? '14px'};color:${settings.textColor ?? '#374151'};background-color:${settings.backgroundColor ?? '#ffffff'}`,
        header: `${base};background-color:${settings.headerBackgroundColor ?? '#f3f4f6'};color:${settings.headerTextColor ?? '#111827'};font-weight:${settings.headerBold === false ? 'normal' : 'bold'}`,
        cell: (rowIndex: number) => `${base}${settings.striped && rowIndex % 2 === 1 ? `;background-color:${settings.stripeColor ?? '#f9fafb'}` : ''}`,
    }
}

export const renderTableContent = (module: EmailModule): string => {
    const settings = module.descriptor?.aikuTable ?? {}
    const columnCount = tableColumnCount(settings)
    const columnWidth = `${(100 / columnCount).toFixed(2)}%`
    const styles = tableCellStyles(settings)
    const cells = (values: string[], render: (value: string) => string) =>
        Array.from({ length: columnCount }, (_, index) => render(values?.[index] ?? '')).join('')
    const headerRow = settings.hasHeader
        ? `<tr>${cells(settings.header, (value) => `<th width="${columnWidth}" style="${escapeAttribute(styles.header)}">${escapeHtmlText(value) || '&nbsp;'}</th>`)}</tr>`
        : ''
    const bodyRows = (settings.rows ?? []).map((row: string[], rowIndex: number) =>
        `<tr>${cells(row, (value) => `<td width="${columnWidth}" style="${escapeAttribute(styles.cell(rowIndex))}">${escapeHtmlText(value) || '&nbsp;'}</td>`)}</tr>`
    ).join('')

    return `<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="${MSO_TABLE};${escapeAttribute(styles.table)}">${headerRow}${bodyRows}</table>`
}

const renderHtml = (module: EmailModule, context: RenderContext): string => {
    if (isTableModule(module)) {
        return blockTable('table_block', module, context, renderTableContent(module))
    }
    if (isUnsubscribeModule(module)) {
        return `<table class="unsubscribe_block block-${context.moduleIndex} ${visibilityClasses(module)}" ${PRESENTATION_TABLE} style="${MSO_TABLE}"><tr><td>${renderUnsubscribeContent(module)}</td></tr></table>`
    }

    return blockTable('html_block', module, context, module.descriptor?.html?.html ?? '')
}

export const withDerivedHtml = (email: EmailJson): EmailJson => {
    const copy: EmailJson = JSON.parse(JSON.stringify(email))
    for (const row of copy.page.rows) {
        for (const column of row.columns) {
            for (const module of column.modules) {
                if (isUnsubscribeModule(module)) {
                    module.descriptor.html = { html: renderUnsubscribeContent(module) }
                }
                if (isTableModule(module)) {
                    module.descriptor.html = { html: renderTableContent(module) }
                }
            }
        }
    }

    return copy
}

const moduleRenderers: Record<string, (module: EmailModule, context: RenderContext) => string> = {
    [MODULE_TYPES.image]: renderImage,
    [MODULE_TYPES.heading]: renderHeading,
    [MODULE_TYPES.paragraph]: renderParagraph,
    [MODULE_TYPES.text]: renderText,
    [MODULE_TYPES.list]: renderList,
    [MODULE_TYPES.button]: renderButton,
    [MODULE_TYPES.divider]: renderDivider,
    [MODULE_TYPES.spacer]: renderSpacer,
    [MODULE_TYPES.social]: renderSocial,
    [MODULE_TYPES.icons]: renderIcons,
    [MODULE_TYPES.video]: renderVideo,
    [MODULE_TYPES.mergeContent]: renderMergeContent,
    [MODULE_TYPES.html]: renderHtml,
}

export const createRenderContext = (email: EmailJson, columnWidth: number, isCanvas = false): RenderContext => ({
    columnWidth,
    linkColor: email.page.body.content?.computedStyle?.linkColor ?? '#0068A5',
    mobileRules: [],
    moduleIndex: 0,
    isCanvas,
})

export const renderModuleHtml = (module: EmailModule, context: RenderContext): string => {
    context.moduleIndex += 1
    const renderer = moduleRenderers[module.type]

    return renderer ? renderer(module, context) : ''
}

export const messageWidth = (email: EmailJson): number =>
    pixels(email.page.body.content?.computedStyle?.messageWidth, pixels(DEFAULT_MESSAGE_WIDTH))

export const rowWidth = (email: EmailJson, row: EmailRow): number =>
    pixels(row.content?.style?.width, messageWidth(email))

export const columnWidth = (email: EmailJson, row: EmailRow, column: EmailColumn): number =>
    rowWidth(email, row) * (column['grid-columns'] ?? 12) / 12 - horizontalPadding(column.style)

const renderColumn = (email: EmailJson, row: EmailRow, column: EmailColumn, columnIndex: number, context: RenderContext): string => {
    const percentage = Number(((column['grid-columns'] ?? 12) / 12 * 100).toFixed(4))
    const verticalAlign = row.content?.computedStyle?.verticalAlign ?? 'top'
    context.columnWidth = columnWidth(email, row, column)
    const modules = column.modules.map((module) => renderModuleHtml(module, context)).join('')

    return `<td class="column column-${columnIndex + 1}" width="${percentage}%" style="${MSO_TABLE};font-weight:400;text-align:left;${styleToString(column.style)};vertical-align:${verticalAlign};width:${percentage}%">${modules}</td>`
}

const rowVisibilityClasses = (row: EmailRow): string => {
    const computedStyle = row.content?.computedStyle ?? {}

    return [computedStyle.hideContentOnMobile ? 'mobile_hide' : '', computedStyle.hideContentOnDesktop ? 'desktop_hide' : ''].filter(Boolean).join(' ')
}

export const renderRowHtml = (email: EmailJson, row: EmailRow, rowIndex: number, context: RenderContext): string => {
    const width = rowWidth(email, row)
    const stackClass = row.content?.computedStyle?.rowColStackOnMobile === false ? '' : 'stack'
    const columns = row.columns.map((column, columnIndex) => renderColumn(email, row, column, columnIndex, context)).join('')

    return `<table class="row row-${rowIndex + 1} ${rowVisibilityClasses(row)}" align="center" ${PRESENTATION_TABLE} style="${MSO_TABLE};${styleToString(row.container?.style)}"><tbody><tr><td><table class="row-content ${stackClass}" align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="${MSO_TABLE};${styleToString(row.content?.style, ['width'])};width:${width}px;margin:0 auto" width="${width}"><tbody><tr>${columns}</tr></tbody></table></td></tr></tbody></table>`
}

const webFontLinks = (email: EmailJson): string => {
    const usedFonts = JSON.stringify(email.page.rows) + JSON.stringify(email.page.body)

    return (email.page.body.webFonts ?? [])
        .filter((font) => font.url && usedFonts.includes(font.fontFamily.split(',')[0]))
        .map((font) => `<link href="${escapeAttribute(font.url)}" rel="stylesheet" type="text/css">`)
        .join('')
}

export const renderEmailHtml = (email: EmailJson): string => {
    const width = messageWidth(email)
    const context = createRenderContext(email, width)
    const rows = email.page.rows.map((row, rowIndex) => renderRowHtml(email, row, rowIndex, context)).join('')
    const bodyBackground = email.page.body.container?.style?.['background-color'] ?? '#FFFFFF'
    const messageBackground = email.page.body.content?.computedStyle?.messageBackgroundColor ?? 'transparent'
    const bodyStyle = styleToString(email.page.body.content?.style)
    const fontFamily = email.page.body.content?.style?.['font-family'] ?? DEFAULT_FONT_FAMILY

    return `<!DOCTYPE html>
<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="en">
<head>
<title>${escapeAttribute(email.page.title ?? '')}</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch><o:AllowPNG/></o:OfficeDocumentSettings></xml><![endif]-->
${email.page.favicon ? `<link rel="icon" href="${escapeAttribute(email.page.favicon)}">` : ''}
${webFontLinks(email)}
<style>
*{box-sizing:border-box}body{margin:0;padding:0}a[x-apple-data-detectors]{color:inherit!important;text-decoration:inherit!important}#MessageViewBody a{color:inherit;text-decoration:none}p{line-height:inherit}.button p{margin:0}.desktop_hide,.desktop_hide table{mso-hide:all;display:none;max-height:0;overflow:hidden}
@media (max-width:${width + 20}px){.product-cell{display:block!important;width:100%!important}.icons-stack .icons-row,.icons-stack .icons-row tbody,.icons-stack .icons-row tr{display:block!important;width:100%!important}.icons-stack .icon-item{display:block!important;width:100%!important}.row-content{width:100%!important}.stack .column{width:100%!important;display:block!important}.mobile_hide{min-height:0;max-height:0;max-width:0;overflow:hidden;font-size:0;display:none}.desktop_hide,.desktop_hide table{display:table!important;max-height:none!important}${context.mobileRules.join('')}}
</style>
</head>
<body class="body" style="background-color:${bodyBackground};margin:0;padding:0;-webkit-text-size-adjust:none;text-size-adjust:none">
<table class="nl-container" ${PRESENTATION_TABLE} style="${MSO_TABLE};background-color:${bodyBackground}"><tbody><tr><td style="font-family:${fontFamily}"><div style="${bodyStyle};background-color:${messageBackground}">${rows}</div></td></tr></tbody></table>
</body>
</html>`
}
