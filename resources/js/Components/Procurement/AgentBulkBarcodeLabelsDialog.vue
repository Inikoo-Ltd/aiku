<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-02 10:17
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { debounce } from "lodash-es"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFilePdf, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faFilePdf, faTimes)

export interface BulkLabels {
	route: routeType
	check_route: routeType
	sizes: Record<string, { key: string; label: string }[]>
	default_size: string
	batch_pattern: string
	max_org_stocks: number
}

const props = defineProps<{
	orgStockIds: number[]
	bulkLabels: BulkLabels
}>()

const visible = defineModel<boolean>("visible", { required: true })

const level = ref("unit")
const standard = ref<"localised" | "unified">("localised")
const batch = ref("")
const size = ref(props.bulkLabels.default_size)

const levelOptions = [
	{ label: ctrans("Unit"), value: "unit" },
	{ label: ctrans("SKO"), value: "sko" },
	{ label: ctrans("Carton"), value: "carton" },
]

const standardOptions = [
	{ label: ctrans("Localised"), value: "localised" },
	{ label: ctrans("Unified"), value: "unified" },
]

const sizeOptions = computed(() => (props.bulkLabels.sizes[level.value] ?? []).map((option) => ({ label: option.label, value: option.key })))

watch(level, () => {
	if (!sizeOptions.value.some((option) => option.value === size.value)) {
		size.value = sizeOptions.value.some((option) => option.value === props.bulkLabels.default_size)
			? props.bulkLabels.default_size
			: sizeOptions.value[0]?.value
	}
})

const normalisedBatch = computed(() => batch.value.trim().toUpperCase())

const isBatchValid = computed(() => standard.value !== "unified" || new RegExp(props.bulkLabels.batch_pattern).test(normalisedBatch.value))

const isOverLimit = computed(() => props.orgStockIds.length > props.bulkLabels.max_org_stocks)

const query = computed(() => ({
	...props.bulkLabels.route.parameters,
	org_stocks: props.orgStockIds.join(","),
	level: level.value,
	standard: standard.value,
	...(standard.value === "unified" ? { batch: normalisedBatch.value } : { size: size.value }),
}))

const check = ref<{ printable: number; skipped: { id: number; code: string; reason: string }[] } | null>(null)
const isChecking = ref(false)
const errorMessage = ref<string | null>(null)

let checkRequest = 0

const runCheck = async () => {
	const request = ++checkRequest
	errorMessage.value = null

	if (!visible.value || !props.orgStockIds.length || isOverLimit.value || !isBatchValid.value) {
		check.value = null
		isChecking.value = false
		return
	}

	isChecking.value = true
	try {
		const { data } = await axios.get(route(props.bulkLabels.check_route.name, { ...query.value, ...props.bulkLabels.check_route.parameters }))
		if (request === checkRequest) {
			check.value = data
		}
	} catch (error: any) {
		if (request === checkRequest) {
			check.value = null
			errorMessage.value = error?.response?.data?.message || ctrans("Could not check the labels")
		}
	} finally {
		if (request === checkRequest) {
			isChecking.value = false
		}
	}
}

const debouncedCheck = debounce(runCheck, 400)

watch(() => [visible.value, props.orgStockIds.join(","), level.value, standard.value], () => {
	if (visible.value) {
		runCheck()
	}
}, { immediate: true })

watch(batch, () => {
	isChecking.value = isBatchValid.value
	debouncedCheck()
})

const canPrint = computed(() => !isChecking.value && !isOverLimit.value && isBatchValid.value && (check.value?.printable ?? 0) > 0)

const openPdf = () => {
	window.open(route(props.bulkLabels.route.name, query.value), "_blank")
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Print :count barcode labels', { count: orgStockIds.length })"
		:style="{ width: '32rem' }" :breakpoints="{ '640px': '95vw' }" :draggable="false">
		<div class="flex flex-col gap-4">
			<div class="flex flex-wrap items-center justify-between gap-3">
				<SegmentedToggle v-model="level" :options="levelOptions" :ariaLabel="ctrans('Label')" />
				<SegmentedToggle v-model="standard" :options="standardOptions" :ariaLabel="ctrans('Label standard')" />
			</div>

			<label v-if="standard === 'unified'" class="flex flex-col gap-1 text-sm text-gray-700">
				{{ ctrans("Batch code") }}
				<InputText v-model="batch" maxlength="20" :invalid="!!batch && !isBatchValid" class="!text-sm uppercase focus:!border-[var(--app-accent)]" />
				<span v-if="!isBatchValid" class="text-xs text-gray-400">
					{{ ctrans("The batch code takes up to 20 characters: A to Z, 0 to 9 and hyphen") }}
				</span>
			</label>

			<div v-else>
				<div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
					{{ ctrans("Size (mm)") }}
				</div>
				<SegmentedToggle v-model="size" :options="sizeOptions" :ariaLabel="ctrans('Size (mm)')" class="flex-wrap" />
			</div>

			<div v-if="isOverLimit" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
				{{ ctrans("Up to :max products can be printed at once", { max: bulkLabels.max_org_stocks }) }}
			</div>

			<div v-else-if="isChecking" class="text-sm text-gray-400">
				<LoadingIcon />
				{{ ctrans("Checking the labels…") }}
			</div>

			<template v-else-if="check">
				<div class="text-sm text-gray-700">
					{{ ctrans(":count labels will be printed, one per page", { count: check.printable }) }}
				</div>
				<div v-if="check.skipped.length" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
					<div class="font-medium">{{ ctrans(":count left out:", { count: check.skipped.length }) }}</div>
					<ul class="mt-1 max-h-48 space-y-0.5 overflow-y-auto text-xs">
						<li v-for="item in check.skipped" :key="item.id">
							<span class="whitespace-nowrap font-medium">{{ item.code }}</span>
							<span class="text-amber-700"> · {{ item.reason }}</span>
						</li>
					</ul>
				</div>
			</template>

			<p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>

			<div class="flex justify-end gap-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" :label="ctrans('PDF')" :disabled="!canPrint" @click="openPdf">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-file-pdf" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</div>
	</Dialog>
</template>
