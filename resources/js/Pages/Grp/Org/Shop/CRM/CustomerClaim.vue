<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import Image from "primevue/image"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxFragile, faCheck, faTimes, faHatWizard, faBan, faUser, faTasks, faBolt, faHandHoldingUsd, faTruck, faShippingFast, faWarehouse, faGift, faShoppingCart, faHourglassHalf } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faBoxFragile, faHatWizard, faUser, faTasks, faBan, faCheck, faBolt, faHandHoldingUsd, faTruck, faShippingFast, faWarehouse, faGift, faShoppingCart, faHourglassHalf)

type RouteRef = { name: string, parameters: any }
type Claim = {
    ulid: string
    reference: string
    state: "in_review" | "settled" | "rejected"
    state_label: string
    source: string
    resolution: string
    resolution_label: string
    amount: number
    currency_code: string | null
    comment: string | null
    created_at: string
    reviewed_at: string | null
    reviewer: string | null
    rejection_reason: string | null
    rejection_note: string | null
    result: Record<string, any> | null
    box_photos: string[]
    lines: { code: string, name: string | null, quantity: number, reason: string, photos: string[] }[]
    customer: { name: string, reference: string, route: RouteRef }
    order: { reference: string, route: RouteRef } | null
    kind: string
    kind_label: string
    conversation: { route: RouteRef } | null
    earlier: { reference: string, state_label: string, resolution: string, created_at: string, route: RouteRef }[]
    timeline: { at: string, icon: string, tone: "gray" | "green" | "amber" | "red", label: string, detail: string | null, route: RouteRef | null }[]
    claims_of_customer: number
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    claim: Claim
    can_review: boolean
    approve_route: RouteRef
    reject_route: RouteRef
    rejection_reasons: { value: string, label: string }[]
}>()

const locale = useLocaleStore()
const money = (amount: number) => props.claim.currency_code ? locale.currencyFormat(props.claim.currency_code, amount) : amount

const approving = ref(false)
const showApprove = ref(false)
const approveError = ref("")
const showReject = ref(false)
const rejecting = ref(false)
const rejectReason = ref<string | null>(null)
const rejectNote = ref("")
const rejectErrors = ref<Record<string, string>>({})

const outcomeText = computed(() => {
    if (props.claim.resolution === "refund_to_balance") {
        return ctrans(":amount will be credited to the customer's balance.", { amount: String(money(props.claim.amount)) })
    }
    if (props.claim.resolution === "replacement") {
        return ctrans("A replacement delivery note will be sent to the warehouse.")
    }
    return ctrans("The items will be added free to the customer's next order.")
})

const approve = () => {
    approveError.value = ""
    router.post(route(props.approve_route.name, props.approve_route.parameters), {}, {
        preserveScroll: true,
        onStart: () => { approving.value = true },
        onFinish: () => { approving.value = false },
        onSuccess: () => { showApprove.value = false },
        onError: (errors) => { approveError.value = Object.values(errors)[0] as string ?? ctrans("Sorry, that did not work.") },
    })
}

const reject = () => {
    rejectErrors.value = {}
    router.post(route(props.reject_route.name, props.reject_route.parameters), { reason: rejectReason.value, note: rejectNote.value || null }, {
        preserveScroll: true,
        onStart: () => { rejecting.value = true },
        onFinish: () => { rejecting.value = false },
        onSuccess: () => { showReject.value = false },
        onError: (errors) => { rejectErrors.value = errors as Record<string, string> },
    })
}

const toneClass: Record<string, string> = {
    gray: "bg-gray-100 text-gray-500",
    green: "bg-green-50 text-green-600",
    amber: "bg-amber-50 text-amber-600",
    red: "bg-red-50 text-red-500",
}

