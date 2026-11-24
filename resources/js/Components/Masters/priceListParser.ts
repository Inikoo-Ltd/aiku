/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 13:55
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

export type PriceListColumnRole = "code" | "master_prices" | "master_rrps" | "master_rrps_per_unit" | "ignore"

export interface PriceListColumn {
    header: string
    role: PriceListColumnRole
    currency: string | null
}

export interface PriceListRow {
    code: string
    master_prices: Record<string, number>
    master_rrps: Record<string, number>
    master_rrps_per_unit: Record<string, number>
}

export interface ParsedPriceList {
    hasHeader: boolean
    cells: string[][]
    columns: PriceListColumn[]
}

const CURRENCY_SYMBOLS: Record<string, string> = {
    "£": "GBP",
    "€": "EUR",
    "$": "USD",
    "zł": "PLN",
    "kč": "CZK",
    "ft": "HUF",
    "lei": "RON",
    "kr": "SEK",
    "₴": "UAH",
}

const detectDelimiter = (lines: string[]): string | RegExp => {
    const text = lines.join("\n")
    if (text.includes("\t")) {
        return "\t"
    }
    if (text.includes(";")) {
        return ";"
    }
    if (lines.every(line => line.includes(","))) {
        return ","
    }
    if (lines.some(line => / {2,}/.test(line))) {
        return / {2,}/
    }

    return /\s+/
}

export const parseAmount = (raw: string | undefined, delimiter: string | RegExp = "\t"): number | null => {
    let cleaned = (raw ?? "").replace(/[^\d.,-]/g, "")
    if (!cleaned) {
        return null
    }

    const lastComma = cleaned.lastIndexOf(",")
    const lastDot = cleaned.lastIndexOf(".")
    if (lastComma > -1 && lastDot > -1) {
        const decimalSeparator = lastComma > lastDot ? "," : "."
        const thousandsSeparator = decimalSeparator === "," ? "." : ","
        cleaned = cleaned.split(thousandsSeparator).join("").replace(decimalSeparator, ".")
    } else if (lastComma > -1 && delimiter !== ",") {
        cleaned = cleaned.replace(",", ".")
    }

    const amount = Number(cleaned)

    return Number.isFinite(amount) && amount > 0 ? amount : null
}

const detectCurrency = (header: string, currencyCodes: string[]): string | null => {
    const tokens = header.toUpperCase().match(/[A-Z]{3}/g) ?? []
    const knownToken = tokens.find(token => currencyCodes.includes(token))
    if (knownToken) {
        return knownToken
    }

    const lowerHeader = header.toLowerCase()
    const symbol = Object.keys(CURRENCY_SYMBOLS).find(symbol => lowerHeader.includes(symbol))

    return symbol ? CURRENCY_SYMBOLS[symbol] : null
}

export const guessColumn = (header: string, currencyCodes: string[]): PriceListColumn => {
    const lowerHeader = header.toLowerCase()
    const currency = detectCurrency(header, currencyCodes)

    if (!currency && /\b(code|sku|kode|ref|reference)\b/.test(lowerHeader)) {
        return { header, role: "code", currency: null }
    }

    if (/rrp|retail|msrp|recommended|srp/.test(lowerHeader)) {
        const isPerUnit = /unit|\/\s*u\b|per\s*u|each|\bpcs?\b|\bea\b/.test(lowerHeader)

        return { header, role: isPerUnit ? "master_rrps_per_unit" : "master_rrps", currency }
    }

    if (currency || /price|harga|selling|wholesale|trade/.test(lowerHeader)) {
        return { header, role: "master_prices", currency }
    }

    return { header, role: "ignore", currency: null }
}

const looksLikeHeader = (cells: string[]): boolean =>
    cells.slice(1).filter(cell => cell.trim() !== "").every(cell => parseAmount(cell) === null)

export const parsePriceList = (text: string, currencyCodes: string[]): ParsedPriceList => {
    const lines = text.split(/\r?\n/).filter(line => line.trim() !== "")
    if (!lines.length) {
        return { hasHeader: false, cells: [], columns: [] }
    }

    const delimiter = detectDelimiter(lines)
    const rows = lines.map(line => line.split(delimiter).map(cell => cell.trim()))
    const columnCount = Math.max(...rows.map(row => row.length))
    const hasHeader = looksLikeHeader(rows[0])

    const columns = Array.from({ length: columnCount }, (_, index) => {
        if (hasHeader) {
            return guessColumn(rows[0][index] ?? "", currencyCodes)
        }

        return { header: "", role: index === 0 ? "code" : "ignore", currency: null } as PriceListColumn
    })

    return {
        hasHeader,
        cells: hasHeader ? rows.slice(1) : rows,
        columns,
    }
}

export const buildPriceListRows = (cells: string[][], columns: PriceListColumn[]): PriceListRow[] => {
    const codeIndex = columns.findIndex(column => column.role === "code")
    if (codeIndex === -1) {
        return []
    }

    return cells
        .filter(row => (row[codeIndex] ?? "").trim() !== "")
        .map(row => {
            const priceListRow: PriceListRow = {
                code: row[codeIndex].trim(),
                master_prices: {},
                master_rrps: {},
                master_rrps_per_unit: {},
            }

            columns.forEach((column, index) => {
                if (column.role === "code" || column.role === "ignore" || !column.currency) {
                    return
                }
                const amount = parseAmount(row[index])
                if (amount !== null) {
                    priceListRow[column.role][column.currency] = amount
                }
            })

            return priceListRow
        })
}
