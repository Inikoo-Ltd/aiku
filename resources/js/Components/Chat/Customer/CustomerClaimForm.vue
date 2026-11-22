<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import axios from "axios"
import Checkbox from "primevue/checkbox"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import SelectButton from "primevue/selectbutton"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faBoxOpen } from "@fortawesome/free-solid-svg-icons"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import MerlinImagePicker from "@/Components/Chat/Customer/MerlinImagePicker.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type Line = { transaction_id: number, code: string, name: string, claimable: number, can_replace: boolean }
type Reason = { value: string, label: string, needs_photos: boolean }
type Form = { order: { id: number, reference: string }, too_old: boolean, max_age_days: number, lines: Line[], reasons: Reason[], outcomes: string[], currency: string, max_photos: number, is_dropshipping: boolean }
export type ClaimSummary = { ulid: string, reference: string, state: string, state_label: string, resolution: string, amount: number | null, currency_code: string | null, replacement: string | null, lines: { code: string, name: string, quantity: number }[] }
type Picked = { checked: boolean, quantity: number, reason: string | null, photos: File[] }

const props = defineProps<{ orderId: number, source: "merlin" | "account" | "chat" | "email", conversation?: string | null, made?: ClaimSummary | null }>()
const emit = defineEmits<{ (e: "made", claim: ClaimSummary): void }>()

const locale = useLocaleStore()
const form = ref<Form | null>(null)
const loadError = ref("")
const picked = ref<Record<number, Picked>>({})
const resolution = ref<string | null>(null)
const comment = ref("")
const boxPhoto = ref<File[]>([])
const sending = ref(false)
const errors = ref<Record<string, string>>({})
const claim = ref<ClaimSummary | null>(props.made ?? null)

const url = (name: string, params: Record<string, string | number>, fallback: string): string => route().has(name) ? route(name, params) : fallback

const outcomeLabels: Record<string, string> = {
    refund_to_balance: ctrans("Credit to my balance"),
    replacement: ctrans("Send a replacement now"),
    next_order: ctrans("Add to my next order"),
}

const chosen = computed(() => form.value ? form.value.lines.filter((line) => picked.value[line.transaction_id]?.checked) : [])
const reasonOf = (value: string | null) => form.value?.reasons.find((reason) => reason.value === value)
const needsBox = computed(() => chosen.value.some((line) => reasonOf(picked.value[line.transaction_id].reason)?.needs_photos))
const canReplaceAll = computed(() => chosen.value.every((line) => line.can_replace))
const outcomes = computed(() => (form.value?.outcomes ?? [])
    .filter((outcome) => outcome !== "replacement" || canReplaceAll.value)
    .map((outcome) => ({ value: outcome, label: outcomeLabels[outcome] ?? outcome })))

const load = async () => {
    try {
        const { data } = await axios.get(url("iris.json.customer_claims.form", { orderId: props.orderId }, `/json/claims/orders/${props.orderId}/form`))
        form.value = data
        picked.value = Object.fromEntries(data.lines.map((line: Line) => [line.transaction_id, { checked: false, quantity: line.claimable, reason: null, photos: [] }]))
    } catch {
        loadError.value = ctrans("Sorry, the claim form could not be opened. Please try again.")
    }
}

onMounted(() => {
    if (!claim.value) {
        load()
    }
})

const submit = async () => {
    errors.value = {}
    if (!chosen.value.length) {
        errors.value.lines = ctrans("Please tick the items that went wrong.")
        return
    }
    if (!resolution.value) {
        errors.value.resolution = ctrans("Please choose what you would like us to do.")
        return
    }

    const body = new FormData()
    body.append("order_id", String(props.orderId))
    body.append("source", props.source)
    if (props.conversation) {
        body.append("conversation", props.conversation)
    }
    body.append("resolution", resolution.value)
    if (comment.value.trim()) {
        body.append("comment", comment.value.trim())
    }
    chosen.value.forEach((line, index) => {
        const choice = picked.value[line.transaction_id]
        body.append(`lines[${index}][transaction_id]`, String(line.transaction_id))
        body.append(`lines[${index}][quantity]`, String(choice.quantity))
        body.append(`lines[${index}][reason]`, choice.reason ?? "")
        choice.photos.forEach((photo) => body.append(`lines[${index}][photos][]`, photo))
    })
    if (boxPhoto.value[0]) {
        body.append("box_photo", boxPhoto.value[0])
    }

    sending.value = true
    try {
        const { data } = await axios.post(url("iris.json.customer_claims.store", {}, "/json/claims"), body)
        claim.value = data
        emit("made", data)
    } catch (error: any) {
        const fieldErrors = error?.response?.data?.errors ?? {}
        errors.value = Object.fromEntries(Object.entries(fieldErrors).map(([field, messages]: [string, any]) => [field.replace(/^lines\.(\d+).*$/, (_, index) => `line.${chosen.value[Number(index)]?.transaction_id}`), messages[0]]))
        if (!Object.keys(errors.value).length) {
            errors.value.form = ctrans("Sorry, that did not work. Please try again.")
        }
    } finally {
        sending.value = false
    }
}
</script>

