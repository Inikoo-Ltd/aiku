import { ctrans } from "@/Composables/useTrans"

export type DocSummary = {
	slug: string
	title: string
	summary: string
	category: string | null
	series: string | null
	order: number
	lang: string
	url: string
}

export const DOC_CATEGORY_ORDER = ["getting-started", "sales-channels", "products", "orders", "payments", "account", "troubleshooting", "other"]

export const docCategoryLabel = (category: string | null): string => {
	const labels: Record<string, string> = {
		"getting-started": ctrans("Getting started"),
		"sales-channels": ctrans("Sales channels"),
		products: ctrans("Products"),
		orders: ctrans("Orders"),
		payments: ctrans("Payments"),
		account: ctrans("Your account"),
		troubleshooting: ctrans("When something goes wrong"),
	}

	return labels[category ?? ""] ?? ctrans("Other guides")
}
