type ProcurementSearchResultItem = {
	id: number
}

export type ProcurementSearchResults = Record<string, ProcurementSearchResultItem[]>

const routeSections: { fragments: string[]; section: string }[] = [
	{ fragments: [".agent_supplier_purchase_orders."], section: "agent_supplier_purchase_orders" },
	{ fragments: [".org_supplier_products.", ".supplier_products."], section: "supplier_products" },
	{ fragments: [".purchase_orders.", ".purchase-orders."], section: "purchase_orders" },
	{ fragments: [".stock_deliveries.", ".stock-deliveries."], section: "stock_deliveries" },
	{
		fragments: [".org_agent_suppliers.", ".org_suppliers.", ".suppliers."],
		section: "suppliers",
	},
	{ fragments: [".org_agents."], section: "agents" },
]

export function preferredProcurementSearchSection(routeName: string): string | null {
	return (
		routeSections.find(({ fragments }) =>
			fragments.some((fragment) => routeName.includes(fragment))
		)?.section ?? null
	)
}

export function prioritiseProcurementSearchResults(
	results: ProcurementSearchResults | null,
	routeName: string
): ProcurementSearchResults | null {
	const preferredSection = preferredProcurementSearchSection(routeName)

	if (!results || !preferredSection || !results[preferredSection]?.length) {
		return results
	}

	return {
		[preferredSection]: results[preferredSection],
		...Object.fromEntries(
			Object.entries(results).filter(([section]) => section !== preferredSection)
		),
	}
}

export function firstProcurementSearchResultSection(
	results: ProcurementSearchResults | null
): string | null {
	return Object.entries(results ?? {}).find(([, items]) => items.length)?.[0] ?? null
}
