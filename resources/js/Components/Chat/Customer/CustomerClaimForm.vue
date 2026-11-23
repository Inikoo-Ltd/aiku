<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from "vue"
import axios from "axios"
import Checkbox from "primevue/checkbox"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import SelectButton from "primevue/selectbutton"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faBoxOpen, faBan, faHourglassHalf } from "@fortawesome/free-solid-svg-icons"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import MerlinImagePicker from "@/Components/Chat/Customer/MerlinImagePicker.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type Line = { transaction_id: number, code: string, name: string, claimable: number, unit_value: number, can_replace: boolean }
type Reason = { value: string, label: string, needs_photos: boolean }
type Form = { order: { id: number, reference: string }, too_old: boolean, max_age_days: number, lines: Line[], reasons: Reason[], outcomes: string[], currency: string, max_photos: number, is_dropshipping: boolean }
export type ClaimSummary = {
    ulid: string
    reference: string
    state: string
    state_label: string
    resolution: string
    amount: number | null
    value?: number
    currency_code: string | null
    replacement: string | null
    rejection?: { reason: string, note: string | null } | null
    lines: { code: string, name: string, quantity: number, reason?: string }[]
}
type Picked = { checked: boolean, quantity: number, reason: string | null, photos: File[] }

const LOST = "lost_in_transit"

const props = defineProps<{ orderId: number, source: "merlin" | "account" | "chat" | "email", conversation?: string | null, made?: ClaimSummary | null }>()
const emit = defineEmits<{ (e: "made", claim: ClaimSummary): void }>()

const locale = useLocaleStore()
const root = ref<HTMLElement | null>(null)
const form = ref<Form | null>(null)
const loadError = ref("")
const picked = ref<Record<number, Picked>>({})
const wholeParcel = ref(false)
const resolution = ref<string | null>(null)
const comment = ref("")
const boxPhoto = ref<File[]>([])
const sending = ref(false)
const errors = ref<Record<string, string>>({})
const claim = ref<ClaimSummary | null>(props.made ?? null)

const url = (name: string, params: Record<string, string | number>, fallback: string): string => route().has(name) ? route(name, params) : fallback
const money = (amount: number, currency: string | null | undefined) => currency ? locale.currencyFormat(currency, amount) : String(amount)

const outcomeLabels: Record<string, string> = {
    refund_to_balance: ctrans("Credit to my balance"),
    replacement: ctrans("Send a replacement now"),
    next_order: ctrans("Add to my next order"),
}

const chosen = computed(() => form.value ? form.value.lines.filter((line) => wholeParcel.value || picked.value[line.transaction_id]?.checked) : [])
const quantityOf = (line: Line) => wholeParcel.value ? line.claimable : picked.value[line.transaction_id].quantity
const reasonOf = (value: string | null) => form.value?.reasons.find((reason) => reason.value === value)
const needsPhotos = (line: Line) => !wholeParcel.value && !!reasonOf(picked.value[line.transaction_id]?.reason)?.needs_photos
const boxLine = computed(() => chosen.value.find((line) => needsPhotos(line)) ?? null)
const canReplaceAll = computed(() => chosen.value.every((line) => line.can_replace))
const outcomes = computed(() => (form.value?.outcomes ?? [])
    .filter((outcome) => outcome !== "replacement" || canReplaceAll.value)
    .map((outcome) => ({ value: outcome, label: outcomeLabels[outcome] ?? outcome })))
const claimValue = computed(() => Math.round(chosen.value.reduce((sum, line) => sum + line.unit_value * (quantityOf(line) || 0), 0) * 100) / 100)

const stateIcon = computed(() => claim.value?.state === "rejected" ? faBan : claim.value?.state === "in_review" ? faHourglassHalf : faCheck)
const stateClass = computed(() => claim.value?.state === "rejected" ? "text-red-500" : claim.value?.state === "in_review" ? "text-amber-500" : "text-green-600")

const load = async () => {
    try {
        const { data } = await axios.get(url("iris.json.customer_claims.form", { orderId: props.orderId }, `/json/claims/orders/${props.orderId}/form`))
        form.value = data
        picked.value = Object.fromEntries(data.lines.map((line: Line) => [line.transaction_id, { checked: false, quantity: 1, reason: null, photos: [] }]))
    } catch {
        loadError.value = ctrans("Sorry, the claim form could not be opened. Please try again.")
    }
}

onMounted(() => {
    if (!claim.value) {
        load()
    }
})

const showFirstError = async () => {
    await nextTick()
    root.value?.querySelector("[data-claim-error]")?.scrollIntoView({ behavior: "smooth", block: "center" })
}

