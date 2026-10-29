<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, h, inject, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faCheckCircle, faCircle, faCopy, faExclamationTriangle, faAddressCard, faBoxOpen, faRulerCombined, faShippingFast, faCalculator, faSave, faInfoCircle, faTimes } from "@fal"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Textarea from "primevue/textarea"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Button from "@/Components/Elements/Buttons/Button.vue"
import CopyButton from "@/Components/Utils/CopyButton.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ProductionCalendarStrip from "@/Components/Procurement/ProductionCalendarStrip.vue"
import { routeType } from "@/types/route"

library.add(faCheck, faCheckCircle, faCircle, faCopy, faExclamationTriangle, faAddressCard, faBoxOpen, faRulerCombined, faShippingFast, faCalculator, faSave, faInfoCircle, faTimes)

type Source = "product" | "family" | "supplier" | null
type Dimensions = { l: number; w: number; h: number } | null

const props = defineProps<{
    panel: {
        internal_code: string
        supplier_code: string
        breadcrumb: { supplier: { code: string; name: string }; family: { code: string; name: string } | null }
        currency: string
        key: {
            cbm: number | null
            units_per_carton: number | null
            moq: { cartons: number | null; source: Source; units: number | null; cbm: number | null; value?: number | null }
            lead_time: { days: number | null; source: Source; measured: number | null; samples: number; confirmed_at: string | null; confirmed_by: string | null }
            cost?: { unit: number; carton: number | null }
        }
        measurements: {
            unit: { dimensions: Dimensions; net_weight: number | null; gross_weight: number | null }
            carton: { dimensions: Dimensions; net_weight: number | null; gross_weight: number | null; cbm: number | null }
        }
        warnings: { key: string; message: string }[]
        cbm_siblings: { code: string; supplier: string; cbm: number; factor: string }[]
        containers: Record<"20ft" | "40ft" | "40ft_hc", number> | null
        checks: { key: string; label: string; done: boolean; anchor: string }[]
        contacts: { values: Record<"contact_name" | "phone" | "email" | "wechat" | "whatsapp", string | null>; update_route: routeType | null }
        packaging: { terms: Record<string, { value: string | number | null; source: Source }>; options: Record<string, string> }
        availability: {
            status: string
            reason: string | null
            available_from: string | null
            new_cost: number | null
            note: string | null
            status_options: Record<string, string>
            reason_options: Record<string, string>
        }
        production_calendar: {
            months: Record<string, string | null>
            advice: { status: string; message: string | null; arrives_on: string | null }
            family: string | null
        }
        update_route: routeType | null
    }
}>()

const locale = inject("locale", aikuLocaleStructure)
const canEdit = computed(() => !!props.panel.update_route)
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const money = (value: number | null) => (value === null ? "-" : locale.currencyFormat(props.panel.currency, value))
const number = (value: number | null, digits = 0) => (value === null ? "-" : locale.number(Number(value.toFixed(digits))))

const sourceLabel = (source: Source) =>
    ({
        family: ctrans("From family at supplier"),
        supplier: ctrans("From supplier record"),
    })[source ?? ""] ?? null

const doneChecks = computed(() => props.panel.checks.filter((check) => check.done).length)
const progress = computed(() => Math.round((doneChecks.value / props.panel.checks.length) * 100))

const highlightedSection = ref<string | null>(null)
let highlightTimeout: ReturnType<typeof setTimeout> | undefined

const jumpTo = (anchor: string, focusFieldId?: string) => {
    document.getElementById(`agent-${anchor}`)?.scrollIntoView({ behavior: "smooth", block: "start" })
    document.getElementById(focusFieldId ?? "")?.focus({ preventScroll: true })

    highlightedSection.value = anchor
    clearTimeout(highlightTimeout)
    highlightTimeout = setTimeout(() => (highlightedSection.value = null), 2500)
}

const highlightClass = (anchor: string) => (highlightedSection.value === anchor ? "ring-2 ring-[--app-accent] ring-offset-2" : "")

const savingSection = ref<string | null>(null)
const saveFeedback = ref<{ section: string; text: string; failed: boolean } | null>(null)

const SaveFeedback = (props: { section: string }) =>
    saveFeedback.value?.section === props.section
        ? h("span", { class: ["text-xs", saveFeedback.value.failed ? "text-red-500" : "text-green-600"] }, saveFeedback.value.text)
        : null

