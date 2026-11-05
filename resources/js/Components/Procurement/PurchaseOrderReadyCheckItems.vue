<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-05 14:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBox, faClipboardCheck, faExclamationTriangle, faInfoCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

export type ReadyCheckItem = {
	id: number
	code: string | null
	name: string | null
	image_thumbnail: object | null
	quantity: number
	reference?: string
	has_unchecked?: boolean
	is_checkable?: boolean
}

export type ReadyCheckItems = {
	received_in_full: boolean
	items: ReadyCheckItem[]
	route: routeType | null
}

const props = defineProps<{
	check: "packing" | "qc"
	data: ReadyCheckItems
}>()

const emit = defineEmits<{
	done: []
}>()

const locale = useLocaleStore()

const isPacking = computed(() => props.check === "packing")

const actionableIds = computed(() => props.data.items.filter((item) => isPacking.value || item.is_checkable).map((item) => item.id))

const totalUnits = computed(() => props.data.items.reduce((total, item) => total + item.quantity, 0))

const hasUncheckedToPack = computed(() => isPacking.value && props.data.items.some((item) => item.has_unchecked))

const isSaving = ref(false)
const error = ref<string | null>(null)

function runAll() {
	if (!props.data.route || !actionableIds.value.length) {
		return
	}

	const url = route(props.data.route.name, props.data.route.parameters)
	const options = {
		preserveScroll: true,
		onStart: () => {
			isSaving.value = true
			error.value = null
		},
		onFinish: () => (isSaving.value = false),
		onSuccess: () => emit("done"),
		onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] || ctrans("Something went wrong")),
	}

	if (isPacking.value) {
		router.patch(url, { transactions: actionableIds.value }, options)
	} else {
		router.post(url, { items: actionableIds.value }, options)
	}
}
</script>

<template>
	<div class="space-y-3 text-sm">
		<template v-if="data.items.length">
			<p class="text-gray-600">
				{{ isPacking ? ctrans("These items are received at the warehouse and not packed yet:") : ctrans("These goods receipt lines are not checked yet:") }}
			</p>

			<ul class="max-h-[50vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200">
				<li v-for="item in data.items" :key="item.id" class="flex items-center gap-3 px-3 py-2">
					<Image :src="item.image_thumbnail" imageCover class="h-10 w-10 shrink-0 overflow-hidden rounded border border-gray-200" />
					<div class="min-w-0 flex-1">
						<div class="flex flex-wrap items-baseline gap-x-2">
							<span class="whitespace-nowrap font-medium text-gray-800">{{ item.code }}</span>
							<span v-if="item.reference" class="whitespace-nowrap text-xs text-gray-400">{{ item.reference }}</span>
						</div>
						<div v-if="item.name" class="text-xs text-gray-500">{{ item.name }}</div>
						<div v-if="isPacking && item.has_unchecked" class="text-xs text-amber-600">{{ ctrans("Not checked yet") }}</div>
						<div v-if="!isPacking && !item.is_checkable" class="text-xs text-amber-600">{{ ctrans("Packed before it was checked: it can no longer be checked") }}</div>
					</div>
					<span class="shrink-0 whitespace-nowrap tabular-nums text-gray-700">{{ ctrans(":units units", { units: locale.number(item.quantity) }) }}</span>
				</li>
			</ul>

			<p class="text-right text-xs tabular-nums text-gray-600">
				{{ ctrans("Total: :count items, :units units", { count: data.items.length, units: locale.number(totalUnits) }) }}
			</p>
		</template>
		<p v-else class="text-gray-500">
			{{ isPacking ? ctrans("Nothing received is waiting to be packed") : ctrans("Every goods receipt line is checked") }}
		</p>

		<p v-if="hasUncheckedToPack" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
			<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
			<span>{{ ctrans("Some items are not checked yet: once packed, their check can no longer be recorded. Check them first if QC must be done.") }}</span>
		</p>

		<p v-if="!data.received_in_full" class="flex gap-2 rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-700">
			<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-gray-500" fixed-width aria-hidden="true" />
			<span>{{ isPacking ? ctrans("Not every item of this order is received yet: Packing is done once all of it is received and packed.") : ctrans("Not every item of this order is received yet: QC is done once all of it is received and checked.") }}</span>
		</p>

		<p v-if="error" class="text-xs text-red-600">{{ error }}</p>

		<Button
			v-if="data.route && actionableIds.length"
			type="primary"
			full
			:label="isPacking ? ctrans('Pack all items (:count)', { count: actionableIds.length }) : ctrans('Check all items (:count)', { count: actionableIds.length })"
			:tooltip="isPacking ? ctrans('Pack every item above for the next container') : ctrans('Record every line above as checked with no rejects')"
			:loading="isSaving"
			@click="runAll"
		>
			<template #icon>
				<FontAwesomeIcon :icon="isPacking ? faBox : faClipboardCheck" fixed-width aria-hidden="true" />
			</template>
		</Button>
	</div>
</template>
