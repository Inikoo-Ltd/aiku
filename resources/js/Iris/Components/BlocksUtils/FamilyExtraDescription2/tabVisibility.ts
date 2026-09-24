export type FamilyExtraDescriptionTabKey =
	| "about"
	| "marketing"
	| "faq"
	| "customisation"
	| "labeling guide"
	| "storage_and_shelf_life"
	| "regulatory_label_information"

export const AROMA_ONLY_TABS: FamilyExtraDescriptionTabKey[] = [
	"customisation",
	"labeling guide",
	"storage_and_shelf_life",
]

const hasValue = (value: unknown): boolean => String(value ?? "").trim() !== ""

export const demoteHeadingOne = (html: unknown): string =>
	String(html ?? "").replace(/<(\/?)h1(?=[\s>])/gi, "<$1h2")

export const hasRichTextContent = (html?: string | null): boolean =>
	hasValue(
		String(html ?? "")
			.replace(/<[^>]*>/g, "")
			.replace(/&nbsp;/g, " ")
	)

export const hasCustomisationOptions = (family: any): boolean => {
	const options = family?.customize_option

	if (!Array.isArray(options)) {
		return false
	}

	return options.some(
		(option: any) =>
			Boolean(option?.available) || hasValue(option?.moq) || hasValue(option?.notes)
	)
}

export const hasLabelingGuideFile = (family: any): boolean =>
	hasValue(family?.labeling_guide?.route?.name)

export const hasStorageOptions = (family: any): boolean => {
	const storageOption = family?.storage_option ?? {}
	const conditions = storageOption?.storage_conditions
	const guidelines = storageOption?.storage_guidelines

	const hasConditions =
		Array.isArray(conditions) && conditions.some((condition: any) => hasValue(condition?.value))

	const hasGuidelines =
		Array.isArray(guidelines) && guidelines.some((guideline: any) => hasValue(guideline?.text))

	return hasConditions || hasGuidelines || hasValue(storageOption?.storage_temperature)
}

export const isTabVisible = (
	tabKey: FamilyExtraDescriptionTabKey,
	family: any,
	isLoggedIn: boolean
): boolean => {
	switch (tabKey) {
		case "about":
			return hasRichTextContent(family?.description_extra)

		case "marketing":
			return isLoggedIn

		case "faq":
			return Array.isArray(family?.faq) && family.faq.length > 0

		case "customisation":
			return Boolean(family?.is_aroma_organisation) && hasCustomisationOptions(family)

		case "labeling guide":
			return Boolean(family?.is_aroma_organisation) && hasLabelingGuideFile(family)

		case "storage_and_shelf_life":
			return Boolean(family?.is_aroma_organisation) && hasStorageOptions(family)

		case "regulatory_label_information":
			return false

		default:
			return true
	}
}

export const hasLabelInfoContent = (product: any): boolean =>
	Object.values(product?.label_info ?? {}).some((item: any) => item?.show === true)

export const isLabelInfoApproved = (product: any): boolean =>
	product?.is_label_info_approved === true

/**
 * Product webpages expose the same tab data under `fieldValue.tabs`,
 * where the about tab is driven by the product description and its extra description.
 */
export const hasProductAboutContent = (tabs: any, product: any): boolean =>
	hasRichTextContent(tabs?.description) || hasRichTextContent(product?.description_extra)

export const isProductTabVisible = (
	tabKey: FamilyExtraDescriptionTabKey,
	tabs: any,
	product: any,
	isLoggedIn: boolean,
	isWorkshop = false
): boolean => {
	switch (tabKey) {
		case "about":
			return hasProductAboutContent(tabs, product)

		case "regulatory_label_information":
			return (
				isWorkshop ||
				(isLoggedIn && isLabelInfoApproved(product) && hasLabelInfoContent(product))
			)

		default:
			return isTabVisible(tabKey, tabs, isLoggedIn)
	}
}
