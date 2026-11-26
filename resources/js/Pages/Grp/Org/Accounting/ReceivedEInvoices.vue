<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { SupplierOptionFields, supplierOptionFilterFields } from "@/types/supplier"
import SupplierSelectOption from "@/Components/SupplyChain/SupplierSelectOption.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInboxIn, faFileCode, faFilePdf, faLink, faTimes, faCheck, faExclamationTriangle } from "@fal"
library.add(faInboxIn, faFileCode, faFilePdf, faLink, faTimes, faCheck, faExclamationTriangle)

interface Supplier extends SupplierOptionFields {
    id: number
    name: string
}

interface ReceivedEInvoice {
    id: number
    received_at: string
    sender_name: string | null
    sender_peppol_id: string | null
    document_type: "invoice" | "credit_note" | null
    document_number: string | null
    issue_date: string | null
    due_date: string | null
    currency_code: string | null
    total_amount: number | null
    order_reference: string | null
    parse_error: string | null
    acknowledged: boolean
    match_state: "unmatched" | "matched" | "ignored"
    suggestion: { supplier_id: number | null; supplier_reason: string | null; purchase_order_id: number | null; stock_delivery_id: number | null } | null
    decided_by: string | null
    decided_note: string | null
    supplier_id: number | null
    supplier_code: string | null
    supplier_name: string | null
    purchase_order_reference: string | null
    stock_delivery_reference: string | null
    xml_url: string | null
    pdf_url: string | null
}

const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
    canEdit: boolean
    organisationId: number
    matchStates: Record<string, string>
    suppliers: Supplier[]
}>()

const locale = useLocaleStore()

const suppliersById = computed(() => Object.fromEntries(props.suppliers.map((supplier) => [supplier.id, supplier])))
const supplierOptions = computed(() => props.suppliers.map((supplier) => ({ ...supplier, value: supplier.id, label: [supplier.code, supplier.company_name, supplier.contact_name, supplier.nickname].filter(Boolean).join(" · ") })))
const supplierSearch = ref("")

const reasonLabels: Record<string, string> = {
    peppol_id: ctrans("same Peppol id"),
    tax_number: ctrans("same tax number"),
    name: ctrans("same name"),
}

const editing = ref<ReceivedEInvoice | null>(null)
const visible = computed({
    get: () => editing.value !== null,
    set: (value: boolean) => {
        if (!value) editing.value = null
    },
})
const form = ref({ match_state: "matched", supplier_id: null as number | null, purchase_order_reference: "", stock_delivery_reference: "", note: "" })
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

const stateOptions = computed(() => Object.entries(props.matchStates).map(([value, label]) => ({ value, label })))

const openMatch = (item: ReceivedEInvoice) => {
    editing.value = item
    errors.value = {}
    form.value = {
        match_state: item.match_state === "unmatched" ? "matched" : item.match_state,
        supplier_id: item.supplier_id ?? item.suggestion?.supplier_id ?? null,
        purchase_order_reference: item.purchase_order_reference ?? (item.suggestion?.purchase_order_id ? item.order_reference ?? "" : ""),
        stock_delivery_reference: item.stock_delivery_reference ?? (item.suggestion?.stock_delivery_id ? item.order_reference ?? "" : ""),
        note: item.decided_note ?? "",
    }
}

const canSubmit = computed(() => form.value.match_state !== "matched" || form.value.supplier_id !== null)

const submit = () => {
    if (!editing.value) return
    isSaving.value = true
    router.post(route("grp.models.org.received_e_invoice.match", [props.organisationId, editing.value.id]), form.value, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
        onError: (formErrors) => (errors.value = formErrors as Record<string, string>),
        onFinish: () => (isSaving.value = false),
    })
}

