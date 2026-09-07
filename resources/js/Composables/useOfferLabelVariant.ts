export type OfferLabelVariant = "default" | "step"

const OFFER_TYPE_LABEL_VARIANTS: Record<string, OfferLabelVariant> = {
	"Product Quantity Ordered": "step",
}

export function getOfferLabelVariant(offer?: { type?: string } | null): OfferLabelVariant {
	return OFFER_TYPE_LABEL_VARIANTS[offer?.type ?? ""] ?? "default"
}

export const SHOP_ORDERED_SUB_TRIGGER = "so"

export function isShopOrderedSubTrigger(subTrigger?: string | null): boolean {
	return subTrigger === SHOP_ORDERED_SUB_TRIGGER
}