const save = async (section: string, routeData: routeType | null, payload: Record<string, unknown>, successText: string) => {
    if (!routeData || savingSection.value) {
        return
    }

    savingSection.value = section
    saveFeedback.value = null
    try {
        await axios.patch(route(routeData.name, routeData.parameters), payload)
        router.reload({ only: ["showcase"] })
        saveFeedback.value = { section, text: successText, failed: false }
    } catch (error: any) {
        const errors = error?.response?.data?.errors
        saveFeedback.value = { section, text: errors ? Object.values(errors).flat().join(" ") : ctrans("Could not save"), failed: true }
    } finally {
        savingSection.value = null
    }
}

const keyForm = ref({ minimum_carton_order: null as number | null, estimated_lead_time_days: null as number | null })
const measurementsForm = ref({
    l: null as number | null,
    w: null as number | null,
    h: null as number | null,
    cbm: null as number | null,
    net_kg: null as number | null,
    gross_kg: null as number | null,
    units_per_carton: null as number | null,
})
const contactsForm = ref({ ...props.panel.contacts.values })
const packagingForm = ref<Record<string, any>>({})
const availabilityForm = ref({
    status: props.panel.availability.status,
    reason: props.panel.availability.reason,
    available_from: props.panel.availability.available_from,
    new_cost: props.panel.availability.new_cost,
    note: props.panel.availability.note,
})

const formsFromPanel = () => {
    const carton = props.panel.measurements.carton
    const availability = props.panel.availability

    return {
        key: {
            minimum_carton_order: props.panel.key.moq.source === "product" ? props.panel.key.moq.cartons : null,
            estimated_lead_time_days: props.panel.key.lead_time.source === "product" ? props.panel.key.lead_time.days : null,
        },
        measurements: {
            l: carton.dimensions?.l ?? null,
            w: carton.dimensions?.w ?? null,
            h: carton.dimensions?.h ?? null,
            cbm: carton.cbm,
            net_kg: carton.net_weight === null ? null : carton.net_weight / 1000,
            gross_kg: carton.gross_weight === null ? null : carton.gross_weight / 1000,
            units_per_carton: props.panel.key.units_per_carton,
        },
        contacts: { ...props.panel.contacts.values },
        packaging: Object.fromEntries(
            Object.entries(props.panel.packaging.terms).map(([term, data]) => [term, data.source === "product" ? data.value : null])
        ) as Record<string, any>,
        availability: {
            status: availability.status,
            reason: availability.reason,
            available_from: availability.available_from,
            new_cost: availability.new_cost,
            note: availability.note,
        },
    }
}

const savedForms = computed(formsFromPanel)

const resetForms = () => {
    const forms = formsFromPanel()
    keyForm.value = forms.key
    measurementsForm.value = forms.measurements
    contactsForm.value = forms.contacts
    packagingForm.value = forms.packaging
    availabilityForm.value = forms.availability
}
watch(() => props.panel, resetForms, { immediate: true })

const blankAsNull = (value: unknown) => (value === "" || value === undefined ? null : value)

const hasChanges = (form: Record<string, unknown>, saved: Record<string, unknown>) =>
    [...new Set([...Object.keys(form), ...Object.keys(saved)])].some((field) => blankAsNull(form[field]) !== blankAsNull(saved[field]))

const keyFiguresChanged = computed(() => hasChanges(keyForm.value, savedForms.value.key))
const measurementsChanged = computed(() => hasChanges(measurementsForm.value, savedForms.value.measurements))
const contactsChanged = computed(() => hasChanges(contactsForm.value, savedForms.value.contacts))
const packagingChanged = computed(() => hasChanges(packagingForm.value, savedForms.value.packaging))
const availabilityChanged = computed(() => hasChanges(availabilityForm.value, savedForms.value.availability))

const cbmFromDimensions = computed(() => {
    const { l, w, h } = measurementsForm.value
    return l && w && h ? Number(((l * w * h) / 1_000_000).toFixed(6)) : null
})

const grams = (kilograms: number | null) => (kilograms === null ? null : Math.round(kilograms * 1000))

const saveKeyFigures = () =>
    save("key", props.panel.update_route, keyForm.value, ctrans("MOQ and lead time saved"))

