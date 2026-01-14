// Convert 1 to 1st, 2 to 2nd, 3 to 3rd, etc..
export const useOrdinalSuffix = (n: number) => {
    const s = ["th", "st", "nd", "rd"]
    const v = n % 100
    if (v >= 11 && v <= 13) {
        return n + "th"
    }
    return n + (s[(v % 10)] || s[0])
}


export function formatPercentage(value?: string): string {
  if (!value) return ""
  return value.replace(/\.0%$/, "%")
}
