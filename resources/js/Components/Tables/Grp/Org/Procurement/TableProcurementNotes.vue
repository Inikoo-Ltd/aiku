<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Popover from 'primevue/popover'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import InputNumber from 'primevue/inputnumber'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import Table from '@/Components/Table/Table.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { faStickyNote, faThumbtack, faCheck, faInfoCircle, faExclamationTriangle } from '@fal'
import { faExclamationCircle, faThumbtack as fasThumbtack } from '@fas'
import { routeType } from '@/types/route'
import { noteCategoryClass, noteSeverityIcon } from '@/Components/Procurement/procurementNoteColours'

library.add(faStickyNote, faThumbtack, faCheck, faInfoCircle, faExclamationTriangle, faExclamationCircle, fasThumbtack)

type Option = { value: string | number, label: string }

type Note = {
    id: number
    author_user_id: number | null
    state: string
    is_pinned: boolean
}

const props = defineProps<{
    data: object
    tab?: string
    storeRoute?: routeType
    agreement?: {
        can_write: boolean
        is_supervisor: boolean
        user_id: number
        routes: { store_note: routeType } | null
        options: {
            categories: Option[]
            severities: Option[]
            visibilities: Option[]
            currencies?: Option[]
            organisation_id: number | null
        } | null
        can_write_notes?: boolean
        note_route?: routeType | null
        note_options?: {
            categories: Option[]
            severities: Option[]
            visibilities: Option[]
            currencies?: Option[]
            organisation_id: number | null
            organisation_is_fixed: boolean
            organisation_label: string
        } | null
    }
}>()

const popover = ref()
const isSaving = ref(false)
const options = computed(() => props.agreement?.note_options ?? props.agreement?.options ?? null)
const saveError = ref<string | null>(null)
const updateErrors = ref<Record<number, string>>({})
const emptyNote = () => ({ title: '', body: '', category: options.value?.categories?.length === 1 ? options.value.categories[0].value : 'general', severity: 'info', visibility: options.value?.visibilities?.length === 1 ? options.value.visibilities[0].value : 'internal', is_pinned: false, only_this_organisation: false, incident_amount: null as number | null, incident_currency_id: null as number | null, incident_type: null as string | null, incident_quantity: null as number | null })
const note = ref(emptyNote())

const noteStoreRoute = computed(() => props.storeRoute
    ?? (props.agreement?.can_write ? props.agreement.routes?.store_note : undefined)
    ?? (props.agreement?.can_write_notes ? props.agreement.note_route ?? undefined : undefined))
const canSave = computed(() => (note.value.body.trim() || note.value.title.trim()) && !isSaving.value)

function reload() {
    router.reload({ only: [props.tab ?? 'notes', 'agreement'] })
}

function firstError(errors: Record<string, string>) {
    return Object.values(errors)[0] ?? ctrans('Failed to save the note')
}

function addNote() {
    if (!noteStoreRoute.value || !canSave.value) {
        return
    }

    const { only_this_organisation, incident_amount, incident_currency_id, incident_type, incident_quantity, ...fields } = note.value
    const incident = fields.category === 'incident'
        ? { incident_type, incident_quantity, ...(incident_amount !== null ? { incident_amount, incident_currency_id } : {}) }
        : {}
    const organisationId = options.value?.organisation_is_fixed || only_this_organisation ? options.value?.organisation_id ?? null : null
    const payload = options.value
        ? { ...fields, ...incident, organisation_id: organisationId }
        : { body: fields.body }

    router.post(route(noteStoreRoute.value.name, noteStoreRoute.value.parameters), payload, {
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
            saveError.value = null
        },
        onFinish: () => { isSaving.value = false },
        onSuccess: () => {
            note.value = emptyNote()
            popover.value?.hide()
            reload()
        },
        onError: (errors) => { saveError.value = firstError(errors) },
    })
}

function canManage(item: Note) {
    return !!props.agreement && (props.agreement.is_supervisor || item.author_user_id === props.agreement.user_id)
}