const confirmLeadTime = () =>
    save(
        "lead_time",
        props.panel.update_route,
        { estimated_lead_time_days: keyForm.value.estimated_lead_time_days ?? props.panel.key.lead_time.days, confirm_lead_time: true },
        ctrans("Lead time confirmed with the supplier")
    )

const leadTimeStillConfirmed = computed(
    () => !!props.panel.key.lead_time.confirmed_at && blankAsNull(keyForm.value.estimated_lead_time_days) === blankAsNull(savedForms.value.key.estimated_lead_time_days)
)

const cancelLeadTimeConfirmation = () =>
    save("lead_time", props.panel.update_route, { confirm_lead_time: false }, ctrans("Lead time confirmation cancelled"))

const saveMeasurements = () => {
    const { l, w, h } = measurementsForm.value
    save(
        "measurements",
        props.panel.update_route,
        {
            carton_dimensions: l && w && h ? { l, w, h } : null,
            cbm: measurementsForm.value.cbm ?? cbmFromDimensions.value,
            carton_net_weight: grams(measurementsForm.value.net_kg),
            carton_weight: grams(measurementsForm.value.gross_kg),
            units_per_carton: measurementsForm.value.units_per_carton,
        },
        ctrans("Weights and measurements saved")
    )
}

const saveContacts = () =>
    save("contacts", props.panel.contacts.update_route, contactsForm.value, ctrans("Saved on the supplier, every product of it shows them"))

const savePackaging = () =>
    save("packaging", props.panel.update_route, { packaging_terms: packagingForm.value }, ctrans("Packaging terms saved"))

const saveAvailability = () =>
    save(
        "availability",
        props.panel.update_route,
        {
            availability_status: availabilityForm.value.status,
            unavailable_reason: availabilityForm.value.status === "available" ? null : availabilityForm.value.reason,
            available_from: availabilityForm.value.status === "available" ? null : availabilityForm.value.available_from,
            availability_new_cost: availabilityForm.value.status === "available" ? null : availabilityForm.value.new_cost,
            availability_note: availabilityForm.value.note,
        },
        ctrans("Availability saved, purchasing has been told")
    )

const toOptions = (labels: Record<string, string>) => Object.entries(labels).map(([value, label]) => ({ value, label }))

const statusTone: Record<string, "emerald" | "amber" | "red"> = { available: "emerald", limited: "amber", unavailable: "red" }
const statusOptions = computed(() =>
    toOptions(props.panel.availability.status_options).map((option) => ({ ...option, tone: statusTone[option.value] }))
)
const payerOptions = computed(() => toOptions(props.panel.packaging.options))

const paidTerms = computed(() => [
    { key: "retail_box", label: ctrans("Retail box") },
    { key: "extra_wrapping", label: ctrans("Extra wrapping") },
    { key: "special_packaging", label: ctrans("Special packaging the supplier must supply") },
    { key: "label_application", label: ctrans("Label application") },
])

const contactFields = computed(() => [
    { key: "contact_name", label: ctrans("Contact person") },
    { key: "phone", label: ctrans("Phone") },
    { key: "email", label: ctrans("Email") },
    { key: "wechat", label: ctrans("WeChat") },
    { key: "whatsapp", label: ctrans("WhatsApp") },
])

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const availableFrom = computed<Date | null>({
    get: () => (availabilityForm.value.available_from ? fromIsoDate(availabilityForm.value.available_from) : null),
    set: (value) => (availabilityForm.value.available_from = value ? toLocalIsoDate(value) : null),
})

const containerLabels = { "20ft": "20ft", "40ft": "40ft", "40ft_hc": "40ft HC" }
const sectionTitle = "mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
const card = "rounded-xl border border-gray-200 bg-white p-4 shadow-sm scroll-mt-4"
</script>

