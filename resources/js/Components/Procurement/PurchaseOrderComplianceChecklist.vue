<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-24 09:25
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import axios from "axios"
import Checkbox from "primevue/checkbox"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faDownload, faUpload } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

export type ComplianceChecklistFile = {
	name: string
	purchase_order_reference: string | null
	download_route: routeType
}

export type ComplianceChecklistDocument = {
	scope: string
	label: string
	files: ComplianceChecklistFile[]
	has_files: boolean
	checked: boolean
	checked_by: string | null
	checked_at: string | null
}

export type ComplianceChecklistProduct = {
	trade_unit_id: number
	code: string
	name: string | null
	route: routeType
	documents: ComplianceChecklistDocument[]
}

export type ComplianceChecklist = {
	products: ComplianceChecklistProduct[]
	upload_route: routeType | null
	check_route: routeType | null
}

const props = defineProps<{
	data?: ComplianceChecklist | null
}>()

const emit = defineEmits<{
	load: []
	changed: []
}>()

onMounted(() => {
	if (!props.data) {
		emit("load")
	}
})

const totalDocuments = computed(() => props.data?.products.reduce((total, product) => total + product.documents.length, 0) ?? 0)
const checkedDocuments = computed(() => props.data?.products.reduce((total, product) => total + product.documents.filter((document) => document.checked).length, 0) ?? 0)

const rowKey = (product: ComplianceChecklistProduct, document: ComplianceChecklistDocument) => `${product.trade_unit_id}-${document.scope}`

const savingKey = ref<string | null>(null)
const error = ref<{ key: string, message: string } | null>(null)

const errorMessage = (exception: any): string => {
	const errors = exception?.response?.data?.errors
	return (errors && (Object.values(errors)[0] as string[])?.[0]) || exception?.response?.data?.message || ctrans("Something went wrong")
}

async function toggleCheck(product: ComplianceChecklistProduct, document: ComplianceChecklistDocument, checked: boolean) {
	if (!props.data?.check_route) {
		return
	}
	const key = rowKey(product, document)
	savingKey.value = key
	error.value = null
	try {
		await axios.patch(route(props.data.check_route.name, { ...props.data.check_route.parameters, tradeUnit: product.trade_unit_id }), { scope: document.scope, checked })
		emit("changed")
	} catch (exception: any) {
		error.value = { key, message: errorMessage(exception) }
	} finally {
		savingKey.value = null
	}
}

async function upload(product: ComplianceChecklistProduct, document: ComplianceChecklistDocument, event: Event) {
	const input = event.target as HTMLInputElement
	const files = Array.from(input.files ?? [])
	input.value = ""
	if (!files.length || !props.data?.upload_route) {
		return
	}
	const key = rowKey(product, document)
	savingKey.value = key
	error.value = null
	try {
		await axios.post(
			route(props.data.upload_route.name, { ...props.data.upload_route.parameters, tradeUnit: product.trade_unit_id }),
			{ attachments: files, scope: document.scope },
			{ headers: { "Content-Type": "multipart/form-data" } },
		)
		emit("changed")
	} catch (exception: any) {
		error.value = { key, message: errorMessage(exception) }
	} finally {
		savingKey.value = null
	}
}

const checkTooltip = (document: ComplianceChecklistDocument): string | undefined => {
	if (document.has_files) {
		return ctrans("Checked: it has a file")
	}
	if (document.checked && document.checked_by && document.checked_at) {
		return ctrans("Checked by :user, :date", { user: document.checked_by, date: useFormatTime(document.checked_at) })
	}
	return undefined
}
</script>

