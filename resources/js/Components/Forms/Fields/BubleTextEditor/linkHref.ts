export type LinkType = 'web' | 'email' | 'phone' | 'sms'

export interface ReadyMadeLink {
    key: string
    label: string
    url: string
}

const withoutQuery = (url: string | null | undefined): string => {
    const [withoutFragment, fragment] = String(url ?? '').trim().split('#')

    return withoutFragment.split('?')[0].replace(/\/$/, '') + (fragment ? '#' + fragment : '')
}

export const findReadyMadeLink = (readyMadeLinks: ReadyMadeLink[] | undefined, url: string | null | undefined): ReadyMadeLink | null =>
    (readyMadeLinks ?? []).find((link) => withoutQuery(link.url) === withoutQuery(url)) ?? null

export interface LinkDialogValue {
    href: string
    text: string
    title: string
    target: '_blank' | '_self'
    underline?: boolean
}

export interface LinkHrefFields {
    type: LinkType
    url: string
    email: string
    subject: string
    body: string
    phone: string
}

const emptyFields = (): LinkHrefFields => ({ type: 'web', url: '', email: '', subject: '', body: '', phone: '' })

const queryParameters = (query: string): URLSearchParams =>
    new URLSearchParams(query.replace(/\+/g, '%2B'))

export const parseLinkHref = (href: string | null | undefined): LinkHrefFields => {
    const fields = emptyFields()
    const value = String(href ?? '').trim()
    const [target, query = ''] = value.split('?')
    const lowerCaseValue = value.toLowerCase()

    if (lowerCaseValue.startsWith('mailto:')) {
        const parameters = queryParameters(query)

        return { ...fields, type: 'email', email: decodeURIComponent(target.slice(7)), subject: parameters.get('subject') ?? '', body: parameters.get('body') ?? '' }
    }
    if (lowerCaseValue.startsWith('tel:')) {
        return { ...fields, type: 'phone', phone: decodeURIComponent(target.slice(4)) }
    }
    if (lowerCaseValue.startsWith('sms:')) {
        return { ...fields, type: 'sms', phone: decodeURIComponent(target.slice(4)), body: queryParameters(query).get('body') ?? '' }
    }

    return { ...fields, url: value }
}

const encodeQuery = (parameters: Record<string, string>): string => {
    const query = Object.entries(parameters)
        .filter(([, value]) => value.trim() !== '')
        .map(([key, value]) => `${key}=${encodeURIComponent(value)}`)
        .join('&')

    return query ? `?${query}` : ''
}

export const buildLinkHref = (fields: LinkHrefFields): string => {
    switch (fields.type) {
        case 'email':
            return fields.email.trim() ? `mailto:${fields.email.trim()}${encodeQuery({ subject: fields.subject, body: fields.body })}` : ''
        case 'phone':
            return fields.phone.trim() ? `tel:${fields.phone.replace(/\s+/g, '')}` : ''
        case 'sms':
            return fields.phone.trim() ? `sms:${fields.phone.replace(/\s+/g, '')}${encodeQuery({ body: fields.body })}` : ''
        default:
            return fields.url.trim()
    }
}
