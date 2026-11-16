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
import { faBox, faCheck, faClipboardCheck, faExclamationTriangle, faInfoCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
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
	is_checked?: boolean
	checked_at?: string | null
	quantity_rejected?: number
	received_quantity?: number
	is_packed?: boolean
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

const actionableIds = computed(() => props.data.items.filter((item) => (isPacking.value ? !item.is_packed : item.is_checkable)).map((item) => item.id))

const totalUnits = computed(() => props.data.items.reduce((total, item) => total + (isPacking.value ? item.received_quantity ?? item.quantity : item.quantity), 0))

const packedCount = computed(() => props.data.items.filter((item) => item.is_packed).length)

const checkedCount = computed(() => props.data.items.filter((item) => item.is_checked).length)

const hasUncheckedQc = computed(() => !isPacking.value && checkedCount.value < props.data.items.length)

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
				<template v-if="isPacking">{{ ctrans(":packed of :total items received are packed:", { packed: packedCount, total: data.items.length }) }}</template>
				<template v-else>{{ ctrans(":checked of :total goods receipt lines are checked:", { checked: checkedCount, total: data.items.length }) }}</template>
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
						<template v-if="isPacking">
							<div v-if="item.is_packed" class="flex items-center gap-1 text-xs text-green-700">
								<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
								{{ ctrans("Packed") }}
							</div>
							<div v-else class="text-xs text-amber-600">
								{{ ctrans(":units units not packed yet", { units: locale.number(item.quantity) }) }}<template v-if="item.has_unchecked"> · {{ ctrans("Not checked yet") }}</template>
							</div>
						</template>
						<template v-if="!isPacking">
							<div v-if="item.is_checked" class="flex items-center gap-1 text-xs text-green-700">
								<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
								{{ item.checked_at ? ctrans("Checked :date", { date: useFormatTime(item.checked_at) }) : ctrans("Checked") }}
								<span v-if="item.quantity_rejected" class="text-red-600">· {{ ctrans(":units rejected", { units: locale.number(item.quantity_rejected) }) }}</span>
							</div>
							<div v-else-if="!item.is_checkable" class="text-xs text-amber-600">{{ ctrans("Not checked: packed before it was checked, it can no longer be checked") }}</div>
							<div v-else class="text-xs text-amber-600">{{ ctrans("Not checked yet") }}</div>
						</template>
					</div>
					<span class="shrink-0 whitespace-nowrap tabular-nums text-gray-700">{{ ctrans(":units units", { units: locale.number(isPacking ? item.received_quantity ?? item.quantity : item.quantity) }) }}</span>
				</li>
			</ul>

			<p class="text-right text-xs tabular-nums text-gray-600">
				{{ isPacking ? ctrans("Total received: :count items, :units units", { count: data.items.length, units: locale.number(totalUnits) }) : ctrans("Total: :count items, :units units", { count: data.items.length, units: locale.number(totalUnits) }) }}
			</p>
		</template>
		<p v-else class="text-gray-500">
			{{ isPacking ? ctrans("Nothing received yet to pack") : ctrans("Nothing received yet to check") }}
		</p>

		<p v-if="!isPacking && data.items.length && !hasUncheckedQc" class="text-xs text-green-700">{{ ctrans("Every goods receipt line is checked") }}</p>
		<p v-if="isPacking && data.items.length && packedCount === data.items.length" class="text-xs text-green-700">{{ ctrans("Every item received is packed") }}</p>

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
