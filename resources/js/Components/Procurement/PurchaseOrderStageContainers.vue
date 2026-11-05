<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-05 08:37
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PurchaseOrderLineAddToContainerDialog from "@/Components/Procurement/PurchaseOrderLineAddToContainerDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxes, faPlus, faTimes, faTrashAlt, faExclamationTriangle, faInfoCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type ItemToRemove = {
	id: number
	code: string | null
	name: string | null
	image_thumbnail: object | null
	unit_quantity: number
}

type Container = {
	id: number
	reference: string
	state: string
	state_label: string
	state_icon: { icon: string; class: string } | null
	number_lines: number
	unit_quantity: number
	show_route: routeType
	items_to_remove: ItemToRemove[]
	remove_route: routeType | null
}

const props = defineProps<{
	containers: Container[]
	itemsToAdd: InstanceType<typeof PurchaseOrderLineAddToContainerDialog>["$props"]["items"]
}>()

const locale = useLocaleStore()

const isDialogVisible = ref(false)

const unitsToAdd = computed(() => props.itemsToAdd.reduce((total, item) => total + item.agent_warehouse.quantity_ready_to_ship, 0))

const containerToRemove = ref<Container | null>(null)
const removeUnits = computed(() => (containerToRemove.value?.items_to_remove ?? []).reduce((total, item) => total + item.unit_quantity, 0))
const isRemoving = ref(false)
const removeError = ref<string | null>(null)

function askRemove(container: Container) {
	removeError.value = null
	containerToRemove.value = container
}

function removeFromContainer() {
	const removeRoute = containerToRemove.value?.remove_route
	if (!removeRoute) {
		return
	}

	router.delete(route(removeRoute.name, removeRoute.parameters), {
		preserveScroll: true,
		onStart: () => {
			isRemoving.value = true
			removeError.value = null
		},
		onFinish: () => (isRemoving.value = false),
		onSuccess: () => (containerToRemove.value = null),
		onError: (errors: Record<string, string>) => (removeError.value = Object.values(errors ?? {})[0] || ctrans("Something went wrong")),
	})
}
</script>

<template>
	<div class="mt-2 space-y-2 text-xs">
		<ul v-if="containers.length" class="space-y-1">
			<li v-for="container in containers" :key="container.id" class="flex flex-wrap items-baseline justify-between gap-x-2">
				<div class="flex min-w-0 items-center gap-1">
					<FontAwesomeIcon
						v-if="container.state_icon"
						:icon="container.state_icon.icon"
						:class="container.state_icon.class"
						v-tooltip="container.state_label"
						fixed-width
						aria-hidden="true"
					/>
					<Link :href="route(container.show_route.name, container.show_route.parameters)" class="whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline">
						{{ container.reference }}
					</Link>
				</div>
				<span class="ml-auto flex items-baseline gap-1">
					<span class="flex flex-wrap justify-end gap-x-1 text-right tabular-nums text-gray-500">
						<span class="whitespace-nowrap">{{ ctrans(":count items", { count: container.number_lines }) }},</span>
						<span class="whitespace-nowrap">{{ ctrans(":units units", { units: locale.number(container.unit_quantity) }) }}</span>
					</span>
					<button
						v-if="container.remove_route"
						type="button"
						class="shrink-0 rounded px-0.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
						v-tooltip="ctrans('Take this order out of :container', { container: container.reference })"
						:aria-label="ctrans('Take this order out of :container', { container: container.reference })"
						@click="askRemove(container)"
					>
						<FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
					</button>
				</span>
			</li>
		</ul>
		<p v-else class="text-gray-400">{{ ctrans("No item in a container yet") }}</p>

		<Button
			v-if="itemsToAdd.length"
			type="tertiary"
			size="xs"
			full
			:label="ctrans('Add all to a container (:count)', { count: itemsToAdd.length })"
			:tooltip="ctrans(':count items not in a container yet, :units units: check them before they go in', { count: itemsToAdd.length, units: locale.number(unitsToAdd) })"
			@click="isDialogVisible = true"
		>
			<template #icon>
				<FontAwesomeIcon :icon="containers.length ? faPlus : faBoxes" class="text-green-600" fixed-width aria-hidden="true" />
			</template>
		</Button>

		<PurchaseOrderLineAddToContainerDialog v-if="itemsToAdd.length" v-model:visible="isDialogVisible" :items="itemsToAdd" />

		<Dialog
			:visible="!!containerToRemove"
			modal
			:header="ctrans('Take this order out of :container', { container: containerToRemove?.reference ?? '' })"
			:style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="(visible: boolean) => !visible && (containerToRemove = null)"
		>
			<div v-if="containerToRemove" class="space-y-3 text-sm">
				<p class="text-gray-600">
					{{ ctrans("These items of this order come out of :container and wait for a container again:", { container: containerToRemove.reference }) }}
				</p>

				<ul class="max-h-[50vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200">
					<li v-for="item in containerToRemove.items_to_remove" :key="item.id" class="flex items-center gap-3 px-3 py-2">
						<Image :src="item.image_thumbnail" imageCover class="h-10 w-10 shrink-0 overflow-hidden rounded border border-gray-200" />
						<div class="min-w-0 flex-1">
							<div class="whitespace-nowrap font-medium text-gray-800">{{ item.code }}</div>
							<div v-if="item.name" class="text-xs text-gray-500">{{ item.name }}</div>
						</div>
						<span class="shrink-0 whitespace-nowrap tabular-nums text-gray-700">{{ ctrans(":units units", { units: locale.number(item.unit_quantity) }) }}</span>
					</li>
				</ul>

				<p class="text-right text-xs tabular-nums text-gray-600">
					{{ ctrans("Total: :count items, :units units", { count: containerToRemove.items_to_remove.length, units: locale.number(removeUnits) }) }}
				</p>

				<p class="flex gap-2 rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-700">
					<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-gray-500" fixed-width aria-hidden="true" />
					<span>{{ ctrans(":container is not deleted: it stays open, with the goods of the other orders in it. Only the items above leave it, and they can be put in a container again later.", { container: containerToRemove.reference }) }}</span>
				</p>

				<p v-if="removeError" class="text-xs text-red-600">{{ removeError }}</p>
			</div>

			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="containerToRemove = null">
						<template #icon>
							<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="red" key=3 full :icon="faExclamationTriangle" :label="ctrans('Take out of the container')" :loading="isRemoving" @click="removeFromContainer">
						<!-- <template #icon>
							<FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
						</template> -->
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