<template>
	<div class="space-y-3 text-sm">
		<div v-if="!data" class="space-y-2">
			<div class="skeleton h-5 w-1/2 rounded" />
			<div class="skeleton h-24 rounded" />
			<div class="skeleton h-24 rounded" />
		</div>

		<template v-else-if="data.products.length">
			<p class="text-gray-600">
				{{ ctrans(":checked of :total documents are checked and OK for this order", { checked: checkedDocuments, total: totalDocuments }) }}
			</p>

			<div class="max-h-[55vh] space-y-3 overflow-y-auto pr-1">
				<section v-for="product in data.products" :key="product.trade_unit_id" class="rounded-md border border-gray-200">
					<header class="flex flex-wrap items-baseline gap-x-2 border-b border-gray-100 bg-gray-50 px-3 py-1.5">
						<Link :href="route(product.route.name, product.route.parameters)" class="primaryLink whitespace-nowrap font-medium">{{ product.code }}</Link>
						<span v-if="product.name" class="text-xs text-gray-500">{{ product.name }}</span>
					</header>

					<ul class="divide-y divide-gray-100">
						<li v-for="document in product.documents" :key="document.scope" class="px-3 py-1.5">
							<div class="flex items-start gap-2">
								<FontAwesomeIcon
									v-if="document.has_files"
									:icon="faCheckCircle"
									class="mt-0.5 text-green-600"
									fixed-width
									v-tooltip="checkTooltip(document)"
									aria-hidden="true"
								/>
								<Checkbox
									v-else
									:modelValue="document.checked"
									binary
									size="small"
									class="mt-0.5"
									:inputId="`compliance-${rowKey(product, document)}`"
									:disabled="!data.check_route || savingKey !== null"
									v-tooltip="checkTooltip(document)"
									@update:modelValue="(checked: boolean) => toggleCheck(product, document, checked)"
								/>
								<div class="min-w-0 flex-1">
									<label :for="`compliance-${rowKey(product, document)}`" class="whitespace-nowrap" :class="document.checked ? 'text-gray-800' : 'text-gray-500'">{{ document.label }}</label>
									<p v-if="document.checked && !document.has_files && document.checked_by" class="text-xs text-gray-400">
										{{ ctrans("Checked by :user, :date", { user: document.checked_by, date: document.checked_at ? useFormatTime(document.checked_at) : "" }) }}
									</p>
									<ul v-if="document.files.length" class="mt-0.5 space-y-0.5">
										<li v-for="(file, index) in document.files" :key="index" class="flex flex-wrap items-center gap-x-2 text-xs">
											<a :href="route(file.download_route.name, file.download_route.parameters)" class="flex min-w-0 items-center gap-1 text-gray-600 hover:text-[var(--app-accent)]">
												<FontAwesomeIcon :icon="faDownload" fixed-width aria-hidden="true" />
												<span class="truncate">{{ file.name }}</span>
											</a>
											<span v-if="file.purchase_order_reference" class="whitespace-nowrap text-gray-400">{{ ctrans("from PO :reference", { reference: file.purchase_order_reference }) }}</span>
										</li>
									</ul>
								</div>
								<LoadingIcon v-if="savingKey === rowKey(product, document)" class="mt-0.5" />
								<label
									v-else-if="data.upload_route"
									class="mt-0.5 cursor-pointer text-gray-400 hover:text-[var(--app-accent)]"
									v-tooltip="ctrans('Upload :document', { document: document.label })"
								>
									<FontAwesomeIcon :icon="faUpload" fixed-width aria-hidden="true" />
									<input type="file" multiple accept="image/*,.pdf" class="hidden" :disabled="savingKey !== null" @change="(event) => upload(product, document, event)" />
								</label>
							</div>
							<p v-if="error?.key === rowKey(product, document)" class="mt-1 text-xs text-red-600">{{ error.message }}</p>
						</li>
					</ul>
				</section>
			</div>

			<p class="text-xs text-gray-500">{{ ctrans("Uploads are kept on the product, so they count for every order of it. Ticking every document does not complete compliance: mark it complete above.") }}</p>
		</template>

		<p v-else class="text-gray-500">{{ ctrans("No products on this order yet") }}</p>
	</div>
</template>
