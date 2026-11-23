<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import Checkbox from "primevue/checkbox"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import SelectButton from "primevue/selectbutton"
import Textarea from "primevue/textarea"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxFragile, faCheck } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { useLocaleStore } from "@/Stores/locale"

library.add(faBoxFragile)

type Line = { transaction_id: number, code: string, name: string, claimable: number, can_replace: boolean }
type Option = { value: string, label: string }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    order: { reference: string, route: { name: string, parameters: any } }
    customer: { name: string, claims: number }
    lines: Line[]
    too_old: boolean
    reasons: Option[]
    outcomes: Option[]
    currency_code: string | null
    store_route: { name: string, parameters: any }
    prefill?: { resolution: string | null, reason: string | null, percentage: number | null, items: Record<string, number> }
}>()

const locale = useLocaleStore()
const picked = ref<Record<number, { checked: boolean, quantity: number, reason: string | null }>>(
    Object.fromEntries(props.lines.map((line) => {
        const proposed = props.prefill?.items?.[line.code.toLowerCase()]
        return [line.transaction_id, proposed
            ? { checked: true, quantity: Math.min(Math.max(1, Math.round(proposed)), line.claimable), reason: props.prefill?.reason ?? null }
            : { checked: false, quantity: line.claimable, reason: null }]
    }))
)
const resolution = ref<string | null>(props.prefill?.resolution ?? null)
const percentage = ref<number>(props.prefill?.percentage ?? 100)
const comment = ref("")
const errors = ref<Record<string, string>>({})
const saving = ref(false)

const chosen = computed(() => props.lines.filter((line) => picked.value[line.transaction_id].checked))
const needsLines = computed(() => resolution.value !== "invoice_refund")
const outOfStock = computed(() => resolution.value === "replacement" && chosen.value.some((line) => !line.can_replace))

const submit = () => {
    errors.value = {}
    if (!resolution.value) {
        errors.value.resolution = ctrans("Choose the outcome.")
        return
    }
    if (needsLines.value && !chosen.value.length) {
        errors.value.lines = ctrans("Tick the items the claim is about.")
        return
    }
    if (chosen.value.some((line) => !picked.value[line.transaction_id].reason)) {
        errors.value.lines = ctrans("Choose a reason for each ticked item.")
        return
    }
    router.post(route(props.store_route.name, props.store_route.parameters), {
        resolution: resolution.value,
        percentage: resolution.value === "refund_to_balance" ? percentage.value : null,
        comment: comment.value || null,
        lines: chosen.value.map((line) => ({ transaction_id: line.transaction_id, quantity: picked.value[line.transaction_id].quantity, reason: picked.value[line.transaction_id].reason })),
    }, {
        onStart: () => { saving.value = true },
        onFinish: () => { saving.value = false },
        onError: (fieldErrors) => { errors.value = { form: Object.values(fieldErrors)[0] as string } },
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="max-w-4xl space-y-5 p-6 text-sm">
        <p class="text-gray-600">
            <Link :href="route(order.route.name, order.route.parameters)" class="secondaryLink whitespace-nowrap">{{ order.reference }}</Link>
            · {{ customer.name }} · {{ ctrans(":count earlier claims", { count: locale.number(customer.claims) }) }}
        </p>

        <p v-if="too_old" class="rounded border border-amber-200 bg-amber-50 p-3 text-amber-700">{{ ctrans("This order is past the claim window. Only an invoice refund is possible.") }}</p>

        <div>
            <p class="mb-1 text-xs font-semibold text-gray-600">{{ ctrans("Outcome") }} <span class="text-red-500">*</span></p>
            <SelectButton v-model="resolution" :options="outcomes" option-label="label" option-value="value" />
            <p v-if="resolution === 'invoice_refund'" class="mt-1 text-xs text-gray-500">{{ ctrans("Opens a refund on the order's invoice. Pick its lines or amount as usual; the claim is settled when the refund is finalised.") }}</p>
            <p v-if="errors.resolution" class="text-xs text-red-500">{{ errors.resolution }}</p>
        </div>

        <div v-if="resolution === 'refund_to_balance'" class="w-48">
            <p class="mb-1 text-xs font-semibold text-gray-600">{{ ctrans("Part of the value to credit") }}</p>
            <InputNumber v-model="percentage" suffix="%" :min="1" :max="100" class="w-full" input-class="w-full" />
        </div>

        <table v-if="lines.length" class="w-full">
            <thead class="whitespace-nowrap border-b text-left text-xs text-gray-500">
                <tr>
                    <th class="w-8 py-2"></th>
                    <th class="py-2 pr-3 font-semibold">{{ ctrans("Product") }}</th>
                    <th class="py-2 pr-3 text-right font-semibold">{{ ctrans("Left to claim") }}</th>
                    <th class="py-2 pr-3 font-semibold">{{ ctrans("Quantity") }}</th>
                    <th class="py-2 font-semibold">{{ ctrans("Reason") }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in lines" :key="line.transaction_id" class="border-b align-middle">
                    <td class="py-2"><Checkbox v-model="picked[line.transaction_id].checked" binary /></td>
                    <td class="py-2 pr-3"><span class="whitespace-nowrap font-semibold">{{ line.code }}</span> <span class="text-gray-600">{{ line.name }}</span></td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ locale.number(line.claimable) }}</td>
                    <td class="py-2 pr-3"><InputNumber v-model="picked[line.transaction_id].quantity" :disabled="!picked[line.transaction_id].checked" :min="1" :max="line.claimable" size="small" input-class="w-16 text-center" /></td>
                    <td class="py-2"><Select v-model="picked[line.transaction_id].reason" :disabled="!picked[line.transaction_id].checked" :options="reasons" option-label="label" option-value="value" :placeholder="ctrans('Reason')" size="small" class="w-52" /></td>
                </tr>
            </tbody>
        </table>
        <p v-else class="text-gray-500">{{ ctrans("Nothing is left to claim on this order.") }}</p>
        <p v-if="outOfStock" class="text-xs text-amber-600">{{ ctrans("Some ticked items are out of stock; the replacement will wait in the warehouse.") }}</p>
        <p v-if="errors.lines" class="text-xs text-red-500">{{ errors.lines }}</p>

        <div>
            <p class="mb-1 text-xs font-semibold text-gray-600">{{ ctrans("Note") }} <span class="font-normal text-gray-400">({{ ctrans("optional") }})</span></p>
            <Textarea v-model="comment" rows="2" auto-resize class="w-full" :placeholder="ctrans('What happened, for the claim history and the warehouse')" />
        </div>

        <p v-if="errors.form" class="text-xs text-red-500">{{ errors.form }}</p>

        <Button type="primary" :label="resolution === 'invoice_refund' ? ctrans('Create claim and open refund') : ctrans('Create and settle claim')" :loading="saving" @click="submit">
            <template #icon><FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" /></template>
        </Button>
    </div>
</template>
