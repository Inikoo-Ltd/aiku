<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-05 08:37
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PurchaseOrderLineAddToContainerDialog from "@/Components/Procurement/PurchaseOrderLineAddToContainerDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxes, faPlus } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Container = {
	id: number
	reference: string
	state: string
	state_label: string
	state_icon: { icon: string; class: string } | null
	number_lines: number
	unit_quantity: number
	show_route: routeType
}

const props = defineProps<{
	containers: Container[]
	itemsToAdd: InstanceType<typeof PurchaseOrderLineAddToContainerDialog>["$props"]["items"]
}>()

const locale = useLocaleStore()

const isDialogVisible = ref(false)

const unitsToAdd = computed(() => props.itemsToAdd.reduce((total, item) => total + item.agent_warehouse.quantity_ready_to_ship, 0))
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
				<span class="ml-auto flex flex-wrap justify-end gap-x-1 text-right tabular-nums text-gray-500">
					<span class="whitespace-nowrap">{{ ctrans(":count items", { count: container.number_lines }) }},</span>
					<span class="whitespace-nowrap">{{ ctrans(":units units", { units: locale.number(container.unit_quantity) }) }}</span>
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
	</div>
</template>