<template>
    <div class="mt-2 rounded border bg-white p-3 text-sm text-gray-800">
        <div v-if="claim" class="space-y-1">
            <p class="font-semibold">
                <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                {{ ctrans("Claim :reference", { reference: claim.reference }) }} · {{ claim.state_label }}
            </p>
            <p v-if="claim.state === 'settled' && claim.amount !== null && claim.currency_code" class="text-xs text-gray-600">
                {{ ctrans(":amount has been added to your balance.", { amount: locale.currencyFormat(claim.currency_code, claim.amount) }) }}
            </p>
            <p v-else-if="claim.state === 'settled' && claim.resolution === 'replacement'" class="text-xs text-gray-600">
                {{ ctrans("A replacement is on its way to our warehouse team.") }}
            </p>
            <p v-else-if="claim.state === 'settled' && claim.resolution === 'next_order'" class="text-xs text-gray-600">
                {{ ctrans("The items will be added free to your next order.") }}
            </p>
            <p v-else-if="claim.state === 'in_review'" class="text-xs text-gray-600">
                {{ ctrans("Our team will check it and reply to you by email.") }}
            </p>
            <ul class="text-xs text-gray-500">
                <li v-for="line in claim.lines" :key="line.code"><span class="whitespace-nowrap">{{ line.code }}</span> × {{ line.quantity }}</li>
            </ul>
        </div>

        <p v-else-if="loadError" class="text-xs text-red-500">{{ loadError }}</p>
        <div v-else-if="!form" class="flex justify-center py-2"><LoadingIcon /></div>

        <div v-else class="space-y-3">
            <p class="font-semibold">
                <FontAwesomeIcon :icon="faBoxOpen" class="text-gray-500" fixed-width aria-hidden="true" />
                {{ ctrans("Report a problem with :reference", { reference: form.order.reference }) }}
            </p>

            <p v-if="form.too_old" class="text-xs text-gray-600">{{ ctrans("Claims are taken for :days days after an order is dispatched.", { days: form.max_age_days }) }}</p>

            <template v-else>
                <div>
                    <p class="mb-1 text-xs text-gray-600">{{ ctrans("Which items went wrong?") }} <span class="text-red-500">*</span></p>
                    <div v-for="line in form.lines" :key="line.transaction_id" class="border-t py-2 first:border-t-0">
                        <label class="flex cursor-pointer items-start gap-2">
                            <Checkbox v-model="picked[line.transaction_id].checked" binary :input-id="`claim-${orderId}-${line.transaction_id}`" />
                            <span class="text-xs"><span class="whitespace-nowrap font-semibold">{{ line.code }}</span> {{ line.name }}</span>
                        </label>
                        <div v-if="picked[line.transaction_id].checked" class="ml-7 mt-2 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <InputNumber v-model="picked[line.transaction_id].quantity" :min="1" :max="line.claimable" show-buttons button-layout="horizontal" size="small" input-class="w-14 text-center text-xs" :aria-label="ctrans('Quantity')" />
                                <Select v-model="picked[line.transaction_id].reason" :options="form.reasons" option-label="label" option-value="value" :placeholder="ctrans('What happened?')" size="small" class="min-w-44 text-xs" />
                            </div>
                            <MerlinImagePicker v-if="reasonOf(picked[line.transaction_id].reason)?.needs_photos" v-model="picked[line.transaction_id].photos" :max="form.max_photos" :label="ctrans('Add photos')" :disabled="sending" />
                            <p v-if="errors[`line.${line.transaction_id}`]" class="text-xs text-red-500">{{ errors[`line.${line.transaction_id}`] }}</p>
                        </div>
                    </div>
                    <p v-if="errors.lines" class="text-xs text-red-500">{{ errors.lines }}</p>
                </div>

                <div v-if="needsBox">
                    <p class="mb-1 text-xs text-gray-600">{{ ctrans("A photo of the outer box") }} <span class="text-red-500">*</span></p>
                    <MerlinImagePicker v-model="boxPhoto" :max="1" :label="ctrans('Add photo')" :disabled="sending" />
                    <p v-if="errors.box_photo" class="text-xs text-red-500">{{ errors.box_photo }}</p>
                </div>

                <div v-if="chosen.length">
                    <p class="mb-1 text-xs text-gray-600">{{ ctrans("What would you like us to do?") }} <span class="text-red-500">*</span></p>
                    <SelectButton v-model="resolution" :options="outcomes" option-label="label" option-value="value" size="small" class="flex-wrap" />
                    <p v-if="!canReplaceAll && form.outcomes.includes('replacement')" class="mt-1 text-xs text-gray-500">{{ ctrans("Some items are out of stock, so we cannot send them now.") }}</p>
                    <p v-if="errors.resolution" class="text-xs text-red-500">{{ errors.resolution }}</p>
                </div>

                <Textarea v-if="chosen.length" v-model="comment" rows="2" auto-resize class="w-full text-xs" :placeholder="ctrans('Anything else we should know? (optional)')" />

                <p v-if="errors.form || errors.order" class="text-xs text-red-500">{{ errors.form || errors.order }}</p>

                <Button type="primary" full :label="ctrans('Send claim')" :disabled="sending" @click="submit">
                    <template #icon>
                        <LoadingIcon v-if="sending" />
                        <FontAwesomeIcon v-else :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </template>
        </div>
    </div>
</template>
