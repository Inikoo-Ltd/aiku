import assert from "node:assert/strict"
import test from "node:test"
import {
	firstProcurementSearchResultSection,
	preferredProcurementSearchSection,
	prioritiseProcurementSearchResults,
} from "../../resources/js/Components/Search/prioritiseProcurementSearchResults.ts"

test("maps procurement routes to their matching result sections", () => {
	const routes = new Map([
		["grp.org.procurement.org_suppliers.index", "suppliers"],
		["grp.org.procurement.org_agents.show.suppliers.index", "suppliers"],
		["grp.org.procurement.org_agents.index", "agents"],
		["grp.org.procurement.org_supplier_products.index", "supplier_products"],
		["grp.org.procurement.org_suppliers.show.supplier_products.index", "supplier_products"],
		["grp.org.procurement.purchase_orders.index", "purchase_orders"],
		["grp.org.procurement.org_agents.show.purchase-orders.index", "purchase_orders"],
		["grp.org.procurement.stock_deliveries.index", "stock_deliveries"],
		["grp.org.procurement.org_agents.show.stock-deliveries.index", "stock_deliveries"],
		[
			"grp.org.procurement.agent_supplier_purchase_orders.index",
			"agent_supplier_purchase_orders",
		],
		["grp.org.procurement.dashboard", null],
	])

	for (const [routeName, expectedSection] of routes) {
		assert.equal(preferredProcurementSearchSection(routeName), expectedSection)
	}
})

test("places the current procurement section first while retaining all results", () => {
	const results = {
		purchase_orders: [{ id: 1 }],
		stock_deliveries: [{ id: 2 }],
		suppliers: [{ id: 3 }],
		supplier_products: [{ id: 4 }],
	}

	const prioritisedResults = prioritiseProcurementSearchResults(
		results,
		"grp.org.procurement.org_suppliers.index"
	)

	assert.deepEqual(Object.keys(prioritisedResults ?? {}), [
		"suppliers",
		"purchase_orders",
		"stock_deliveries",
		"supplier_products",
	])
	assert.deepEqual(prioritisedResults, {
		suppliers: [{ id: 3 }],
		purchase_orders: [{ id: 1 }],
		stock_deliveries: [{ id: 2 }],
		supplier_products: [{ id: 4 }],
	})
})

test("preserves the original ordering when the route has no matching section", () => {
	const results = {
		purchase_orders: [{ id: 1 }],
		suppliers: [{ id: 2 }],
	}

	assert.equal(
		prioritiseProcurementSearchResults(results, "grp.org.procurement.dashboard"),
		results
	)
})

test("falls back to the first group with results when the route group is empty", () => {
	const results = {
		purchase_orders: [{ id: 1 }],
		suppliers: [],
		supplier_products: [{ id: 2 }],
	}

	const prioritisedResults = prioritiseProcurementSearchResults(
		results,
		"grp.org.procurement.org_suppliers.index"
	)

	assert.equal(prioritisedResults, results)
	assert.equal(firstProcurementSearchResultSection(prioritisedResults), "purchase_orders")
})
