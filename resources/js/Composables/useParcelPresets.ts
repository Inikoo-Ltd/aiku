export interface ParcelPreset {
    label: string
    weight: number | null
    dimensions: number[]
}

export const parcelPresets: ParcelPreset[] = [
    { label: '39 × 39 × 39 cm', weight: null, dimensions: [39, 39, 39] },
    { label: '40 × 40 × 40 cm', weight: null, dimensions: [40, 40, 40] },
    { label: '60 × 40 × 30 cm', weight: null, dimensions: [60, 40, 30] },
    { label: '60 × 50 × 40 cm', weight: null, dimensions: [60, 50, 40] },
    { label: '60 × 60 × 40 cm', weight: null, dimensions: [60, 60, 40] },
    { label: '41 × 24 × 31 cm', weight: null, dimensions: [41, 24, 31] },
    { label: '94 × 48 × 37 cm', weight: null, dimensions: [94, 48, 37] },
    { label: '94 × 48 × 43 cm', weight: null, dimensions: [94, 48, 43] },
    { label: '22 × 19 × 17 cm', weight: null, dimensions: [22, 19, 17] },
    { label: '68 × 68 × 44 cm', weight: null, dimensions: [68, 68, 44] },
]

export const findParcelPreset = (dimensions: (number | null)[] | undefined): ParcelPreset | undefined =>
    parcelPresets.find(preset => preset.dimensions.every((dimension, index) => dimension === dimensions?.[index]))

export const applyParcelPreset = (parcel: { dimensions: (number | null)[]; weight: number | null }, preset: ParcelPreset | null) => {
    if (!preset) {
        return
    }

    parcel.dimensions = [...preset.dimensions]

    if (preset.weight) {
        parcel.weight = preset.weight
    }
}
