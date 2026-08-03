/**
 *  author: Vika Aqordi
 *  created on: 18-10-2024
 *  github: https://github.com/aqordeon
 *  copyright: 2024
 */

import { defineStore } from "pinia"
import { Image } from "@/types/Image"
import { Colors } from "@/types/Color"
import { ref } from "vue"
import { useColorTheme } from "@/Composables/useStockList"
import { useFamilyPageBasket } from "@/Composables/useFamilyPageBasket"


interface User {
	id: number
	avatar_thumbnail: Image
	email: string
	username: string
}

interface App {
	name: string
	color: unknown | Colors
	theme: string[]
	url: string | null
	environment: string | null
}

interface RightBasketProduct {
	family_id?: number | null
	department_id?: number | null
	sub_department_id?: number | null
	quantity_ordered?: number | string | null
	quantity_ordered_new?: number | string | null
}

interface CategoryQuantityOrdered {
	family: Record<string, number>
	department: Record<string, number>
	sub_department: Record<string, number>
}

const getLocalStorage = () => {
	let storageIris = {}
	if (typeof window !== "undefined" && window.localStorage) {
		storageIris = JSON.parse(localStorage.getItem("iris") || "{}") // Get layout from localStorage
		return storageIris
	}

	return storageIris
}

export const useIrisLayoutStore = defineStore("irisLayout", () => {
	const user = ref<User | null>(null)
	const app = ref<App>({
		name: "", // For styling navigation depend on which App
		color: null, // Styling layout colour
		theme: useColorTheme[3], // For styling app colour (same as Retina)
		url: null, // For url on logo top left
		environment: null, // 'local' | 'staging'
	})

	const iris_varnish = {
		isFetching: false,
	}
	const iris = {
		is_logged_in: getLocalStorage().is_logged_in || false,
	}
	const iris_variables = getLocalStorage().iris_variables || {}
	const offer_meters = getLocalStorage().offer_meters || {}
	const currentRoute = ref<string | undefined>("iris.login") // Define value to avoid route null at the first load
	const currentParams = ref<{ [key: string]: string }>({})
	const currentQuery = ref<{ [key: string]: string }>({})
	const outboxes = ref(null)
	const isSidebarLoaded = ref(false)

	const { family_page, family_quantity_ordered } = useFamilyPageBasket()

	const rightbasket = ref<{ show: boolean; products: RightBasketProduct[] }>({
        show: false,
        products: [],
    })

	// Quantity ordered totals grouped by family, department and sub_department,
	// derived from the products currently in the right side basket.
	// const category_quantity_ordered = computed<CategoryQuantityOrdered>(() => {
	// 	const totals: CategoryQuantityOrdered = {
	// 		family: {},
	// 		department: {},
	// 		sub_department: {},
	// 	}

	// 	for (const product of rightbasket.value?.products ?? []) {
	// 		const quantity = Number(product?.quantity_ordered_new ?? product?.quantity_ordered ?? 0)

	// 		if (product?.family_id != null) {
	// 			totals.family[product.family_id] = (totals.family[product.family_id] ?? 0) + quantity
	// 		}

	// 		if (product?.department_id != null) {
	// 			totals.department[product.department_id] = (totals.department[product.department_id] ?? 0) + quantity
	// 		}

	// 		if (product?.sub_department_id != null) {
	// 			totals.sub_department[product.sub_department_id] = (totals.sub_department[product.sub_department_id] ?? 0) + quantity
	// 		}
	// 	}

	// 	return totals
	// })

	return {
		user,
		app,
		currentRoute,
		currentParams,
		currentQuery,
		iris_varnish,
		iris_variables,
		offer_meters,
		iris,
		isSidebarLoaded,
		outboxes,
		rightbasket,
		family_page,
		family_quantity_ordered,
		// category_quantity_ordered,
	}
})
