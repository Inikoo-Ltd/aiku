export const compactCount = (value: number): string => (value >= 1000 ? `${Math.floor(value / 1000)}k` : String(value))