<template>
    <div class="grid items-start gap-6 md:col-span-3 md:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 md:col-span-2">
            <section :class="card">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500">
                            {{ panel.breadcrumb.supplier.code }}
                            <template v-if="panel.breadcrumb.family"> › {{ panel.breadcrumb.family.code }}</template>
                            › {{ panel.internal_code }}
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-2xl font-semibold tracking-tight text-gray-900">{{ panel.internal_code }}</span>
                            <CopyButton :text="panel.internal_code" />
                        </div>
                        <div class="mt-0.5 text-sm text-gray-500">{{ ctrans("Supplier code") }}: {{ panel.supplier_code }}</div>
                        <div class="group relative mt-0.5 inline-block text-sm text-gray-500">
                            {{ ctrans("Supplier") }}: <span class="cursor-help font-medium text-gray-800 underline decoration-dotted underline-offset-2">{{ panel.breadcrumb.supplier.name }}</span>
                            <span class="text-gray-400">({{ panel.breadcrumb.supplier.code }})</span>
                            <section id="agent-contacts" :class="[card, 'invisible absolute left-0 top-full z-20 w-96 opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100']">
                                <h3 :class="sectionTitle">
                                    <FontAwesomeIcon icon="fal fa-address-card" fixed-width aria-hidden="true" />
                                    {{ ctrans("Supplier contacts") }}
                                    <span class="ml-auto font-normal normal-case tracking-normal text-gray-400">{{ ctrans("From supplier record") }}</span>
                                </h3>
                                <div class="space-y-2">
                                    <label v-for="field in contactFields" :key="field.key" class="flex items-center gap-2 text-sm">
                                        <span class="w-28 shrink-0 text-gray-500">{{ field.label }}</span>
                                        <InputText v-if="panel.contacts.update_route" v-model="contactsForm[field.key]" class="!w-full !py-1 !text-sm" :class="fieldFocusClass"
                                            :placeholder="ctrans('Missing')" />
                                        <span v-else class="truncate" :class="panel.contacts.values[field.key] ? 'text-gray-800' : 'italic text-gray-400'">
                                            {{ panel.contacts.values[field.key] ?? ctrans("Missing") }}
                                        </span>
                                    </label>
                                </div>
                                <div v-if="panel.contacts.update_route" class="mt-3 flex items-center justify-between gap-2">
                                    <span class="text-xs text-gray-400">{{ ctrans("Saved on the supplier") }}</span>
                                    <Button type="tertiary" :label="ctrans('Save')" :loading="savingSection === 'contacts'" :disabled="!contactsChanged" @click="saveContacts">
                                        <template #icon><FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" /></template>
                                    </Button>
                                    <SaveFeedback section="contacts" />
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="w-full max-w-md">
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-gray-700">{{ ctrans("Things to fix") }}</span>
                            <span class="tabular-nums text-gray-500">{{ doneChecks }} / {{ panel.checks.length }}</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-green-500 transition-all" :style="{ width: `${progress}%` }" />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button v-for="check in panel.checks" :key="check.key" type="button"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ring-1 ring-inset"
                                :class="check.done ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50'"
                                @click="jumpTo(check.anchor)">
                                <FontAwesomeIcon :icon="check.done ? 'fal fa-check' : 'fal fa-circle'" fixed-width aria-hidden="true" />
                                {{ check.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="agent-key-figures" :class="card">
                <h3 :class="sectionTitle">
                    <FontAwesomeIcon icon="fal fa-calculator" fixed-width aria-hidden="true" />
                    {{ ctrans("Key figures") }}
                </h3>
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-gray-500">{{ ctrans("CBM per carton") }}</dt>
                        <dd class="text-xl font-semibold tabular-nums text-gray-900">{{ panel.key.cbm ? `${number(panel.key.cbm, 4)} m³` : "-" }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ ctrans("Units per carton") }}</dt>
                        <dd class="text-xl font-semibold tabular-nums text-gray-900">{{ number(panel.key.units_per_carton) }}</dd>
                    </div>
                    <div v-if="panel.key.cost">
                        <dt class="text-xs text-gray-500">{{ ctrans("Agreed cost") }}</dt>
                        <dd class="text-xl font-semibold tabular-nums text-gray-900">{{ money(panel.key.cost.unit) }}</dd>
                        <dd class="text-xs tabular-nums text-gray-500">{{ money(panel.key.cost.carton) }} {{ ctrans("per carton") }}</dd>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <dt class="text-xs text-gray-500">{{ ctrans("MOQ") }}</dt>
                        <dd class="text-xl font-semibold tabular-nums text-gray-900">
                            {{ panel.key.moq.cartons ? ctrans(":count cartons", { count: panel.key.moq.cartons }) : "-" }}
                        </dd>
                        <dd v-if="panel.key.moq.cartons" class="text-xs tabular-nums text-gray-500">
                            {{ ctransChoice(":count unit|:count units", panel.key.moq.units ?? 0, { count: number(panel.key.moq.units) }) }} · {{ number(panel.key.moq.cbm, 3) }} m³<template v-if="panel.key.cost"> · {{ money(panel.key.moq.value ?? null) }}</template>
                        </dd>
                        <dd v-if="sourceLabel(panel.key.moq.source)" class="text-xs italic text-gray-400">{{ sourceLabel(panel.key.moq.source) }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-500">{{ ctrans("Lead time to warehouse") }}</dt>
                        <dd class="text-xl font-semibold tabular-nums text-gray-900">
                            {{ panel.key.lead_time.days ? ctrans(":days days", { days: panel.key.lead_time.days }) : "-" }}
                            <span v-if="panel.key.lead_time.measured" class="ml-2 text-sm font-normal text-gray-500">
                                {{ ctrans("measured :days days on :count deliveries", { days: panel.key.lead_time.measured, count: panel.key.lead_time.samples }) }}
                            </span>
                        </dd>
                        <dd v-if="sourceLabel(panel.key.lead_time.source)" class="text-xs italic text-gray-400">{{ sourceLabel(panel.key.lead_time.source) }}</dd>
                        <dd class="text-xs" :class="panel.key.lead_time.confirmed_at ? 'text-green-700' : 'text-amber-700'">
                            <template v-if="panel.key.lead_time.confirmed_at">
                                <FontAwesomeIcon icon="fal fa-check-circle" fixed-width aria-hidden="true" />
                                {{ ctrans("Confirmed by :user on :date", { user: panel.key.lead_time.confirmed_by ?? "-", date: new Date(panel.key.lead_time.confirmed_at).toLocaleDateString() }) }}
                            </template>
                            <template v-else>{{ ctrans("Not confirmed with the supplier") }}</template>
                        </dd>
                        <dd v-if="canEdit" class="mt-2 flex items-center gap-1.5">
                            <template v-if="leadTimeStillConfirmed">
                                <Button type="tertiary" size="xs" :label="ctrans('Cancel confirmation')" :loading="savingSection === 'lead_time'" @click="cancelLeadTimeConfirmation">
                                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                                </Button>
                                <SaveFeedback section="lead_time" />
                                <FontAwesomeIcon icon="fal fa-info-circle" class="cursor-help text-gray-400 hover:text-gray-600" fixed-width
                                    v-tooltip="ctrans('Marks this lead time as not yet agreed with the supplier')"
                                    :aria-label="ctrans('About cancelling the lead time confirmation')" />
                            </template>
                            <template v-else>
                                <Button type="tertiary" size="xs" :label="ctrans('Confirm with supplier')" :loading="savingSection === 'lead_time'"
                                    :disabled="!(keyForm.estimated_lead_time_days ?? panel.key.lead_time.days)" @click="confirmLeadTime">
                                    <template #icon><FontAwesomeIcon icon="fal fa-check-circle" class="text-green-600" fixed-width aria-hidden="true" /></template>
                                </Button>
                                <SaveFeedback section="lead_time" />
                                <FontAwesomeIcon icon="fal fa-info-circle" class="cursor-help text-gray-400 hover:text-gray-600" fixed-width
                                    v-tooltip="ctrans('Marks this lead time as agreed with the supplier')"
                                    :aria-label="ctrans('About confirming the lead time')" />
                            </template>
                        </dd>
                    </div>
                </dl>

                <div v-if="canEdit" class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                    <label class="text-xs text-gray-500">
                        {{ ctrans("MOQ (cartons)") }}
                        <InputNumber v-model="keyForm.minimum_carton_order" :min="1" :useGrouping="false" :placeholder="panel.key.moq.cartons?.toString()" inputClass="!w-24 !py-1 !text-sm" :class="fieldFocusClass" class="mt-1 block" />
                    </label>
                    <label class="text-xs text-gray-500">
                        {{ ctrans("Lead time (days)") }}
                        <InputNumber v-model="keyForm.estimated_lead_time_days" :min="1" :max="365" :useGrouping="false" :placeholder="panel.key.lead_time.days?.toString()" inputClass="!w-24 !py-1 !text-sm" :class="fieldFocusClass" class="mt-1 block" />
                    </label>
                    <Button type="secondary" :label="ctrans('Save')" icon="fal fa-save" :loading="savingSection === 'key'" :disabled="!keyFiguresChanged" @click="saveKeyFigures">
                        <!-- <template #icon><FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" /></template> -->
                    </Button>
                    <SaveFeedback section="key" />
                </div>
            </section>

            <section id="agent-measurements" :class="[card, 'transition-shadow', highlightClass('measurements')]">
                <h3 :class="sectionTitle">
                    <FontAwesomeIcon icon="fal fa-ruler-combined" fixed-width aria-hidden="true" />
                    {{ ctrans("Weights and measurements") }}
                </h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="py-1 font-medium" />
                            <th class="py-1 font-medium">{{ ctrans("L (cm)") }}</th>
                            <th class="py-1 font-medium">{{ ctrans("W (cm)") }}</th>
                            <th class="py-1 font-medium">{{ ctrans("H (cm)") }}</th>
                            <th class="py-1 font-medium">{{ ctrans("Net (kg)") }}</th>
                            <th class="py-1 font-medium">{{ ctrans("Gross (kg)") }}</th>
                            <th class="py-1 font-medium">{{ ctrans("CBM") }} {{ ctrans("(m³)") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="tabular-nums text-gray-700">
                            <td class="py-2 pr-2 font-medium text-gray-600">{{ ctrans("Unit") }}</td>
                            <td class="py-2">{{ number(panel.measurements.unit.dimensions?.l ?? null, 1) }}</td>
                            <td class="py-2">{{ number(panel.measurements.unit.dimensions?.w ?? null, 1) }}</td>
                            <td class="py-2">{{ number(panel.measurements.unit.dimensions?.h ?? null, 1) }}</td>
                            <td class="py-2">{{ panel.measurements.unit.net_weight === null ? "-" : number(panel.measurements.unit.net_weight / 1000, 3) }}</td>
                            <td class="py-2">{{ panel.measurements.unit.gross_weight === null ? "-" : number(panel.measurements.unit.gross_weight / 1000, 3) }}</td>
                            <td class="py-2 text-gray-400">-</td>
                        </tr>
                        <tr class="tabular-nums text-gray-700">
                            <td class="py-2 pr-2 font-medium text-gray-600">{{ ctrans("Carton") }}</td>
                            <template v-if="canEdit">
                                <td v-for="side in ['l', 'w', 'h']" :key="side" class="py-2 pr-1">
                                    <InputNumber v-model="measurementsForm[side]" :min="0" :maxFractionDigits="1" inputClass="!w-16 !py-1 !text-sm" :class="fieldFocusClass" />
                                </td>
                                <td class="py-2 pr-1"><InputNumber v-model="measurementsForm.net_kg" :min="0" :maxFractionDigits="3" inputClass="!w-20 !py-1 !text-sm" :class="fieldFocusClass" /></td>
                                <td class="py-2 pr-1"><InputNumber v-model="measurementsForm.gross_kg" :min="0" :maxFractionDigits="3" inputClass="!w-20 !py-1 !text-sm" :class="fieldFocusClass" /></td>
                                <td class="py-2"><InputNumber v-model="measurementsForm.cbm" inputId="agent-carton-cbm" :min="0" :maxFractionDigits="6" inputClass="!w-24 !py-1 !text-sm" :class="fieldFocusClass" /></td>
                            </template>
                            <template v-else>
                                <td class="py-2">{{ number(panel.measurements.carton.dimensions?.l ?? null, 1) }}</td>
                                <td class="py-2">{{ number(panel.measurements.carton.dimensions?.w ?? null, 1) }}</td>
                                <td class="py-2">{{ number(panel.measurements.carton.dimensions?.h ?? null, 1) }}</td>
                                <td class="py-2">{{ panel.measurements.carton.net_weight === null ? "-" : number(panel.measurements.carton.net_weight / 1000, 3) }}</td>
                                <td class="py-2">{{ panel.measurements.carton.gross_weight === null ? "-" : number(panel.measurements.carton.gross_weight / 1000, 3) }}</td>
                                <td class="py-2">{{ number(panel.measurements.carton.cbm, 4) }}</td>
                            </template>
                        </tr>
                    </tbody>
                </table>

                <div v-if="canEdit" class="mt-3 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-xs text-gray-500">
                        {{ ctrans("Units per carton") }}
                        <InputNumber v-model="measurementsForm.units_per_carton" :min="1" :useGrouping="false" inputClass="!w-20 !py-1 !text-sm" :class="fieldFocusClass" />
                    </label>
                    <button v-if="cbmFromDimensions && cbmFromDimensions !== measurementsForm.cbm" type="button"
                        class="text-xs text-gray-600 underline decoration-dotted hover:text-gray-900"
                        @click="measurementsForm.cbm = cbmFromDimensions">
                        {{ ctrans("Use :cbm m³ from the carton size", { cbm: cbmFromDimensions }) }}
                    </button>
                    <Button type="tertiary" class="ml-auto" :label="ctrans('Save')" :loading="savingSection === 'measurements'" :disabled="!measurementsChanged" @click="saveMeasurements">
                        <template #icon><FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <SaveFeedback section="measurements" />
                </div>

                <ul v-if="panel.warnings.length || panel.cbm_siblings.length" class="mt-3 space-y-1">
                    <li v-for="warning in panel.warnings" :key="warning.key" class="flex gap-2 rounded bg-amber-50 p-2 text-xs text-amber-800">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" class="mt-0.5" />
                        {{ warning.message }}
                    </li>
                    <li v-for="sibling in panel.cbm_siblings" :key="sibling.code + sibling.supplier" class="flex gap-2 rounded bg-amber-50 p-2 text-xs text-amber-800">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" class="mt-0.5" />
                        {{ ctrans(":code at :supplier has a CBM of :cbm m³, :factor this one. A decimal point probably slipped on one of them.", { code: sibling.code, supplier: sibling.supplier, cbm: sibling.cbm, factor: sibling.factor }) }}
                    </li>
                </ul>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <section :class="card">
                <h3 :class="sectionTitle">
                    <FontAwesomeIcon icon="fal fa-shipping-fast" fixed-width aria-hidden="true" />
                    {{ ctrans("Cartons per container") }}
                </h3>
                <dl v-if="panel.containers" class="divide-y divide-gray-100">
                    <div v-for="(cartons, container) in panel.containers" :key="container" class="flex items-baseline justify-between py-2">
                        <dt class="text-sm text-gray-500">{{ containerLabels[container] }}</dt>
                        <dd class="text-base font-semibold tabular-nums text-gray-800">{{ number(cartons) }}</dd>
                    </div>
                </dl>
                <button v-else type="button" class="text-sm italic text-[--app-accent] underline decoration-dotted underline-offset-2 hover:text-[--app-accent-strong]"
                    @click="jumpTo('measurements', 'agent-carton-cbm')">
                    {{ ctrans("Needs the CBM") }}
                </button>
            </section>
        </div>

        <section :class="[card, 'md:col-span-3']">
            <h3 :class="sectionTitle">{{ ctrans("Production calendar") }}
                <span v-if="panel.production_calendar.family" class="ml-auto font-normal normal-case tracking-normal text-gray-400">
                    {{ ctrans("From family at supplier: :family", { family: panel.production_calendar.family }) }}
                </span>
            </h3>
            <ProductionCalendarStrip :calendar="panel.production_calendar.months" :interactive="false" />
            <p v-if="panel.production_calendar.advice.message" class="mt-2 text-sm"
                :class="{ 'text-amber-800': panel.production_calendar.advice.status === 'order_before_stop', 'text-green-700': panel.production_calendar.advice.status === 'good_time', 'text-red-700': panel.production_calendar.advice.status === 'cannot_produce', 'text-gray-700': ['peak_or_slow', 'not_set'].includes(panel.production_calendar.advice.status) }">
                {{ panel.production_calendar.advice.message }}
                <span v-if="panel.production_calendar.advice.arrives_on" class="text-gray-500">
                    {{ ctrans("Ordered today, it reaches the warehouse about :date.", { date: new Date(panel.production_calendar.advice.arrives_on).toLocaleDateString() }) }}
                </span>
            </p>
            <p v-else class="mt-2 text-sm italic text-gray-400">{{ ctrans("No production calendar yet: set it on the family in the By supplier tab of the products list.") }}</p>
        </section>

        <div class="flex min-w-0 flex-col gap-6 md:col-span-2">
            <section id="agent-packaging" :class="card">
                <h3 :class="sectionTitle">
                    <FontAwesomeIcon icon="fal fa-box-open" fixed-width aria-hidden="true" />
                    {{ ctrans("Packaging terms") }}
                </h3>
                <div class="space-y-2">
                    <div v-for="term in paidTerms" :key="term.key" class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm text-gray-600">
                            {{ term.label }}
                            <span v-if="sourceLabel(panel.packaging.terms[term.key].source) && !packagingForm[term.key]" class="ml-1 text-xs italic text-gray-400">
                                {{ panel.packaging.options[panel.packaging.terms[term.key].value as string] }} · {{ sourceLabel(panel.packaging.terms[term.key].source) }}
                            </span>
                        </span>
                        <SegmentedToggle v-if="canEdit" v-model="packagingForm[term.key]" :options="payerOptions" :ariaLabel="term.label" />
                        <span v-else class="text-sm text-gray-800">{{ panel.packaging.options[panel.packaging.terms[term.key].value as string] ?? "-" }}</span>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-2">
                        <span class="text-sm text-gray-600">{{ ctrans("Protective materials in the carton") }}</span>
                        <InputText v-if="canEdit" v-model="packagingForm.protective_materials" class="!w-64 !py-1 !text-sm" :class="fieldFocusClass"
                            :placeholder="panel.packaging.terms.protective_materials.value?.toString() ?? ''" />
                        <span v-else class="text-sm text-gray-800">{{ panel.packaging.terms.protective_materials.value ?? "-" }}</span>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm text-gray-600">{{ ctrans("Silica gel sachets per carton") }}</span>
                        <InputNumber v-if="canEdit" v-model="packagingForm.silica_gel_sachets" :min="0" :useGrouping="false" inputClass="!w-20 !py-1 !text-sm" :class="fieldFocusClass"
                            :placeholder="panel.packaging.terms.silica_gel_sachets.value?.toString() ?? ''" />
                        <span v-else class="text-sm text-gray-800">{{ panel.packaging.terms.silica_gel_sachets.value ?? "-" }}</span>
                    </div>
                </div>
                <div v-if="canEdit" class="mt-3 flex justify-end">
                    <Button type="tertiary" :label="ctrans('Save')" :loading="savingSection === 'packaging'" :disabled="!packagingChanged" @click="savePackaging">
                        <template #icon><FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <SaveFeedback section="packaging" />
                </div>
            </section>
        </div>

        <div class="flex min-w-0 flex-col gap-6">
            <section id="agent-availability" :class="card">
                <h3 :class="sectionTitle">{{ ctrans("Availability") }}</h3>
                <template v-if="canEdit">
                    <SegmentedToggle v-model="availabilityForm.status" :options="statusOptions" :ariaLabel="ctrans('Availability')" />
                    <div v-if="availabilityForm.status !== 'available'" class="mt-3 space-y-2">
                        <Select v-model="availabilityForm.reason" :options="toOptions(panel.availability.reason_options)" optionLabel="label" optionValue="value"
                            :placeholder="ctrans('Reason')" class="w-full" :class="fieldFocusClass" />
                        <DatePicker v-model="availableFrom" :placeholder="ctrans('Available from')" showIcon iconDisplay="input" :manualInput="false" class="w-full" :class="fieldFocusClass" />
                        <InputNumber v-model="availabilityForm.new_cost" :min="0" :maxFractionDigits="4" :placeholder="ctrans('New cost asked (:currency)', { currency: panel.currency })" class="w-full" inputClass="!w-full" :class="fieldFocusClass" />
                    </div>
                    <Textarea v-model="availabilityForm.note" :placeholder="ctrans('Note')" rows="2" autoResize class="mt-2 w-full !text-sm" :class="fieldFocusClass" />
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-400">{{ ctrans("Purchasing is told when you save") }}</span>
                        <Button type="tertiary" :label="ctrans('Save')" :loading="savingSection === 'availability'"
                            :disabled="!availabilityChanged || (availabilityForm.status !== 'available' && !availabilityForm.reason)" @click="saveAvailability">
                            <template #icon><FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <SaveFeedback section="availability" />
                    </div>
                </template>
                <dl v-else class="text-sm">
                    <dt class="text-gray-800">{{ panel.availability.status_options[panel.availability.status] }}</dt>
                    <dd v-if="panel.availability.reason" class="text-gray-500">{{ panel.availability.reason_options[panel.availability.reason] }}</dd>
                    <dd v-if="panel.availability.available_from" class="text-gray-500">{{ ctrans("Available from :date", { date: panel.availability.available_from }) }}</dd>
                    <dd v-if="panel.availability.note" class="text-gray-500">{{ panel.availability.note }}</dd>
                </dl>
            </section>
        </div>
    </div>
</template>
