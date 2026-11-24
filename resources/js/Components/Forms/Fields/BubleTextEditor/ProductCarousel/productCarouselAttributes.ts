export const MAX_PRODUCTS_PER_CAROUSEL = 20

export const DEFAULT_FAMILY_PRODUCTS_LIMIT = 12

export const PRODUCTS_PER_VIEW_OPTIONS = [3, 4, 5, 6] as const

export type ProductCarouselSource = "products" | "family"

export const CAROUSEL_TEXT_TAGS = ["h2", "h3", "h4", "h5", "h6", "p"] as const

export type CarouselTextTag = (typeof CAROUSEL_TEXT_TAGS)[number]

export const DEFAULT_TITLE_TAG: CarouselTextTag = "h3"

export const DEFAULT_PRODUCT_NAME_TAG: CarouselTextTag = "p"

export interface ProductCarouselAttributes {
	source: ProductCarouselSource
	codes: string[]
	family: string
	limit: number
	title: string
	perView: number
	titleTag: CarouselTextTag
	productNameTag: CarouselTextTag
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
	titleTag: DEFAULT_TITLE_TAG,
	productNameTag: DEFAULT_PRODUCT_NAME_TAG,
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

export const carouselTextTag = (value: unknown, fallback: CarouselTextTag): CarouselTextTag => {
	const tag = String(value ?? "").toLowerCase()

	return (CAROUSEL_TEXT_TAGS as readonly string[]).includes(tag) ? (tag as CarouselTextTag) : fallback
}
