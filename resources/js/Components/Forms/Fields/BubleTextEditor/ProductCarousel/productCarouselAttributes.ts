export const MAX_PRODUCTS_PER_CAROUSEL = 20

export const DEFAULT_FAMILY_PRODUCTS_LIMIT = 12

export const PRODUCTS_PER_VIEW_OPTIONS = [3, 4, 5, 6] as const

export type ProductCarouselSource = "products" | "family"

export interface ProductCarouselAttributes {
	source: ProductCarouselSource
	codes: string[]
	family: string
	limit: number
	title: string
	perView: number
}

export interface ProductCarouselRoutes {
	lookupProducts: { name: string, parameters: Record<string, any> }
	searchProducts: { name: string, parameters: Record<string, any> }
	searchFamilies: { name: string, parameters: Record<string, any> }
}

export const defaultProductCarouselAttributes = (): ProductCarouselAttributes => ({
	source: "products",
	codes: [],
	family: "",
	limit: DEFAULT_FAMILY_PRODUCTS_LIMIT,
	title: "",
	perView: 4,
})

export const parseProductCodes = (text: string): string[] => {
	const seen = new Set<string>()

	return text
		.split(",")
		.map((code) => code.trim())
		.filter((code) => {
			const key = code.toLowerCase()
			if (!code || seen.has(key)) {
				return false
			}
			seen.add(key)

			return true
		})
		.slice(0, MAX_PRODUCTS_PER_CAROUSEL)
}

export const productCarouselSource = (value: unknown): ProductCarouselSource => (value === "family" ? "family" : "products")

export const familyProductsLimit = (value: unknown): number => {
	const limit = Math.round(Number(value))

	return limit >= 1 ? Math.min(limit, MAX_PRODUCTS_PER_CAROUSEL) : DEFAULT_FAMILY_PRODUCTS_LIMIT
}

export const productsPerView = (value: unknown): number => {
	const perView = Number(value)

	return (PRODUCTS_PER_VIEW_OPTIONS as readonly number[]).includes(perView) ? perView : defaultProductCarouselAttributes().perView
}
