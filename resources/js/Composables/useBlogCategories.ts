export interface BlogCategoryOption {
	value: string
	label: string
}

const CATALOGUE_CATEGORIES: BlogCategoryOption[] = [
	{ value: "newsletters", label: "Newsletters" },
	{ value: "product_guides", label: "Product Guides" },
	{ value: "business_tips", label: "Business Tips" },
]

const DROPSHIPPING_CATEGORIES: BlogCategoryOption[] = [
	{ value: "integrations_guides", label: "Integrations Guides" },
	{ value: "dropshipping_guides", label: "Dropshipping Guides" },
	{ value: "product_guides", label: "Product Guides" },
]

/**
 * Mirrors WebpageSubTypeEnum::blogCategories(). A blueprint reached without a webpage has no shop
 * to read, so it is offered every category rather than silently hiding the ones a shop uses.
 */
export const getBlogCategoryOptions = (shopType?: string | null): BlogCategoryOption[] => {
	if (shopType === "dropshipping") {
		return DROPSHIPPING_CATEGORIES
	}

	if (!shopType) {
		return [
			...CATALOGUE_CATEGORIES,
			...DROPSHIPPING_CATEGORIES.filter(
				category => !CATALOGUE_CATEGORIES.some(known => known.value === category.value)
			),
		]
	}

	return CATALOGUE_CATEGORIES
}

export const getShopType = (webpageData?: any): string | undefined => webpageData?.shop?.type
