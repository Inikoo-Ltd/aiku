<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Link } from "@inertiajs/vue3"
import { faCheck, faHandHoldingUsd, faTimes } from "@fal"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

library.add(faCheck, faHandHoldingUsd, faTimes)

export interface MoneyRequestRow {
	id: number
	reference: string
	client_code: string
	requested_at: string
	state: string
	state_label: string
	currency_code: string
	total: number
	paid: number
	outstanding: number
	notes: string | null
	requested_by: string | null
	approved_at: string | null
	approved_by: string | null
	lines: {
		kind: string
		kind_label: string
		reference: string | null
		route: routeType | null
		description: string
		supplier_code: string | null
		supplier_name: string | null
		amount: number
	}[]
	cancel_route: routeType | null
	approve_route: routeType | null
	unapprove_route: routeType | null
	pay_route: routeType | null
}

defineProps<{
	data: object
	tab?: string
	approvingId?: number | null
}>()

const emit = defineEmits<{
	(e: "approve", row: MoneyRequestRow): void
	(e: "unapprove", row: MoneyRequestRow): void
	(e: "pay", row: MoneyRequestRow): void
	(e: "cancel", row: MoneyRequestRow): void
}>()

const locale = useLocaleStore()
const money = (code: string, amount: number) => locale.currencyFormat(code, amount)

const stateClass: Record<string, string> = {
	requested: "bg-amber-50 text-amber-700",
	settled: "bg-green-50 text-green-700",
	cancelled: "bg-gray-100 text-gray-500",
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(requested_at)="{ item }">
			<span class="whitespace-nowrap">{{ useFormatTime(item.requested_at) }}</span>
		</template>

		<template #cell(reference)="{ item }">
			<span class="whitespace-nowrap font-medium text-gray-700">{{ item.reference }}</span>
		</template>

		<template #cell(client_code)="{ item }">
			<span class="font-semibold uppercase text-gray-700">{{ item.client_code }}</span>
		</template>

		<template #cell(lines)="{ item }">
			<ul class="space-y-0.5 text-sm">
				<li v-for="(line, index) in item.lines" :key="index" class="flex items-baseline justify-between gap-4">
					<span class="min-w-0">
						<span class="whitespace-nowrap text-gray-700">
							{{ line.description }}
							<Link v-if="line.route" :href="route(line.route.name, line.route.parameters)" class="primaryLink">{{ line.reference }}</Link>
							<template v-else-if="line.reference">{{ line.reference }}</template>
						</span>
						<span v-if="line.supplier_code" class="ml-1.5 text-xs text-gray-400" :title="line.supplier_name ?? undefined">{{ line.supplier_name ?? line.supplier_code }}</span>
					</span>
					<span class="whitespace-nowrap tabular-nums text-gray-500">{{ money(item.currency_code, line.amount) }}</span>
				</li>
			</ul>
			<div v-if="item.notes" class="text-xs text-gray-400">{{ item.notes }}</div>
		</template>

		<template #cell(state)="{ item }">
			<span class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs" :class="stateClass[item.state]">{{ item.state_label }}</span>
			<div v-if="item.state === 'requested'" class="mt-1 whitespace-nowrap text-xs" :class="item.approved_at ? 'text-green-700' : 'text-gray-400'">
				{{ item.approved_at ? ctrans("Approved by :user", { user: item.approved_by ?? "-" }) : ctrans("Waiting for approval") }}
			</div>
			<div v-if="item.requested_by" class="whitespace-nowrap text-xs text-gray-400">{{ ctrans("Asked by :user", { user: item.requested_by }) }}</div>
		</template>

		<template #cell(total)="{ item }">
			<span class="whitespace-nowrap" :class="item.state === 'cancelled' ? 'text-gray-400 line-through' : ''">{{ money(item.currency_code, item.total) }}</span>
		</template>

		<template #cell(paid)="{ item }">
			<span class="whitespace-nowrap">{{ item.paid ? money(item.currency_code, item.paid) : "-" }}</span>
		</template>

		<template #cell(outstanding)="{ item }">
			<span class="whitespace-nowrap font-medium" :class="item.outstanding > 0 ? 'text-gray-800' : 'text-gray-400'">
				{{ item.outstanding > 0 ? money(item.currency_code, item.outstanding) : "-" }}
			</span>
		</template>

		<template #cell(actions)="{ item }">
			<div class="flex justify-end gap-2">
				<Button v-if="item.approve_route" type="tertiary" size="xs" :label="ctrans('Approve')" :loading="approvingId === item.id" @click="emit('approve', item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="item.unapprove_route" type="tertiary" size="xs" :label="ctrans('Cancel approval')" :loading="approvingId === item.id" @click="emit('unapprove', item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="item.pay_route" type="tertiary" size="xs" :label="ctrans('Pay')" @click="emit('pay', item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-hand-holding-usd" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="item.cancel_route" type="negative" size="xs" :label="ctrans('Cancel')" @click="emit('cancel', item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Table>
</template>
