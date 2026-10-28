<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSearch, faBox, faClipboard } from "@fal"

library.add(faSearch, faBox, faClipboard)

interface Results {
	products: { code: string; name: string }[]
	orders: { reference: string; state_label: string }[]
}

const props = defineProps<{
	searchRoute: string
}>()

const emit = defineEmits<{
	(e: "product", code: string): void
	(e: "order", reference: string): void
}>()

const isOpen = ref(false)
const query = ref("")
const results = ref<Results>({ products: [], orders: [] })
const isSearching = ref(false)
const isMac = typeof navigator !== "undefined" && navigator.platform.includes("Mac")
let timer: ReturnType<typeof setTimeout> | undefined
let lastRequest = 0

watch(query, (value) => {
	clearTimeout(timer)
	if (value.trim().length < 2) {
		results.value = { products: [], orders: [] }
		return
	}
	timer = setTimeout(async () => {
		const request = ++lastRequest
		isSearching.value = true
		try {
			const response = await axios.get(props.searchRoute, { params: { q: value.trim() } })
			if (request === lastRequest) {
				results.value = response.data
			}
		} finally {
			if (request === lastRequest) {
				isSearching.value = false
			}
		}
	}, 250)
})

const choose = (kind: "product" | "order", value: string) => {
	isOpen.value = false
	query.value = ""
	kind === "product" ? emit("product", value) : emit("order", value)
}

const onKeydown = (event: KeyboardEvent) => {
	if ((isMac ? event.metaKey : event.ctrlKey) && event.key.toLowerCase() === "k") {
		event.preventDefault()
		isOpen.value = true
	}
}

onMounted(() => window.addEventListener("keydown", onKeydown))
onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown))
</script>

<template>
	<div>
		<button
			type="button"
			class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-sm text-gray-400 shadow-sm transition hover:border-gray-300 sm:w-64"
			:aria-label="ctrans('Search')"
			@click="isOpen = true">
			<FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" />
			<span class="hidden flex-1 text-left sm:inline">{{ ctrans("Search orders or products") }}</span>
			<kbd class="hidden rounded border border-gray-300 px-1.5 text-xs text-gray-500 sm:inline">{{ isMac ? "⌘" : "Ctrl" }} K</kbd>
		</button>

		<Dialog v-model:visible="isOpen" modal dismissableMask :showHeader="false" position="top" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }" :pt="{ content: { class: '!p-3' } }">
			<IconField>
				<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
				<InputText v-model="query" autofocus class="w-full" :placeholder="ctrans('Order number, product code or name')" :aria-label="ctrans('Search')" />
			</IconField>

			<div class="mt-3 max-h-[60vh] space-y-3 overflow-y-auto text-sm">
				<p v-if="query.trim().length < 2" class="px-1 text-xs text-gray-400">{{ ctrans("Type at least 2 letters") }}</p>
				<p v-else-if="!isSearching && !results.orders.length && !results.products.length" class="px-1 text-xs text-gray-400">{{ ctrans("Nothing found") }}</p>

				<div v-if="results.orders.length">
					<div class="px-1 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Purchase orders") }}</div>
					<button
						v-for="order in results.orders"
						:key="order.reference"
						type="button"
						class="flex w-full items-center gap-2.5 rounded px-2 py-2 text-left hover:bg-gray-100"
						@click="choose('order', order.reference)">
						<FontAwesomeIcon icon="fal fa-clipboard" class="text-gray-400" fixed-width aria-hidden="true" />
						<span class="font-medium text-gray-800">{{ order.reference }}</span>
						<span class="ml-auto text-xs text-gray-500">{{ order.state_label }}</span>
					</button>
				</div>

				<div v-if="results.products.length">
					<div class="px-1 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Products") }}</div>
					<button
						v-for="product in results.products"
						:key="product.code"
						type="button"
						class="flex w-full items-center gap-2.5 rounded px-2 py-2 text-left hover:bg-gray-100"
						@click="choose('product', product.code)">
						<FontAwesomeIcon icon="fal fa-box" class="text-gray-400" fixed-width aria-hidden="true" />
						<span class="font-medium text-gray-800">{{ product.code }}</span>
						<span class="truncate text-gray-500">{{ product.name }}</span>
					</button>
				</div>
			</div>
		</Dialog>
	</div>
</template>
