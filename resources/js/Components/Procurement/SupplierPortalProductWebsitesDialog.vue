<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-11 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGlobe, faExternalLink, faFolder, faBox } from "@fal"

library.add(faGlobe, faExternalLink, faFolder, faBox)

interface ProductWebsites {
	product: { code: string; name: string | null }
	websites: {
		website: string
		domain: string
		product_code: string
		product_name: string | null
		product_url: string | null
		family_code: string | null
		family_name: string | null
		family_url: string | null
	}[]
}

const props = defineProps<{
	productWebsitesRoute: string
}>()

const supplierProductId = defineModel<number | null>({ required: true })

const detail = ref<ProductWebsites | null>(null)
const isLoading = ref(false)
const loadError = ref<string | null>(null)

const isVisible = computed({
	get: () => supplierProductId.value !== null,
	set: (visible: boolean) => {
		if (!visible) {
			supplierProductId.value = null
		}
	},
})

watch(supplierProductId, async (id) => {
	detail.value = null
	loadError.value = null
	if (id === null) {
		return
	}

	isLoading.value = true
	try {
		const { data } = await axios.get<ProductWebsites>(props.productWebsitesRoute.replace(/\/0\/websites$/, `/${id}/websites`))
		if (supplierProductId.value === id) {
			detail.value = data
		}
	} catch {
		loadError.value = ctrans("The websites could not be loaded. Try again.")
	} finally {
		isLoading.value = false
	}
})
</script>

<template>
	<Dialog
		v-model:visible="isVisible"
		modal
		dismissableMask
		:header="ctrans('Where this product is sold')"
		:style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="isLoading" class="flex justify-center py-12"><LoadingIcon /></div>
		<p v-else-if="loadError" class="py-6 text-center text-xs text-red-600">{{ loadError }}</p>

		<div v-else-if="detail" class="space-y-3 text-sm">
			<div>
				<span class="mr-1.5 whitespace-nowrap font-semibold text-gray-900">{{ detail.product.code }}</span>
				<span class="text-gray-600">{{ detail.product.name }}</span>
			</div>

			<p v-if="!detail.websites.length" class="py-6 text-center text-xs text-gray-400">{{ ctrans("This product is not on any of our websites yet") }}</p>

			<ul v-else class="divide-y divide-gray-100 rounded border border-gray-200">
				<li v-for="(row, index) in detail.websites" :key="index" class="space-y-1.5 p-3">
					<div class="flex flex-wrap items-baseline justify-between gap-x-3">
						<span class="font-semibold text-gray-800">
							<FontAwesomeIcon icon="fal fa-globe" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />{{ row.website }}
						</span>
						<span class="text-xs text-gray-400">{{ row.domain }}</span>
					</div>
					<div class="grid gap-1.5 sm:grid-cols-2">
						<a v-if="row.family_url" :href="row.family_url" target="_blank" rel="noopener" class="group flex items-center gap-1.5 rounded border border-gray-200 px-2 py-1.5 hover:border-[var(--app-accent-muted)] hover:bg-[var(--app-accent-soft)]">
							<FontAwesomeIcon icon="fal fa-folder" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
							<span class="min-w-0 flex-1 truncate">
								<span class="text-xs text-gray-400">{{ ctrans("Family") }}</span>
								<span class="ml-1 text-gray-700">{{ row.family_name ?? row.family_code }}</span>
							</span>
							<FontAwesomeIcon icon="fal fa-external-link" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
						</a>
						<span v-else class="flex items-center gap-1.5 rounded border border-dashed border-gray-200 px-2 py-1.5 text-xs text-gray-400">
							<FontAwesomeIcon icon="fal fa-folder" fixed-width aria-hidden="true" />{{ ctrans("No live family page") }}
						</span>
						<a v-if="row.product_url" :href="row.product_url" target="_blank" rel="noopener" class="group flex items-center gap-1.5 rounded border border-gray-200 px-2 py-1.5 hover:border-[var(--app-accent-muted)] hover:bg-[var(--app-accent-soft)]">
							<FontAwesomeIcon icon="fal fa-box" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
							<span class="min-w-0 flex-1 truncate">
								<span class="text-xs text-gray-400">{{ ctrans("Product") }}</span>
								<span class="ml-1 whitespace-nowrap text-gray-700">{{ row.product_code }}</span>
							</span>
							<FontAwesomeIcon icon="fal fa-external-link" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
						</a>
						<span v-else class="flex items-center gap-1.5 rounded border border-dashed border-gray-200 px-2 py-1.5 text-xs text-gray-400">
							<FontAwesomeIcon icon="fal fa-box" fixed-width aria-hidden="true" />{{ ctrans("No live product page") }}
						</span>
					</div>
				</li>
			</ul>
		</div>
	</Dialog>
</template>