const check = (): Record<string, string> => {
    const found: Record<string, string> = {}
    if (!chosen.value.length) {
        found.lines = ctrans("Please tick the items that went wrong.")
    }
    chosen.value.forEach((line) => {
        if (wholeParcel.value) {
            return
        }
        const choice = picked.value[line.transaction_id]
        if (!choice.reason) {
            found[`line.${line.transaction_id}`] = ctrans("Please choose what happened.")
        } else if (needsPhotos(line) && !choice.photos.length) {
            found[`line.${line.transaction_id}`] = ctrans("Please add a photo of this item.")
        }
    })
    if (boxLine.value && !boxPhoto.value.length) {
        found.box_photo = ctrans("Please add a photo of the outer box.")
    }
    if (chosen.value.length && !resolution.value) {
        found.resolution = ctrans("Please choose what you would like us to do.")
    }
    return found
}

const fieldOf = (field: string): string => {
    const match = field.match(/^lines\.(\d+)/)
    return match ? `line.${chosen.value[Number(match[1])]?.transaction_id}` : field
}

const submit = async () => {
    errors.value = check()
    if (Object.keys(errors.value).length) {
        showFirstError()
        return
    }

    const body = new FormData()
    body.append("order_id", String(props.orderId))
    body.append("source", props.source)
    if (props.conversation) {
        body.append("conversation", props.conversation)
    }
    body.append("resolution", resolution.value as string)
    if (comment.value.trim()) {
        body.append("comment", comment.value.trim())
    }
    chosen.value.forEach((line, index) => {
        const choice = picked.value[line.transaction_id]
        body.append(`lines[${index}][transaction_id]`, String(line.transaction_id))
        body.append(`lines[${index}][quantity]`, String(quantityOf(line)))
        body.append(`lines[${index}][reason]`, wholeParcel.value ? LOST : choice.reason ?? "")
        if (!wholeParcel.value) {
            choice.photos.forEach((photo) => body.append(`lines[${index}][photos][]`, photo))
        }
    })
    if (boxLine.value && boxPhoto.value[0]) {
        body.append("box_photo", boxPhoto.value[0])
    }

    sending.value = true
    try {
        const { data } = await axios.post(url("iris.json.customer_claims.store", {}, "/json/claims"), body)
        claim.value = data
        emit("made", data)
    } catch (error: any) {
        const fieldErrors = error?.response?.data?.errors ?? {}
        const found: Record<string, string> = {}
        Object.entries(fieldErrors).forEach(([field, messages]: [string, any]) => {
            const key = fieldOf(field)
            found[key] = found[key] ?? messages[0]
        })
        if (!Object.keys(found).length) {
            found.form = ctrans("Sorry, that did not work. Please try again.")
        }
        errors.value = found
        showFirstError()
    } finally {
        sending.value = false
    }
}
</script>

