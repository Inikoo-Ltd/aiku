export interface ComparisonTemplateItem {
    label: string
    show?: boolean
    value?: string | null
}

export type ComparisonTemplate = Record<string, ComparisonTemplateItem>

export const COMPARISON_TEMPLATES = {
    BATH_BOMBS: {
        dimensions: {
            label: "Weight / Size",
            show: true,
        },
        product_count: {
            label: "Number of Products in Range",
            show: true,
        },
        fragrance: {
            label: "Fragrance Options",
            value: null,
        },
        packaging: {
            label: "Packaging Type",
            show: true,
        },
        customization: {
            label: "Customisation Available",
            show: true,
        },
        key_ingredients: {
            label: "Key Ingredient",
            value: null,
        },
        special_ingredients: {
            label: "Special Ingredient",
            value: null,
        },
        special_feature: {
            label: "Special Feature",
            value: null,
        },
    },

    SOAPS: {
        dimensions: {
            label: "Weight / Size",
            show: true,
        },
        product_count: {
            label: "Number of Products in Range",
            show: true,
        },
        fragrance: {
            label: "Fragrance / Aromatherapy",
            value: null,
        },
        packaging: {
            label: "Packaging Type",
            show: true,
        },
        customization: {
            label: "Customisation Available",
            show: true,
        },
        key_ingredients: {
            label: "Key Ingredient",
            value: null,
        },
        sub_type: {
            label: "Soap Type",
            value: null,
        },
        special_feature: {
            label: "Special Feature",
            value: null,
        },
    },

    ESSENTIAL_FRAGRANCE_CARRIER_OILS: {
        dimensions: {
            label: "Bottle Size Options",
            show: true,
        },
        product_count: {
            label: "Number of Products in Range",
            show: true,
        },
        fragrance: {
            label: "Fragrance / Aroma Variety",
            value: null,
        },
        packaging: {
            label: "Packaging Options",
            show: true,
        },
        customization: {
            label: "Customisation Available",
            show: true,
        },
        key_ingredients: {
            label: "Key Ingredient",
            value: null,
        },
        sub_type: {
            label: "Oil Type",
            value: null,
        },
        usage: {
            label: "Applications / Usage",
            value: null,
        },
        synthetic: {
            label: "Natural / Synthetic",
            value: null,
        },
    },

    COSMETICS: {
        dimensions: {
            label: "Pack Size / Volume",
            show: true,
        },
        product_count: {
            label: "Number of Products in Range",
            show: true,
        },
        fragrance: {
            label: "Fragrance / Unscented",
            value: null,
        },
        packaging: {
            label: "Packaging Type",
            show: true,
        },
        customization: {
            label: "Customisation Available",
            show: true,
        },
        key_ingredients: {
            label: "Key Ingredient",
            value: null,
        },
        sub_type: {
            label: "Product Type",
            value: null,
        },
        special_ingredients: {
            label: "Active / Hero Ingredient",
            value: null,
        },
        main_benefit: {
            label: "Main Benefit",
            value: null,
        },
        benefit_type: {
            label: "Skin / Hair Type",
            value: null,
        },
        format: {
            label: "Texture / Format",
            value: null,
        },
    },

    CUSTOM: {
        dimensions: {
            label: "Dimensions",
            show: true,
        },
        product_count: {
            label: "Product Count",
            show: true,
        },
        packaging: {
            label: "Packaging",
            show: true,
        },
        customization: {
            label: "Customization",
            show: true,
        },
    },
} satisfies Record<string, ComparisonTemplate>

export type ComparisonTemplateName = keyof typeof COMPARISON_TEMPLATES