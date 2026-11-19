<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import RunExpiryInput, { expiryFromMonths, toIsoDate, type PeriodAfterOpeningOption, type RunShelfLife } from "@/Components/Production/RunExpiryInput.vue"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import DatePicker from "primevue/datepicker"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type ArtefactOption = { id: number, code: string, name: string, artefact_department_id: number | null, packed_in: number | null, stock_available: number | null, gross_weight: number | null, on_board: number, run_sources?: string[], shelf_life?: RunShelfLife | null }
type Line = { artefact: ArtefactOption, quantity: number, batch_code: string, expiry_date: string, period_after_opening: string | null }

const props = defineProps<{
    isOpen: boolean
    options: { reasons: string[], periods_after_opening?: PeriodAfterOpeningOption[], artefacts: ArtefactOption[] } | null
    artisans: { id: number, name: string, department_ids?: number[] }[]
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const reasonLabels: Record<string, string> = {
    stock: ctrans("Stock"),
    partner: ctrans("Partner / PO"),
    sample: ctrans("Sample"),
    rework: ctrans("Rework"),
    other: ctrans("Other"),
}

const form = reactive({
    reason: "",
    notes: "",
    needed_by: null as Date | null,
    employee_id: null as number | null,
    lines: [] as Line[],
})
const search = ref("")
const saving = ref(false)
const errors = ref<Record<string, string>>({})

watch(() => props.isOpen, (open) => {
    if (open && !props.options) {
        router.reload({ only: ["manualJobOrder"] })
    }
}, { immediate: true })

const reasonOptions = computed(() => (props.options?.reasons ?? []).map(reason => ({ value: reason, label: reasonLabels[reason] ?? reason })))
const lineError = (index: number) => errors.value[`lines.${index}.batch_code`] ?? errors.value[`lines.${index}.expiry_date`] ?? errors.value[`lines.${index}.period_after_opening`] ?? null
const otherError = computed(() => Object.entries(errors.value).find(([key]) => key !== "reason" && key !== "lines" && !/^lines\.\d+\.(batch_code|expiry_date|period_after_opening)$/.test(key))?.[1] ?? null)

const matches = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (term.length < 2 || !props.options) {
        return []
    }

    return props.options.artefacts
        .filter(artefact => !form.lines.some(line => line.artefact.id === artefact.id))
        .filter(artefact => artefact.code.toLowerCase().includes(term) || artefact.name.toLowerCase().includes(term))
        .slice(0, 12)
})

const hasNoArtefact = computed(() => {
    const term = search.value.trim().toLowerCase()

    return term.length >= 2 && !!props.options && !props.options.artefacts.some(artefact => artefact.code.toLowerCase().includes(term) || artefact.name.toLowerCase().includes(term))
})

function addLine(artefact: ArtefactOption) {
    form.lines.push({
        artefact,
        quantity: 1,
        batch_code: "",
        expiry_date: expiryFromMonths(artefact.shelf_life?.expiry_months),
        period_after_opening: artefact.shelf_life?.period_after_opening ?? null,
    })
    search.value = ""
}

const labelNeeds = (line: Line, source: string) => !!line.artefact.run_sources?.includes(source)

const lineDepartmentIds = computed(() => form.lines.map(line => line.artefact.artefact_department_id).filter((id): id is number => !!id))
const worksOnTheseProducts = (artisan: { department_ids?: number[] }) => lineDepartmentIds.value.some(id => artisan.department_ids?.includes(id))
const artisanOptions = computed(() => {
    const toOption = (artisan: { id: number, name: string }) => ({ value: artisan.id, label: artisan.name })
    const area = props.artisans.filter(worksOnTheseProducts)
    if (!area.length) {
        return [{ label: ctrans("Artisans"), items: props.artisans.map(toOption) }]
    }

    return [
        { label: ctrans("Work where these products are made"), items: area.map(toOption) },
        { label: ctrans("Everyone else"), items: props.artisans.filter(artisan => !worksOnTheseProducts(artisan)).map(toOption) },
    ]
})

