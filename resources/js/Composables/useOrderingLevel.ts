import { trans } from "laravel-vue-i18n"

export type OrderingLevel = "cartons" | "skos" | "units"

export const getOrderingLevels = () => [
	{
		key: "cartons" as OrderingLevel,
		icon: "fal fa-pallet",
		tab: trans("Ordering Cartons"),
		description: trans("Carton description"),
		quantity: trans("Cartons"),
		cost: trans("Carton cost"),
		singular: trans("Carton"),
	},
	{
		key: "skos" as OrderingLevel,
		icon: "fal fa-box",
		tab: trans("Ordering SKOs"),
		description: trans("SKO description"),
		quantity: trans("SKOs"),
		cost: trans("SKO cost"),
		singular: trans("SKO"),
	},
	{
		key: "units" as OrderingLevel,
		icon: "fal fa-stop-circle",
		tab: trans("Ordering Units"),
		description: trans("Unit description"),
		quantity: trans("Units"),
		cost: trans("Unit cost"),
		singular: trans("Unit"),
	},
]

export const unitsPerOrderingLevel = (item: any, level: OrderingLevel): number => {
	if (level === "cartons") {
		return Number(item?.units_per_carton) || 1
	}

	if (level === "skos") {
		return Number(item?.units_per_pack) || 1
	}

	return 1
}
