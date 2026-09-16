export const redOfferTypes = [
	"Category Ordered",
	"Category Quantity Ordered",
	"Category Amount Ordered",
	"Department Ordered",
	"Department Quantity Ordered",
	"Subdepartment Ordered",
	"Subdepartment Quantity Ordered",
]

export const isRedOfferType = (type?: string | null): boolean => redOfferTypes.includes(String(type ?? ""))

export const getOfferTextColorClass = (offer?: { type?: string | null } | null): string | null => {
	if (!offer) {
		return null
	}

	return isRedOfferType(offer.type) ? "text-red-700" : "text-[#E87928]"
}