const units = (line: Line) => line.artefact.packed_in ? line.quantity * line.artefact.packed_in : null
const totalUnits = computed(() => form.lines.reduce((total, line) => total + (units(line) ?? 0), 0))
const totalKilos = computed(() => form.lines.reduce((total, line) => total + (line.artefact.gross_weight ?? 0) * line.quantity, 0) / 1000)

const canSave = computed(() => !saving.value && !!form.reason && form.lines.length > 0 && form.lines.every(line => line.quantity > 0))

function close() {
    form.reason = ""
    form.notes = ""
    form.needed_by = null
    form.employee_id = null
    form.lines = []
    errors.value = {}
    emits("onClose")
}

function save() {
    saving.value = true
    router.post(
        route("grp.org.productions.show.to_produce.manual_job_order.store", [route().params["organisation"], route().params["production"]]),
        {
            reason: form.reason,
            notes: form.notes || null,
            needed_by: form.needed_by ? toIsoDate(form.needed_by) : null,
            employee_id: form.employee_id,
            lines: form.lines.map(line => ({
                artefact_id: line.artefact.id,
                quantity: line.quantity,
                batch_code: line.batch_code.trim() || null,
                expiry_date: line.period_after_opening ? null : line.expiry_date || null,
                period_after_opening: line.period_after_opening,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => close(),
            onError: (errorBag) => errors.value = errorBag,
            onFinish: () => saving.value = false,
        }
    )
}

const inputPt = { root: { class: "w-full" } }
const smallInputPt = { root: { class: "w-full min-w-0 !py-0.5 !text-xs" } }
</script>

<template>
    <Modal :isOpen="isOpen" width="w-full max-w-3xl" @onClose="close">
        <div class="text-sm">
            <h2 class="text-lg font-semibold">{{ ctrans("Create job order") }}</h2>
            <p class="text-gray-500">{{ ctrans("Plan a job by hand. It goes on the board as Assigned, waiting for the floor. The reference is given when it is saved.") }}</p>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Why is it made?") }} <span class="text-red-500">*</span></span>
                    <Select v-model="form.reason" :options="reasonOptions" optionLabel="label" optionValue="value" :placeholder="ctrans('Choose a reason')" :pt="inputPt" />
                    <span v-if="errors.reason" class="text-xs text-red-600">{{ errors.reason }}</span>
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Name or notes") }} <span class="font-normal text-gray-400">({{ ctrans("optional") }})</span></span>
                    <InputText v-model="form.notes" maxlength="255" :placeholder="ctrans('e.g. ELEMENTS - PO85')" :pt="inputPt" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Artisan") }}</span>
                    <Select
                        v-model="form.employee_id"
                        :options="artisanOptions"
                        optionLabel="label"
                        optionValue="value"
                        optionGroupLabel="label"
                        optionGroupChildren="items"
                        showClear
                        filter
                        :placeholder="ctrans('Whoever usually makes the first product')"
                        :pt="inputPt" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Target date") }} <span class="font-normal text-gray-400">({{ ctrans("optional") }})</span></span>
                    <DatePicker v-model="form.needed_by" dateFormat="dd/mm/yy" showButtonBar :pt="inputPt" />
                </label>
            </div>

            <div class="mt-5">
                <div class="font-medium">{{ ctrans("Products to make") }} <span class="text-red-500">*</span> <span class="font-normal text-gray-400">· {{ ctrans("in SKOs, as the board counts them") }}</span></div>
                <div class="relative mt-1">
                    <InputText v-model="search" type="search" :disabled="!options" :placeholder="options ? ctrans('Search product code or name') : ctrans('Loading products…')" :pt="inputPt" />
                    <div v-if="matches.length" class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <button v-for="artefact in matches" :key="artefact.id" type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800" @click="addLine(artefact)">
                            <span class="whitespace-nowrap font-medium">{{ artefact.code }}</span>
                            <span class="truncate text-gray-600">{{ artefact.name }}</span>
                        </button>
                    </div>
                </div>
                <div v-if="hasNoArtefact" class="mt-1 text-xs text-gray-500">
                    {{ ctrans("No product to make matches :term. A SKU needs an artefact before it can go on the board.", { term: search.trim() }) }}
                    <Link :href="route('grp.org.productions.show.crafts.artefacts.create', [route().params['organisation'], route().params['production']])" class="font-medium text-gray-700 hover:underline">{{ ctrans("Create the artefact") }}</Link>
                </div>
                <span v-if="errors.lines" class="text-xs text-red-600">{{ errors.lines }}</span>

                <table v-if="form.lines.length" class="mt-3 w-full">
                    <thead class="whitespace-nowrap text-left text-xs uppercase text-gray-400">
                        <tr>
                            <th class="py-1">{{ ctrans("Product") }}</th>
                            <th class="w-28 py-1">{{ ctrans("SKOs") }}</th>
                            <th class="w-20 py-1 text-right">{{ ctrans("Units") }}</th>
                            <th class="w-8" />
                        </tr>
                    </thead>
                    <tbody v-for="(line, index) in form.lines" :key="line.artefact.id" class="border-t border-gray-100 dark:border-gray-800">
                        <tr class="align-top">
                            <td class="pt-1.5">
                                <div><span class="whitespace-nowrap font-medium">{{ line.artefact.code }}</span> <span class="text-gray-600">{{ line.artefact.name }}</span></div>
                                <div v-if="line.artefact.on_board" class="text-xs text-amber-700">{{ line.artefact.on_board === 1 ? ctrans("Already on the board once") : ctrans("Already on the board :count times", { count: String(line.artefact.on_board) }) }}</div>
                                <div v-if="line.artefact.stock_available" class="text-xs text-amber-700">{{ ctrans(":count SKOs in stock now", { count: useLocaleStore().number(line.artefact.stock_available) }) }}</div>
                            </td>
                            <td class="pt-1.5"><InputNumber v-model="line.quantity" :min="1" :useGrouping="false" :aria-label="ctrans('SKOs')" :pt="{ root: { class: 'w-24' }, pcInputText: { root: { class: '!w-24 !py-0.5' } } }" /></td>
                            <td class="pt-1.5 text-right tabular-nums text-gray-600">{{ units(line) !== null ? useLocaleStore().number(units(line) as number) : "—" }}</td>
                            <td class="pt-1.5 text-right">
                                <button type="button" class="text-gray-400 hover:text-red-600" :title="ctrans('Remove')" @click="form.lines.splice(index, 1)">
                                    <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" class="pb-2 pt-1">
                                <div class="grid grid-cols-2 gap-2 text-xs" role="group" :aria-label="ctrans('What the labels print')">
                                    <label class="flex min-w-0 flex-col gap-0.5">
                                        <span class="text-gray-500">{{ ctrans("Batch code") }} <span v-if="labelNeeds(line, 'batch_code')" class="text-red-500">*</span></span>
                                        <InputText
                                            v-model="line.batch_code"
                                            maxlength="64"
                                            :placeholder="labelNeeds(line, 'batch_code') ? ctrans('Printed on the label') : ctrans('From job order')"
                                            :pt="smallInputPt" />
                                    </label>
                                    <RunExpiryInput
                                        v-model:expiryDate="line.expiry_date"
                                        v-model:periodAfterOpening="line.period_after_opening"
                                        :periods="options?.periods_after_opening ?? []"
                                        :months="line.artefact.shelf_life?.expiry_months"
                                        :required="labelNeeds(line, 'expiry_date')" />
                                </div>
                                <div v-if="lineError(index)" class="mt-1 text-xs text-red-600">{{ lineError(index) }}</div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 font-medium dark:border-gray-700">
                            <td class="py-1.5" colspan="2">{{ ctrans("Total") }}<span v-if="totalKilos" class="font-normal text-gray-500"> · {{ useLocaleStore().number(Math.round(totalKilos * 10) / 10) }} kg</span></td>
                            <td class="py-1.5 text-right tabular-nums">{{ useLocaleStore().number(totalUnits) }}</td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
                <p v-if="form.lines.length" class="mt-1 text-xs text-gray-400">{{ ctrans("An empty batch code is named after the job order once it is made.") }}</p>
            </div>

            <div v-if="otherError" class="mt-3 text-xs text-red-600">{{ otherError }}</div>
            <div class="mt-5 flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="close">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Create job order')" :loading="saving" :disabled="!canSave" @click="save">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
    </Modal>
</template>
