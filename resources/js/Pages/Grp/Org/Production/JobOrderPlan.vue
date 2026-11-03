<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 12 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router, usePoll } from "@inertiajs/vue3"
import { computed } from "vue"
import DatePicker from "primevue/datepicker"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { mixBatchesLabel } from "@/Composables/mixBatches"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCalendarDay, faCheck, faTimes, faCircle, faSpinner, faExclamationTriangle } from "@fal"

library.add(faCalendarDay, faCheck, faTimes, faCircle, faSpinner, faExclamationTriangle)

type Step = {
    id: number
    name: string
    state: "todo" | "in_progress" | "done"
    quantity_made: number
    quantity_required: number
    unit: string | null
    mix_batch_kg: number | null
    employee_id: number | null
    only_for: number[]
    operator: string | null
    working_on_by: string[]
}

type PlanItem = {
    id: number
    job_order_id: number
    reference: string
    job_order_slug: string
    state_label: string
    planned_for: string
    plan_position: number | null
    is_carried_over: boolean
    artefact_code: string | null
    artefact_name: string | null
    artefact_department_id: number | null
    quantity: number
    steps: Step[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
    date: string
    today: string
    can_edit: boolean
    summary: { jobs: number, carried_over: number, steps: number, steps_done: number, steps_unassigned: number }
    operators: { id: number, name: string, department_ids: number[] }[]
}>()

usePoll(30000, { only: ["data", "summary"] })

const locale = useLocaleStore()

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const planDate = computed<Date | null>({
    get: () => new Date(`${props.date}T00:00:00`),
    set: (value) => {
        if (!value) return
        router.get(route(route().current() as string, { ...route().params, date: toIsoDate(value) }), {}, { preserveScroll: true })
    },
})

const selectPt = { label: { class: "!py-0.5 !text-xs" } }
const reloadOptions = { preserveScroll: true, only: ["data", "summary"] }

function jobOrderRoute(item: PlanItem) {
    return route("grp.org.productions.show.operations.job-orders.show", [route().params["organisation"], route().params["production"], item.job_order_slug])
}

function departmentFirst(options: { id: number, name: string, department_ids: number[] }[], departmentId: number | null) {
    if (!departmentId) return options
    return [...options.filter((option) => option.department_ids.includes(departmentId)), ...options.filter((option) => !option.department_ids.includes(departmentId))]
}

function assign(step: Step, employeeId: number | null) {
    router.patch(route("grp.models.job-order-item-task.assign", { jobOrderItemTask: step.id }), { employee_id: employeeId ?? null }, reloadOptions)
}

function updateJobOrder(item: PlanItem, modelData: { planned_for?: string | null, plan_position?: number }) {
    router.patch(route("grp.models.job-order.update", { jobOrder: item.job_order_id }), modelData, reloadOptions)
}

function setPosition(item: PlanItem, position: number | null) {
    if (position && position !== item.plan_position) updateJobOrder(item, { plan_position: position })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700">
        <DatePicker v-model="planDate" dateFormat="D d M yy" :manualInput="false" showIcon iconDisplay="input" :pt="{ pcInputText: { root: { class: '!w-40 !py-1 !text-xs' } } }" :aria-label="ctrans('Day')" />
        <span>{{ ctransChoice(":count job|:count jobs", summary.jobs) }}</span>
        <span class="tabular-nums">
            <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
            {{ ctrans(":done of :total steps done", { done: locale.number(summary.steps_done), total: locale.number(summary.steps) }) }}
        </span>
        <span v-if="summary.steps_unassigned" class="text-amber-700">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
            {{ ctransChoice(":count step without an operator|:count steps without an operator", summary.steps_unassigned) }}
        </span>
        <span v-if="summary.carried_over" class="text-amber-700">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
            {{ ctransChoice(":count job not finished on its day|:count jobs not finished on their day", summary.carried_over) }}
        </span>
    </div>

    <Table :resource="data" class="mt-4">
        <template #cell(plan_position)="{ item }">
            <InputNumber
                v-if="can_edit"
                :modelValue="item.plan_position"
                :min="1"
                :max="999"
                :useGrouping="false"
                :pt="{ pcInputText: { root: { class: '!w-12 !py-0.5 !text-xs !text-right' } } }"
                :aria-label="ctrans('Order')"
                @update:modelValue="(position: number | null) => setPosition(item, position)" />
            <span v-else class="tabular-nums">{{ item.plan_position ?? "-" }}</span>
        </template>

        <template #cell(reference)="{ item }">
            <Link :href="jobOrderRoute(item)" class="font-medium text-gray-700 hover:underline">{{ item.reference }}</Link>
            <div class="text-xs text-gray-500">{{ item.state_label }}</div>
        </template>

        <template #cell(artefact_code)="{ item }">
            <div class="font-medium">{{ item.artefact_code }}</div>
            <div class="text-xs text-gray-500">{{ item.artefact_name }}</div>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.quantity) }}</span>
        </template>

        <template #cell(steps)="{ item }">
            <div class="flex flex-col gap-1">
                <div v-for="step in (item as PlanItem).steps" :key="step.id" class="flex items-center gap-2 text-xs">
                    <FontAwesomeIcon
                        :icon="step.state === 'done' ? 'fal fa-check' : step.state === 'in_progress' ? 'fal fa-spinner' : 'fal fa-circle'"
                        :class="step.state === 'done' ? 'text-green-600' : step.state === 'in_progress' ? 'text-amber-500' : 'text-gray-300'"
                        fixed-width
                        aria-hidden="true" />
                    <span class="w-32 truncate" :title="step.name">{{ step.name }}</span>
                    <span class="w-20 text-right tabular-nums text-gray-500" :title="mixBatchesLabel(step.quantity_required, step.mix_batch_kg) ?? undefined">{{ locale.number(step.quantity_made) }} / {{ locale.number(step.quantity_required) }}<template v-if="step.unit"> {{ step.unit }}</template></span>
                    <Select
                        v-if="can_edit && step.state !== 'done'"
                        :modelValue="step.employee_id"
                        :options="departmentFirst(step.only_for.length ? operators.filter((operator) => step.only_for.includes(operator.id)) : operators, (item as PlanItem).artefact_department_id)"
                        optionLabel="name"
                        optionValue="id"
                        filter
                        showClear
                        :placeholder="step.operator ?? ctrans('Nobody')"
                        class="w-44"
                        :class="{ '!border-amber-400': !step.operator }"
                        :pt="selectPt"
                        :aria-label="ctrans('Operator of :step', { step: step.name })"
                        @update:modelValue="(employeeId: number | null) => assign(step, employeeId)" />
                    <span v-else class="w-44 truncate" :class="step.operator ? 'text-gray-700' : 'text-gray-400'">{{ step.operator ?? ctrans("Nobody") }}</span>
                    <span v-if="step.working_on_by.length" class="text-amber-700">{{ ctrans("working now: :names", { names: step.working_on_by.join(", ") }) }}</span>
                </div>
            </div>
        </template>

        <template #cell(planned_for)="{ item }">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span :class="item.is_carried_over ? 'text-amber-700' : 'text-gray-700'">{{ useFormatTime(item.planned_for) }}</span>
                <template v-if="can_edit">
                    <Button v-if="item.is_carried_over && date >= today" type="tertiary" size="xs" :label="ctrans('Confirm for :date', { date: useFormatTime(date) })" @click="updateJobOrder(item, { planned_for: date })">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" size="xs" :label="ctrans('Take off the plan')" @click="updateJobOrder(item, { planned_for: null })">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </template>
            </div>
        </template>
    </Table>
</template>
