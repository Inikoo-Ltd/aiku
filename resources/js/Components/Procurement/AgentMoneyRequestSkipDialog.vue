<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-20 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faForward } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faTimes, faForward)

export interface SkipTarget {
	kind: "advance" | "balance" | "shipment" | "expense"
	purchase_order_id: number | null
	stock_delivery_id: number | null
	agent_payment_id: number | null
	title: string
	amount: string
}

const props = defineProps<{
	skipRoute: routeType
	organisationId: number
	clientCode: string
	target: SkipTarget | null
}>()

const visible = defineModel<boolean>("visible", { required: true })

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const reason = ref("")
const saving = ref(false)
const errorMessage = ref<string | null>(null)

watch(visible, (isVisible) => {
	if (isVisible) {
		reason.value = ""
		errorMessage.value = null
	}
})

const kindLabels = computed<Record<SkipTarget["kind"], { label: string; header: string }>>(() => ({
	advance: { label: ctrans("Deposit"), header: ctrans("Skip deposit") },
	balance: { label: ctrans("Balance"), header: ctrans("Skip balance") },
	shipment: { label: ctrans("Stock delivery costs"), header: ctrans("Skip stock delivery costs") },
	expense: { label: ctrans("Expense"), header: ctrans("Skip expense") },
}))

const kind = computed(() => (props.target ? kindLabels.value[props.target.kind] : null))

const canSave = computed(() => !!props.target && !!reason.value.trim())

const save = () => {
	if (!canSave.value || !props.target) return
	router.patch(
		route(props.skipRoute.name, props.skipRoute.parameters),
		{
			organisation_id: props.organisationId,
			kind: props.target.kind,
			purchase_order_id: props.target.purchase_order_id,
			stock_delivery_id: props.target.stock_delivery_id,
			agent_payment_id: props.target.agent_payment_id,
			skipped: true,
			reason: reason.value.trim(),
		},
		{
			preserveScroll: true,
			preserveState: true,
			onStart: () => (saving.value = true),
			onFinish: () => (saving.value = false),
			onSuccess: () => (visible.value = false),
			onError: (errors: Record<string, string>) => (errorMessage.value = Object.values(errors)[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal dismissableMask :header="kind?.header ?? ctrans('Skip this line')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-3">
			<div v-if="target" class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
				<div class="flex items-start gap-2">
					<span class="font-semibold text-gray-900">{{ target.title }}</span>
					<span class="ml-auto shrink-0 whitespace-nowrap rounded border border-gray-300 bg-white px-1.5 text-xs font-semibold text-gray-700">{{ kind?.label }}</span>
				</div>
				<div class="text-gray-600">{{ target.amount }}</div>
			</div>
			<ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
				<li>{{ ctrans("Use it when :client already gave you this money outside a money request, for example handed over directly or sent by a transfer not linked to any request.", { client: clientCode }) }}</li>
				<li>{{ ctrans("Once skipped, this line is no longer offered in new money requests, so :client will not pay this money to you.", { client: clientCode }) }}</li>
				<li>{{ ctrans("Your balance with :client does not change: skipping only takes the line off the list of money to ask for.", { client: clientCode }) }}</li>
				<li>{{ ctrans("Write the reason so your colleagues know later why this line was skipped.") }}</li>
				<li>{{ ctrans("Skipped by mistake? Open the Skipped filter and press 'Put back'.") }}</li>
			</ul>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Why it is skipped") }} <span class="text-red-500">*</span></span>
				<Textarea v-model="reason" rows="3" maxlength="500" autoResize :placeholder="ctrans('e.g. Deposit handed over directly by :client on 11 Oct', { client: clientCode })" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>
		</div>
		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Skip line')" :disabled="!canSave" :loading="saving" @click="save">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-forward" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
