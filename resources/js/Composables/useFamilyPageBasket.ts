import { computed, ref, ComputedRef, Ref } from "vue"

export interface ProductInBasketItem {
	transaction_id?: number
	quantity_ordered?: number | string
	quantity_ordered_new?: number | string
	department_id?: number | null
	sub_department_id?: number | null
	family_id?: number | null
	is_golden_product?: boolean
}

export interface FamilyPage {
	productInBasket: {
		isLoading: boolean
		list: Record<string, ProductInBasketItem>
	}
}

/**
 * Basket state of the products shown on the current family/category page,
 * plus the quantity_ordered totals and golden product flags grouped by family_id derived from it.
 *
 * Shared by the Iris and Retina layout stores so both expose the same shape.
 *
 * @return array{family_page: Ref<FamilyPage>, family_quantity_ordered: ComputedRef<Record<string, number>>, family_has_golden_product: ComputedRef<Record<string, boolean>>}
 */
export const useFamilyPageBasket = (): {
	family_page: Ref<FamilyPage>
	family_quantity_ordered: ComputedRef<Record<string, number>>
	family_has_golden_product: ComputedRef<Record<string, boolean>>
} => {
	const family_page = ref<FamilyPage>({
		productInBasket: {
			isLoading: false,
			list: {},
		},
	})

	const family_quantity_ordered = computed<Record<string, number>>(() => {
		const totals: Record<string, number> = {}

		for (const item of Object.values(family_page.value?.productInBasket?.list ?? {})) {
			if (item?.family_id == null) {
				continue
			}

			const quantity = Number(item.quantity_ordered_new ?? item.quantity_ordered ?? 0)

			totals[item.family_id] = (totals[item.family_id] ?? 0) + (Number.isFinite(quantity) ? quantity : 0)
		}

		return totals
	})

	const family_has_golden_product = computed<Record<string, boolean>>(() => {
		const flags: Record<string, boolean> = {}

		for (const item of Object.values(family_page.value?.productInBasket?.list ?? {})) {
			if (item?.family_id == null || !item.is_golden_product) {
				continue
			}

			const quantity = Number(item.quantity_ordered_new ?? item.quantity_ordered ?? 0)

			if (Number.isFinite(quantity) && quantity > 0) {
				flags[item.family_id] = true
			}
		}

		return flags
	})

	return { family_page, family_quantity_ordered, family_has_golden_product }
}