<template>
    <div ref="root" class="customer-claim-form mt-2 rounded border bg-white p-3 text-sm text-gray-800">
        <div v-if="claim" class="space-y-1.5">
            <p class="font-semibold">
                <FontAwesomeIcon :icon="stateIcon" :class="stateClass" fixed-width aria-hidden="true" />
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
            <div v-else-if="claim.state === 'rejected'" class="space-y-1 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">
                <p>{{ ctrans("We could not accept this claim") }}<span v-if="claim.rejection?.reason">: {{ claim.rejection.reason }}</span></p>
                <p v-if="claim.rejection?.note" class="whitespace-pre-line text-gray-700">{{ claim.rejection.note }}</p>
                <p class="text-gray-600">{{ ctrans("If you think this is wrong, reply here and ask to talk to a person.") }}</p>
            </div>
            <table class="w-full text-xs text-gray-600">
                <tbody>
                    <tr v-for="line in claim.lines" :key="line.code" class="align-top">
                        <td class="py-0.5 pr-2">{{ line.name || line.code }} <span class="whitespace-nowrap text-gray-400">{{ line.code }}</span></td>
                        <td class="whitespace-nowrap py-0.5 pr-2 text-right tabular-nums">× {{ line.quantity }}</td>
                        <td class="py-0.5 text-gray-500">{{ line.reason }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-if="claim.value && claim.currency_code" class="text-xs text-gray-500">{{ ctrans("Value of the items: :amount", { amount: money(claim.value, claim.currency_code) }) }}</p>
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
                <label class="flex cursor-pointer items-center gap-2 rounded border bg-gray-50 p-2 text-xs">
                    <Checkbox v-model="wholeParcel" binary :input-id="`claim-${orderId}-whole`" />
                    <span class="font-semibold">{{ ctrans("The whole parcel never arrived") }}</span>
                </label>

                <div v-if="wholeParcel" class="text-xs text-gray-600">
                    {{ ctrans("We will check with the courier. Everything on this order is included:") }}
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="line in form.lines" :key="line.transaction_id">{{ line.name || line.code }} × {{ line.claimable }}</li>
                    </ul>
                </div>

                <div v-else>
                    <p class="mb-1 text-xs text-gray-600">{{ ctrans("Which items went wrong?") }} <span class="text-red-500">*</span></p>
                    <div v-for="line in form.lines" :key="line.transaction_id" class="border-t py-2 first:border-t-0">
                        <label class="flex cursor-pointer items-start gap-2">
                            <Checkbox v-model="picked[line.transaction_id].checked" binary :input-id="`claim-${orderId}-${line.transaction_id}`" />
                            <span class="text-xs">{{ line.name || line.code }} <span class="whitespace-nowrap text-gray-400">{{ line.code }}</span></span>
                        </label>
                        <div v-if="picked[line.transaction_id].checked" class="ml-7 mt-2 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex items-center gap-1 text-xs text-gray-600">
                                    <InputNumber v-model="picked[line.transaction_id].quantity" :min="1" :max="line.claimable" show-buttons button-layout="horizontal" size="small" input-class="w-12 text-center text-xs" :aria-label="ctrans('Quantity')" />
                                    {{ ctrans("of :count", { count: line.claimable }) }}
                                </span>
                                <Select v-model="picked[line.transaction_id].reason" :options="form.reasons" option-label="label" option-value="value" :placeholder="ctrans('What happened?')" size="small" class="min-w-44 text-xs" />
                            </div>
                            <MerlinImagePicker v-if="needsPhotos(line)" v-model="picked[line.transaction_id].photos" :max="form.max_photos" :label="ctrans('Add photos of the item')" :disabled="sending" />
                            <div v-if="boxLine?.transaction_id === line.transaction_id">
                                <MerlinImagePicker v-model="boxPhoto" :max="1" :label="ctrans('Add a photo of the outer box')" :disabled="sending" />
                                <p v-if="errors.box_photo" data-claim-error class="text-xs text-red-500">{{ errors.box_photo }}</p>
                            </div>
                            <p v-if="errors[`line.${line.transaction_id}`]" data-claim-error class="text-xs text-red-500">{{ errors[`line.${line.transaction_id}`] }}</p>
                        </div>
                    </div>
                </div>
                <p v-if="errors.lines" data-claim-error class="text-xs text-red-500">{{ errors.lines }}</p>

                <div v-if="chosen.length">
                    <p class="mb-1 text-xs text-gray-600">{{ ctrans("What would you like us to do?") }} <span class="text-red-500">*</span></p>
                    <SelectButton v-model="resolution" :options="outcomes" option-label="label" option-value="value" size="small" class="flex-wrap" />
                    <p v-if="!canReplaceAll && form.outcomes.includes('replacement')" class="mt-1 text-xs text-gray-500">{{ ctrans("Some items are out of stock, so we cannot send them now.") }}</p>
                    <p v-if="errors.resolution" data-claim-error class="text-xs text-red-500">{{ errors.resolution }}</p>
                </div>

                <p v-if="chosen.length && claimValue > 0" class="text-xs text-gray-600">
                    <template v-if="resolution === 'refund_to_balance'">{{ ctrans("We will credit :amount to your balance.", { amount: money(claimValue, form.currency) }) }}</template>
                    <template v-else>{{ ctrans("Value of these items: :amount", { amount: money(claimValue, form.currency) }) }}</template>
                </p>

                <Textarea v-if="chosen.length" v-model="comment" rows="2" auto-resize class="w-full text-xs" :placeholder="ctrans('Anything else we should know? (optional)')" />

                <p v-if="errors.form || errors.order" data-claim-error class="text-xs text-red-500">{{ errors.form || errors.order }}</p>

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

<style scoped>
.customer-claim-form {
    --claim-accent: var(--theme-color-4, var(--app-accent, #e87928));
    --claim-accent-text: var(--theme-color-5, var(--app-accent-text, #ffffff));
}

.customer-claim-form :deep(.p-checkbox-checked .p-checkbox-box) {
    border-color: var(--claim-accent);
    background: var(--claim-accent);
}

.customer-claim-form :deep(.p-checkbox-checked .p-checkbox-icon) {
    color: var(--claim-accent-text);
}

.customer-claim-form :deep(.p-togglebutton-checked .p-togglebutton-content) {
    background: var(--claim-accent);
    color: var(--claim-accent-text);
}

.customer-claim-form :deep(.p-togglebutton-checked) {
    border-color: var(--claim-accent);
}
</style>
