import type { FormatLongFn, Locale } from 'date-fns'
import { hi } from 'date-fns/locale'

type Width = 'narrow' | 'abbreviated' | 'short' | 'wide'
type FormatWidth = 'full' | 'long' | 'medium' | 'short'

const devanagariDigits = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९']

const toNepaliDigits = (value: number | string): string =>
    value.toString().replace(/\d/g, (digit) => devanagariDigits[Number(digit)])

const eras = {
    narrow: ['ई.पू.', 'ई.सं.'],
    abbreviated: ['ई.पू.', 'ई.सं.'],
    wide: ['ईसा पूर्व', 'ईसवी संवत'],
}

const quarters = {
    narrow: ['१', '२', '३', '४'],
    abbreviated: ['त्रै१', 'त्रै२', 'त्रै३', 'त्रै४'],
    wide: ['पहिलो त्रैमास', 'दोस्रो त्रैमास', 'तेस्रो त्रैमास', 'चौथो त्रैमास'],
}

const months = {
    narrow: ['ज', 'फे', 'मा', 'अ', 'मे', 'जु', 'जु', 'अ', 'से', 'अ', 'नो', 'डि'],
    abbreviated: ['जन', 'फेब', 'मार्च', 'अप्रि', 'मे', 'जुन', 'जुला', 'अग', 'सेप्ट', 'अक्टो', 'नोभे', 'डिसे'],
    wide: ['जनवरी', 'फेब्रुअरी', 'मार्च', 'अप्रिल', 'मे', 'जुन', 'जुलाई', 'अगस्ट', 'सेप्टेम्बर', 'अक्टोबर', 'नोभेम्बर', 'डिसेम्बर'],
}

const days = {
    narrow: ['आ', 'सो', 'मं', 'बु', 'बि', 'शु', 'श'],
    short: ['आइ', 'सोम', 'मंगल', 'बुध', 'बिही', 'शुक्र', 'शनि'],
    abbreviated: ['आइत', 'सोम', 'मंगल', 'बुध', 'बिही', 'शुक्र', 'शनि'],
    wide: ['आइतबार', 'सोमबार', 'मंगलबार', 'बुधबार', 'बिहीबार', 'शुक्रबार', 'शनिबार'],
}

const dayPeriods: Record<string, string> = {
    am: 'पूर्वाह्न',
    pm: 'अपराह्न',
    midnight: 'मध्यरात',
    noon: 'मध्याह्न',
    morning: 'बिहान',
    afternoon: 'दिउँसो',
    evening: 'साँझ',
    night: 'राति',
}

const pickWidth = <T extends Record<string, string[]>>(values: T, width: string | undefined, fallback: keyof T): string[] =>
    values[(width && width in values ? width : fallback) as keyof T]

const distances: Record<string, { one: string, other: string } | string> = {
    lessThanXSeconds: { one: '१ सेकेन्डभन्दा कम', other: '{{count}} सेकेन्डभन्दा कम' },
    xSeconds: { one: '१ सेकेन्ड', other: '{{count}} सेकेन्ड' },
    halfAMinute: 'आधा मिनेट',
    lessThanXMinutes: { one: '१ मिनेटभन्दा कम', other: '{{count}} मिनेटभन्दा कम' },
    xMinutes: { one: '१ मिनेट', other: '{{count}} मिनेट' },
    aboutXHours: { one: 'लगभग १ घण्टा', other: 'लगभग {{count}} घण्टा' },
    xHours: { one: '१ घण्टा', other: '{{count}} घण्टा' },
    xDays: { one: '१ दिन', other: '{{count}} दिन' },
    aboutXWeeks: { one: 'लगभग १ हप्ता', other: 'लगभग {{count}} हप्ता' },
    xWeeks: { one: '१ हप्ता', other: '{{count}} हप्ता' },
    aboutXMonths: { one: 'लगभग १ महिना', other: 'लगभग {{count}} महिना' },
    xMonths: { one: '१ महिना', other: '{{count}} महिना' },
    aboutXYears: { one: 'लगभग १ वर्ष', other: 'लगभग {{count}} वर्ष' },
    xYears: { one: '१ वर्ष', other: '{{count}} वर्ष' },
    overXYears: { one: '१ वर्षभन्दा बढी', other: '{{count}} वर्षभन्दा बढी' },
    almostXYears: { one: 'झन्डै १ वर्ष', other: 'झन्डै {{count}} वर्ष' },
}

const dateFormats: Record<FormatWidth, string> = {
    full: 'EEEE, do MMMM, y',
    long: 'do MMMM, y',
    medium: 'd MMM, y',
    short: 'dd/MM/yyyy',
}

const timeFormats: Record<FormatWidth, string> = {
    full: 'h:mm:ss a zzzz',
    long: 'h:mm:ss a z',
    medium: 'h:mm:ss a',
    short: 'h:mm a',
}

const dateTimeFormats: Record<FormatWidth, string> = {
    full: '{{date}}, {{time}}',
    long: '{{date}}, {{time}}',
    medium: '{{date}}, {{time}}',
    short: '{{date}}, {{time}}',
}

const relativeFormats: Record<string, string> = {
    lastWeek: "'गत' eeee p",
    yesterday: "'हिजो' p",
    today: "'आज' p",
    tomorrow: "'भोलि' p",
    nextWeek: "eeee p",
    other: 'P',
}

const formatWidth = (formats: Record<FormatWidth, string>): FormatLongFn =>
    (options = {}) => formats[options.width && options.width !== 'any' ? options.width : 'full']

export const ne: Locale = {
    code: 'ne',
    formatDistance: (token, count, options) => {
        const entry = distances[token]
        const text = typeof entry === 'string'
            ? entry
            : (count === 1 ? entry.one : entry.other.replace('{{count}}', toNepaliDigits(count)))

        if (options?.addSuffix) {
            return options.comparison && options.comparison > 0 ? `${text} पछि` : `${text} अघि`
        }

        return text
    },
    formatLong: {
        date: formatWidth(dateFormats),
        time: formatWidth(timeFormats),
        dateTime: formatWidth(dateTimeFormats),
    },
    formatRelative: (token) => relativeFormats[token],
    localize: {
        ordinalNumber: (value) => toNepaliDigits(Number(value)),
        era: (value, options) => pickWidth(eras, options?.width as Width, 'wide')[value],
        quarter: (value, options) => pickWidth(quarters, options?.width as Width, 'wide')[value - 1],
        month: (value, options) => pickWidth(months, options?.width as Width, 'wide')[value],
        day: (value, options) => pickWidth(days, options?.width as Width, 'wide')[value],
        dayPeriod: (value) => dayPeriods[value] ?? value,
    },
    match: hi.match,
    options: {
        weekStartsOn: 0,
        firstWeekContainsDate: 1,
    },
}
