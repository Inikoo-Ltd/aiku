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

export const dailyLine = (): string => {
    const now = new Date()
    const start = Date.UTC(now.getUTCFullYear(), 0, 0)
    const day = Math.floor((Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()) - start) / 86400000)
    return lines[day % lines.length]
}
