const lines = [
    'ship it before the light goes.',
    'a box packed well is a promise kept.',
    'every shelf remembers what it held.',
    'count twice, carry once.',
    'the day is a queue; clear it kindly.',
    'small things, sent far, arrive as big things.',
    'paper, ink, and a plan. then coffee.',
    'stock moves; so do we.',
    'what is picked is half-way home.',
    'a good label is a love letter to a stranger.',
    'the warehouse is quiet before it is busy. enjoy the quiet.',
    'the order was placed by a person. pack it like one.',
    'slow is smooth, smooth is fast.',
    'numbers are stories that agreed to behave.',
    'make the next line on the list easier than this one.',
    'invoices are memories with totals.',
    'the van leaves at four. the van always leaves at four.',
    'a barcode is a tiny poem only the scanner can read.',
    'today: fewer errors, more tea.',
    'leave the bay tidier than you found it.',
    'one source of truth, many stories.',
    'measure the pallet, not the mood.',
    'tomorrow\'s dispatch starts with today\'s put-away.',
    'kind words, clean data.',
    'somewhere a customer just smiled at a parcel. that was you.',
    'the spreadsheet is not the business. the business is the business.',
    'be the person who updates the stock count.',
    'even the returns teach us something.',
    'a calm dashboard is earned, not given.',
    'good morning. the inbox can wait five minutes.',
    'nothing ships itself. thank you.',
]

const seasonal: Record<string, string[]> = {
    xmas: [
        'every parcel is a small gift. these ones especially.',
        'the busiest week, the kindest boxes.',
        'tape, tinsel, and a tidy pick list.',
        'deck the shelves. then empty them.',
        'december: where the year goes out in boxes.',
    ],
    newyear: [
        'new year, same stock count. let\'s make it right.',
        'fresh ledger. fresh coffee.',
        'january is for put-away and promises.',
    ],
    winter: [
        'cold hands, warm dispatch.',
        'short days, long lists. we\'ve got this.',
        'the van still leaves at four, even in the dark.',
    ],
    spring: [
        'spring: the catalogue wakes up.',
        'new season, new families, same care.',
        'open the doors. let the returns air out.',
    ],
    summer: [
        'summer: pack light, pack right.',
        'the warehouse is warm. the water is where it should be.',
        'long days, short queues. keep them short.',
        'sunscreen for you, bubble wrap for them.',
    ],
    autumn: [
        'autumn: stock up, slow down, then don\'t.',
        'leaves fall; delivery notes shouldn\'t.',
        'the big season is coming. the shelves know.',
    ],
}

const seasonFor = (month: number, day: number): string | null => {
    if ((month === 12 && day >= 6) || (month === 1 && day <= 1)) return 'xmas'
    if (month === 1 && day <= 10) return 'newyear'
    if (month === 12 || month <= 2) return 'winter'
    if (month >= 3 && month <= 5) return 'spring'
    if (month >= 6 && month <= 8) return 'summer'
    if (month >= 9 && month <= 11) return 'autumn'
    return null
}

export const dailyLine = (): string => {
    const now = new Date()
    const start = Date.UTC(now.getUTCFullYear(), 0, 0)
    const dayOfYear = Math.floor((Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()) - start) / 86400000)
    const season = seasonFor(now.getMonth() + 1, now.getDate())
    const special = season ? seasonal[season] : []
    const pool = dayOfYear % 3 === 0 && special.length ? special : [...special, ...lines]

    return pool[dayOfYear % pool.length]
}