const stateClass: Record<string, string> = {
    in_review: "bg-amber-50 text-amber-700 border-amber-200",
    settled: "bg-green-50 text-green-700 border-green-200",
    rejected: "bg-red-50 text-red-700 border-red-200",
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div v-if="can_review" class="flex gap-2">
                <Button type="tertiary" :label="ctrans('Reject')" @click="showReject = true">
                    <template #icon><FontAwesomeIcon :icon="faBan" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" :label="ctrans('Approve')" @click="showApprove = true">
                    <template #icon><FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>
    </PageHeading>

    <div class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="rounded border px-2 py-0.5 text-xs font-semibold" :class="stateClass[claim.state]">{{ claim.state_label }}</span>
                <span v-if="claim.kind !== 'items'" class="rounded border px-2 py-0.5 text-xs text-gray-600">{{ claim.kind_label }}</span>
                <span class="text-gray-600">{{ ctrans("Asked for") }}: <span class="font-semibold text-gray-800">{{ claim.resolution_label }}</span></span>
                <span v-if="claim.resolution === 'refund_to_balance'" class="text-gray-600">{{ money(claim.amount) }}</span>
                <span v-if="claim.source === 'merlin'" class="text-gray-500"><FontAwesomeIcon :icon="faHatWizard" fixed-width aria-hidden="true" /> {{ ctrans("Made in Merlin") }}</span>
            </div>

            <div v-if="claim.state === 'rejected'" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ rejection_reasons.find((reason) => reason.value === claim.rejection_reason)?.label }}<span v-if="claim.rejection_note">: {{ claim.rejection_note }}</span>
                <span class="text-xs text-red-500"> · {{ claim.reviewer }} · {{ useFormatTime(claim.reviewed_at) }}</span>
            </div>
            <div v-if="claim.state === 'settled'" class="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                <span v-if="claim.result?.refund">{{ ctrans("Refund :reference", { reference: claim.result.refund }) }} · {{ money(claim.amount) }}</span>
                <span v-else-if="claim.result?.replacement">{{ ctrans("Replacement :reference", { reference: claim.result.replacement }) }}</span>
                <span v-else-if="claim.result?.in_next_order">{{ ctrans("In next order") }}: {{ claim.result.in_next_order.join(", ") }}</span>
                <span class="text-xs text-green-600"> · {{ claim.reviewer ?? ctrans("Approved automatically") }}</span>
            </div>

            <table class="w-full text-sm">
                <thead class="whitespace-nowrap border-b text-left text-xs text-gray-500">
                    <tr>
                        <th class="py-2 pr-3 font-semibold">{{ ctrans("Product") }}</th>
                        <th class="py-2 pr-3 text-right font-semibold">{{ ctrans("Quantity") }}</th>
                        <th class="py-2 pr-3 font-semibold">{{ ctrans("Reason") }}</th>
                        <th class="py-2 font-semibold">{{ ctrans("Photos") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in claim.lines" :key="line.code" class="border-b align-top">
                        <td class="py-2 pr-3"><span class="whitespace-nowrap font-semibold">{{ line.code }}</span> <span class="text-gray-600">{{ line.name }}</span></td>
                        <td class="py-2 pr-3 text-right tabular-nums">{{ locale.number(line.quantity) }}</td>
                        <td class="py-2 pr-3">{{ line.reason }}</td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-1.5">
                                <Image v-for="photo in line.photos" :key="photo" :src="photo" preview image-class="h-16 w-16 rounded border object-cover" />
                                <span v-if="!line.photos.length" class="text-xs text-gray-400">{{ ctrans("None") }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="claim.box_photos.length">
                <p class="mb-1 text-xs font-semibold text-gray-500">{{ ctrans("Outer box") }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <Image v-for="photo in claim.box_photos" :key="photo" :src="photo" preview image-class="h-24 w-24 rounded border object-cover" />
                </div>
            </div>

            <div v-if="claim.comment">
                <p class="mb-1 text-xs font-semibold text-gray-500">{{ ctrans("Customer comment") }}</p>
                <p class="whitespace-pre-line rounded border bg-gray-50 p-3 text-sm text-gray-800">{{ claim.comment }}</p>
            </div>
        </div>

        <div class="space-y-4 text-sm">
            <dl class="space-y-2 rounded border p-4">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ ctrans("Customer") }}</dt><dd><Link :href="route(claim.customer.route.name, claim.customer.route.parameters)" class="secondaryLink">{{ claim.customer.name }}</Link></dd></div>
                <div v-if="claim.order" class="flex justify-between gap-3"><dt class="text-gray-500">{{ ctrans("Order") }}</dt><dd><Link :href="route(claim.order.route.name, claim.order.route.parameters)" class="secondaryLink whitespace-nowrap">{{ claim.order.reference }}</Link></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ ctrans("Made") }}</dt><dd>{{ useFormatTime(claim.created_at) }}</dd></div>
                <div v-if="claim.conversation" class="flex justify-between gap-3"><dt class="text-gray-500">{{ ctrans("Conversation") }}</dt><dd><Link :href="route(claim.conversation.route.name, claim.conversation.route.parameters)" class="secondaryLink">{{ ctrans("Open in Merlin") }}</Link></dd></div>
            </dl>

            <div class="rounded border p-4">
                <p class="mb-3 text-xs font-semibold text-gray-500">{{ ctrans("Timeline") }}</p>
                <ol class="space-y-3">
                    <li v-for="(event, index) in claim.timeline" :key="index" class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full" :class="toneClass[event.tone]">
                            <FontAwesomeIcon :icon="event.icon" fixed-width aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm text-gray-800">
                                <Link v-if="event.route" :href="route(event.route.name, event.route.parameters)" class="secondaryLink">{{ event.label }}</Link>
                                <template v-else>{{ event.label }}</template>
                            </p>
                            <p class="text-xs text-gray-500">
                                <template v-if="event.detail">{{ event.detail }} · </template>{{ useFormatTime(event.at, { formatTime: "short-datetime" }) }}
                            </p>
                        </div>
                    </li>
                </ol>
            </div>

            <div class="rounded border p-4">
                <p class="mb-2 text-xs font-semibold text-gray-500">{{ ctrans("Claims of this customer") }}: {{ locale.number(claim.claims_of_customer) }}</p>
                <p v-if="!claim.earlier.length" class="text-xs text-gray-400">{{ ctrans("None") }}</p>
                <ul v-else class="space-y-1">
                    <li v-for="other in claim.earlier" :key="other.reference" class="flex justify-between gap-2 text-xs">
                        <Link :href="route(other.route.name, other.route.parameters)" class="secondaryLink whitespace-nowrap">{{ other.reference }}</Link>
                        <span class="text-gray-500">{{ other.resolution }} · {{ other.state_label }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <Dialog v-model:visible="showApprove" modal :header="ctrans('Approve :reference', { reference: claim.reference })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <p class="text-sm text-gray-700">{{ outcomeText }}</p>
        <p v-if="approveError" class="mt-2 text-xs text-red-500">{{ approveError }}</p>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="showApprove = false">
                    <template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="primary" full :label="ctrans('Approve')" :loading="approving" @click="approve">
                    <template #icon><FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>
    </Dialog>

    <Dialog v-model:visible="showReject" modal :header="ctrans('Reject :reference', { reference: claim.reference })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm">
            <label class="block">
                <span class="mb-1 block text-xs text-gray-600">{{ ctrans("Reason") }} <span class="text-red-500">*</span></span>
                <Select v-model="rejectReason" :options="rejection_reasons" option-label="label" option-value="value" class="w-full" />
                <span v-if="rejectErrors.reason" class="text-xs text-red-500">{{ rejectErrors.reason }}</span>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs text-gray-600">{{ ctrans("Note") }} <span v-if="rejectReason === 'other'" class="text-red-500">*</span></span>
                <Textarea v-model="rejectNote" rows="3" auto-resize class="w-full" />
                <span v-if="rejectErrors.note" class="text-xs text-red-500">{{ rejectErrors.note }}</span>
            </label>
        </div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="showReject = false">
                    <template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" full :label="ctrans('Reject')" :loading="rejecting" @click="reject">
                    <template #icon><FontAwesomeIcon :icon="faBan" class="text-amber-500" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
