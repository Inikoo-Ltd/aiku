<!--
    * Author: Raul Perusquia <raul@inikoo.com>
    * Created: Sat, 14 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
    * Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

type Product = { id: number; code: string | null; name: string | null }

const props = defineProps<{
	addRoute: routeType
	products: Product[] | null | undefined
}>()

const visible = defineModel<boolean>("visible", { default: false })

const productId = ref<number | null>(null)
const skoQuantity = ref<number | null>(null)
const errors = ref<string[]>([])
const isSubmitting = ref(false)

watch(visible, (isVisible) => {
	if (isVisible) {
		productId.value = null
		skoQuantity.value = null
		errors.value = []
	}
})

const submit = () => {
	router.post(
		route(props.addRoute.name, props.addRoute.parameters),
		{ org_supplier_product_id: productId.value, sko_quantity: skoQuantity.value },
		{
			preserveScroll: true,
			onStart: () => (isSubmitting.value = true),
			onFinish: () => (isSubmitting.value = false),
			onError: (error) => (errors.value = Object.values(error || {}) as string[]),
			onSuccess: () => (visible.value = false),
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Add item not on the purchase order')" :style="{ width: '32rem' }" :breakpoints="{ '640px': '95vw' }">
		<div class="space-y-4 text-sm">
			<p class="text-gray-500">
				{{ ctrans("The supplier sent a product that is on none of this delivery's purchase orders. It is added as received and counted, at the supplier's price, and then put away like the other lines.") }}
			</p>

			<label class="block space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("Product") }} <span class="text-red-500">*</span></span>
				<div v-if="!products" class="skeleton h-10 rounded" />
				<Select
					v-else
					v-model="productId"
					:options="products"
					optionValue="id"
					:optionLabel="(product: Product) => `${product.code ?? ''} ${product.name ?? ''}`"
					filter
					fluid
					:placeholder="ctrans('Search by code or name')"
					:emptyMessage="ctrans('This supplier has no active products')"
				>
					<template #option="{ option }">
						<span class="whitespace-nowrap font-medium">{{ option.code }}</span>
						<span class="ml-2 text-gray-500">{{ option.name }}</span>
					</template>
				</Select>
			</label>

			<label class="block space-y-1">
				<span class="text-xs text-gray-500">{{ ctrans("SKOs received") }} <span class="text-red-500">*</span></span>
				<InputNumber v-model="skoQuantity" :min="0" :maxFractionDigits="4" fluid />
			</label>

			<div v-if="errors.length" class="space-y-0.5 text-xs text-red-600">
				<div v-for="error in errors" :key="error">{{ error }}</div>
			</div>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Add to delivery')" :disabled="!productId || !skoQuantity || isSubmitting" @click="submit">
					<template #icon>
						<LoadingIcon v-if="isSubmitting" />
						<FontAwesomeIcon v-else icon="fal fa-plus" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
