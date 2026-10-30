<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import DatePicker from "primevue/datepicker"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

interface Delivery {
	id: number
	reference: string
	organisation: string
	days: number
	needs_reason: boolean
	route: routeType
}

interface Reminder {
	reason_after_days: number
	remind_on_max: string
	deliveries: Delivery[]
	acknowledge_route: routeType
}

const reminder = computed(() => usePage().props.costing_reminder as Reminder | null)
const reasons = ref<Record<number, string>>({})
const remindOn = ref<Record<number, Date | null>>({})
const tomorrow = computed(() => { const date = new Date(); date.setHours(0, 0, 0, 0); date.setDate(date.getDate() + 1); return date })
const remindOnMax = computed(() => reminder.value ? new Date(`${reminder.value.remind_on_max}T00:00:00`) : undefined)
const isoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const errors = ref<Record<string, string>>({})
const sending = ref(false)

watch(reminder, () => { errors.value = {} })

const lateDeliveries = computed(() => reminder.value?.deliveries.filter(delivery => delivery.needs_reason) ?? [])

const acknowledge = () => {
	if (!reminder.value) return
	router.post(route(reminder.value.acknowledge_route.name, reminder.value.acknowledge_route.parameters), {
		reasons: reasons.value,
		remind_on: Object.fromEntries(Object.entries(remindOn.value).filter(([, date]) => date).map(([id, date]) => [id, isoDate(date as Date)])),
	}, {
		preserveScroll: true,
		preserveState: true,
		onStart: () => { sending.value = true },
		onFinish: () => { sending.value = false },
		onSuccess: () => { reasons.value = {}; remindOn.value = {} },
		onError: (formErrors: Record<string, string>) => { errors.value = formErrors },
	})
}
</script>

<template>
	<Dialog
		:visible="!!reminder"
		modal
		:closable="false"
		:style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }"
	>
		<template #header>
			<div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
				<FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-red-500" fixed-width aria-hidden="true" />
				{{ ctrans("Deliveries booked in without their costs") }}
			</div>
		</template>

		<div v-if="reminder" class="space-y-3 text-sm text-gray-700">
			<p>{{ ctrans("The warehouse has booked in these deliveries, and their costs are still not entered. Costs should be in before the goods arrive: until they are, this stock is valued at the purchase order price and its margins are wrong.") }}</p>
			<p v-if="lateDeliveries.length" class="font-medium text-red-700">
				{{ ctrans("Waiting more than :days working days: say why for each one. It is written in the delivery's history under your name. You can choose when to be reminded again, at most a month from today.", { days: String(reminder.reason_after_days) }) }}
			</p>

			<table class="w-full">
				<thead class="whitespace-nowrap border-b border-gray-200 text-xs text-gray-500">
					<tr>
						<th class="py-1.5 pr-3 text-left font-medium">{{ ctrans("Delivery") }}</th>
						<th class="py-1.5 pr-3 text-right font-medium">{{ ctrans("Working days") }}</th>
						<th class="py-1.5 text-left font-medium">{{ ctrans("Reason") }}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr v-for="delivery in reminder.deliveries" :key="delivery.id" class="align-top">
						<td class="py-2 pr-3 whitespace-nowrap">
							<a :href="route(delivery.route.name, delivery.route.parameters)" target="_blank" rel="noopener" class="font-medium text-gray-700 hover:underline">
								{{ delivery.reference }}
							</a>
							<span class="ml-1 text-xs text-gray-400">{{ delivery.organisation }}</span>
						</td>
						<td class="py-2 pr-3 text-right tabular-nums" :class="delivery.needs_reason ? 'font-semibold text-red-600' : 'text-gray-600'">{{ delivery.days }}</td>
						<td class="py-2">
							<template v-if="delivery.needs_reason">
								<InputText v-model="reasons[delivery.id]" size="small" fluid maxlength="500" :invalid="!!errors[`reasons.${delivery.id}`]" :placeholder="ctrans('Why is it not costed yet?')" />
								<p v-if="errors[`reasons.${delivery.id}`]" class="mt-1 text-xs text-red-600">{{ errors[`reasons.${delivery.id}`] }}</p>
								<DatePicker
									v-model="remindOn[delivery.id]"
									class="mt-1.5"
									size="small"
									dateFormat="yy-mm-dd"
									:minDate="tomorrow"
									:maxDate="remindOnMax"
									:invalid="!!errors[`remind_on.${delivery.id}`]"
									:placeholder="ctrans('Remind me again on (optional)')"
									showButtonBar
									fluid
								/>
								<p v-if="errors[`remind_on.${delivery.id}`]" class="mt-1 text-xs text-red-600">{{ errors[`remind_on.${delivery.id}`] }}</p>
							</template>
							<span v-else class="text-xs text-gray-400">—</span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<template #footer>
			<Button type="tertiary" :label="ctrans('I will enter the costs today')" :loading="sending" @click="acknowledge">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</Dialog>
</template>