const stateClass = (state: string) =>
    ({ matched: "bg-green-50 text-green-700", ignored: "bg-gray-100 text-gray-500", unmatched: "bg-amber-50 text-amber-700" })[state] ?? ""
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data">
        <template #cell(received_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.received_at, { formatTime: "dd MMM yyyy HH:mm" }) }}</span>
        </template>
        <template #cell(sender_name)="{ item }">
            <div>{{ item.sender_name ?? "-" }}</div>
            <div class="whitespace-nowrap text-xs text-gray-400">{{ item.sender_peppol_id }}</div>
        </template>
        <template #cell(document_number)="{ item }">
            <span class="whitespace-nowrap">{{ item.document_number ?? "-" }}</span>
            <span v-if="item.document_type === 'credit_note'" class="ml-1 rounded bg-red-50 px-1 text-xs text-red-600">{{ ctrans("Credit note") }}</span>
            <div v-if="item.parse_error" class="text-xs text-red-500" :title="item.parse_error">
                <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                {{ ctrans("Could not be read, open the file") }}
            </div>
        </template>
        <template #cell(issue_date)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.issue_date) }}</span>
        </template>
        <template #cell(due_date)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.due_date) }}</span>
        </template>
        <template #cell(total_amount)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ item.total_amount === null || !item.currency_code ? "-" : locale.currencyFormat(item.currency_code, item.total_amount) }}</span>
        </template>
        <template #cell(match)="{ item }">
            <span class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs" :class="stateClass(item.match_state)">{{ matchStates[item.match_state] }}</span>
            <template v-if="item.match_state === 'matched'">
                <div class="whitespace-nowrap">{{ item.supplier_name }} <span class="text-xs text-gray-400">{{ item.supplier_code }}</span></div>
                <div v-if="item.purchase_order_reference || item.stock_delivery_reference" class="whitespace-nowrap text-xs text-gray-500">
                    {{ [item.purchase_order_reference, item.stock_delivery_reference].filter(Boolean).join(" · ") }}
                </div>
            </template>
            <div v-else-if="item.match_state === 'unmatched' && item.suggestion?.supplier_id && suppliersById[item.suggestion.supplier_id]" class="text-xs text-gray-500">
                {{ ctrans("Suggested: :supplier (:reason)", { supplier: suppliersById[item.suggestion.supplier_id].name, reason: reasonLabels[item.suggestion.supplier_reason ?? ""] ?? "" }) }}
            </div>
            <div v-if="item.decided_note" class="text-xs text-gray-400">{{ item.decided_note }}</div>
        </template>
        <template #cell(files)="{ item }">
            <div class="flex gap-2 whitespace-nowrap">
                <a v-if="item.pdf_url" :href="item.pdf_url" target="_blank" class="text-gray-600 hover:text-[var(--app-accent)]" :title="ctrans('PDF')">
                    <FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true" /> PDF
                </a>
                <a v-if="item.xml_url" :href="item.xml_url" class="text-gray-600 hover:text-[var(--app-accent)]" :title="ctrans('XML as received')">
                    <FontAwesomeIcon :icon="faFileCode" fixed-width aria-hidden="true" /> XML
                </a>
            </div>
        </template>
        <template #cell(actions)="{ item }">
            <Button v-if="canEdit" type="tertiary" size="xs" :label="item.match_state === 'unmatched' ? ctrans('Match') : ctrans('Change')" @click="openMatch(item)">
                <template #icon>
                    <FontAwesomeIcon :icon="faLink" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </Table>

    <Dialog v-model:visible="visible" modal :header="ctrans('Match :number', { number: editing?.document_number ?? '' })" :style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div v-if="editing" class="space-y-3 text-sm">
            <div class="text-xs text-gray-500">{{ editing.sender_name }} · {{ editing.sender_peppol_id }}</div>
            <SegmentedToggle v-model="form.match_state" :options="stateOptions" :ariaLabel="ctrans('Match state')" />
            <template v-if="form.match_state === 'matched'">
                <label class="block">
                    <span class="text-xs text-gray-600">{{ ctrans("Supplier") }} <span class="text-red-500">*</span></span>
                    <Select
                        v-model="form.supplier_id"
                        :options="supplierOptions"
                        optionLabel="label"
                        optionValue="value"
                        filter
                        :filterFields="supplierOptionFilterFields"
                        :filterPlaceholder="ctrans('Code, name, contact or product code')"
                        class="w-full"
                        :placeholder="ctrans('Choose a supplier')"
                        @filter="(event) => supplierSearch = event.value"
                        @hide="supplierSearch = ''"
                    >
                        <template #option="{ option }">
                            <SupplierSelectOption :supplier="option" :search="supplierSearch" />
                        </template>
                    </Select>
                    <span v-if="errors.supplier_id" class="text-xs text-red-500">{{ errors.supplier_id }}</span>
                </label>
                <label class="block">
                    <span class="text-xs text-gray-600">{{ ctrans("Purchase order reference") }} ({{ ctrans("optional") }})</span>
                    <InputText v-model="form.purchase_order_reference" class="w-full" />
                    <span v-if="errors.purchase_order_reference" class="text-xs text-red-500">{{ errors.purchase_order_reference }}</span>
                </label>
                <label class="block">
                    <span class="text-xs text-gray-600">{{ ctrans("Delivery reference") }} ({{ ctrans("optional") }})</span>
                    <InputText v-model="form.stock_delivery_reference" class="w-full" />
                    <span v-if="errors.stock_delivery_reference" class="text-xs text-red-500">{{ errors.stock_delivery_reference }}</span>
                </label>
            </template>
            <label class="block">
                <span class="text-xs text-gray-600">{{ ctrans("Note") }} ({{ ctrans("optional") }})</span>
                <InputText v-model="form.note" class="w-full" />
                <span v-if="errors.note" class="text-xs text-red-500">{{ errors.note }}</span>
            </label>
            <p v-if="errors.match_state" class="text-xs text-red-500">{{ errors.match_state }}</p>
        </div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Save')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
