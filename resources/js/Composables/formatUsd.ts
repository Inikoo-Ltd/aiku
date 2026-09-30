export const usd = (value: number) => value > 0 && value < 0.01 ? "<$0.01" : "$" + value.toFixed(2)