function update(item: Note, payload: Record<string, unknown>) {
    delete updateErrors.value[item.id]
    router.patch(route('grp.models.procurement_note.update', { procurementNote: item.id }), payload, {
        preserveScroll: true,
        onSuccess: reload,
        onError: (errors) => { updateErrors.value[item.id] = firstError(errors) },
    })
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template v-if="noteStoreRoute" #add-on-button>
            <Button :label="ctrans('Add note')" type="tertiary" size="xs" @click="(event) => popover.toggle(event)">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sticky-note" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <Popover ref="popover">
                <div class="flex flex-col gap-3 w-[26rem] text-sm">
                    <InputText v-if="options" v-model="note.title" :placeholder="ctrans('One line, shown in lists and alerts')" />
                    <PureTextarea v-model="note.body" :rows="5" :placeholder="ctrans('Write a note')" />
                    <div v-if="options" class="grid grid-cols-3 gap-2">
                        <Select v-model="note.category" :options="options.categories" optionLabel="label" optionValue="value" />
                        <Select v-model="note.severity" :options="options.severities" optionLabel="label" optionValue="value" />
                        <Select v-model="note.visibility" :options="options.visibilities" optionLabel="label" optionValue="value" />
                    </div>
                    <div v-if="options?.incident_types && note.category === 'incident'" class="grid grid-cols-2 gap-2">
                        <Select v-model="note.incident_type" :options="options.incident_types" optionLabel="label" optionValue="value" showClear :placeholder="ctrans('Issue type')" />
                        <InputNumber v-model="note.incident_quantity" :minFractionDigits="0" :maxFractionDigits="3" :min="0" :placeholder="ctrans('Quantity affected')" fluid />
                    </div>
                    <div v-if="options?.currencies && note.category === 'incident'" class="grid grid-cols-2 gap-2">
                        <InputNumber v-model="note.incident_amount" :minFractionDigits="0" :maxFractionDigits="2" :min="0" :placeholder="ctrans('Amount overcharged or claimed')" fluid />
                        <Select v-model="note.incident_currency_id" :options="options.currencies" optionLabel="label" optionValue="value" filter :placeholder="ctrans('Currency')" />
                    </div>
                    <div v-if="options" class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <label class="flex items-center gap-2">
                            <ToggleSwitch v-model="note.is_pinned" />
                            <span>{{ ctrans('Pin: show on purchase orders') }}</span>
                        </label>
                        <label v-if="options.organisation_id && !options.organisation_is_fixed" class="flex items-center gap-2">
                            <ToggleSwitch v-model="note.only_this_organisation" />
                            <span>{{ ctrans('Only for this organisation') }}</span>
                        </label>
                    </div>
                    <div v-if="options?.organisation_is_fixed" class="text-xs text-gray-500">{{ ctrans('For: :organisation', { organisation: options.organisation_label }) }}</div>
                    <div v-if="saveError" class="text-xs text-red-600">{{ saveError }}</div>
                    <div class="flex justify-end">
                        <Button :label="ctrans('Save')" type="tertiary" size="xs" :loading="isSaving" :disabled="!canSave" @click="addNote">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                </div>
            </Popover>
        </template>

        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: 'hm' }) }}</span>
        </template>

        <template #cell(category)="{ item }">
            <span class="inline-flex items-center gap-1 whitespace-nowrap rounded border px-1.5 py-0.5 text-xs" :class="noteCategoryClass[item.category.colour]">
                <FontAwesomeIcon v-if="item.severity !== 'info'" :icon="noteSeverityIcon[item.severity].icon" :class="item.category.colour === 'black' ? 'text-white' : noteSeverityIcon[item.severity].class" fixed-width :title="item.severity_label" />
                {{ item.category.label }}
            </span>
            <div v-if="item.visibility !== 'internal'" class="mt-0.5 text-xs text-gray-500">{{ ctrans('Visible to: :who', { who: item.visibility_label }) }}</div>
        </template>

        <template #cell(author)="{ item }">
            {{ item.author ?? '-' }}
        </template>

        <template #cell(note)="{ item }">
            <div :class="{ 'line-through text-gray-400': item.strikethrough }">
                <div v-if="item.title" class="font-medium">
                    <FontAwesomeIcon v-if="item.is_pinned" icon="fas fa-thumbtack" class="mr-1 text-gray-500" :title="ctrans('Pinned')" />
                    {{ item.title }}
                </div>
                <div class="whitespace-pre-line">{{ item.note }}</div>
            </div>
            <div v-if="item.supplier_product_code" class="text-xs text-gray-500">{{ ctrans('On product') }} {{ item.supplier_product_code }}</div>
            <div v-if="item.incident_outcome_label" class="text-xs text-gray-500">
                <template v-if="item.incident_type_label">{{ item.incident_type_label }}<template v-if="item.incident_quantity"> × {{ Number(item.incident_quantity) }}</template> · </template>{{ item.incident_outcome_label }}<template v-if="item.incident_amount"> · {{ item.incident_currency_code }} {{ item.incident_amount }}</template>
            </div>
            <div v-if="item.number_purchase_orders > 1" class="text-xs text-gray-500">
                {{ ctrans('On :count purchase orders', { count: item.number_purchase_orders }) }}
            </div>
            <div v-else-if="item.purchase_order_reference" class="text-xs text-gray-500">
                {{ ctrans('On purchase order') }} {{ item.purchase_order_reference }}
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <div v-if="canManage(item) && item.state === 'active'" class="flex justify-end gap-1">
                <Button :label="item.is_pinned ? ctrans('Unpin') : ctrans('Pin')" type="tertiary" size="xs" @click="update(item, { is_pinned: !item.is_pinned })">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-thumbtack" class="text-gray-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button :label="ctrans('Resolve')" type="tertiary" size="xs" @click="update(item, { state: 'resolved' })">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <div v-if="updateErrors[item.id]" class="mt-1 text-right text-xs text-red-600">{{ updateErrors[item.id] }}</div>
        </template>
    </Table>
</template>
