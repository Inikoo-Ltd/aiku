<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Tag from "@/Components/Tag.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import Select from "primevue/select"
import SelectButton from "primevue/selectbutton"
import DatePicker from "primevue/datepicker"
import ToggleSwitch from "primevue/toggleswitch"
import Message from 'primevue/message';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faBuilding, faUser } from "@fal";

library.add(faPlus, faTrash, faEdit, faCheck, faTimes, faTachometerAlt, faList, faLayerGroup, faBuilding, faUser)

const props = defineProps<{
    data: Record<string, any>
    tab?: string
}>()

const isDetailModalOpen = ref(false)
const isEditModalOpen = ref(false)
const isCreateModalOpen = ref(false)
const isHybridSetupModalOpen = ref(false)
const selectedPolicy = ref<Record<string, any> | null>(null)
const isSubmitting = ref(false)
const isSubmittingRule = ref(false)
const isUpdatingRuleMode = ref<number[]>([])
const isCreating = ref(false)
const createError = ref("")
const createFieldErrors = ref<Record<string, string>>({})
const hybridRuleError = ref("")
const editRuleError = ref("")

const editForm = ref({
    mode: "onsite",
    is_active: true,
    start_at: null as Date | null,
    end_at: null as Date | null,
    reason: "",
})

const newRule = ref({
    day_of_week: "",
    mode_override: "onsite",
    start_at: "",
    end_at: "",
})

const createForm = ref({
    scope_type: "employee",
    scope_id: null as number | null,
    mode: "onsite",
    is_active: true,
    start_at: null as Date | null,
    end_at: null as Date | null,
    reason: "",
})

const createHybridDraftRules = ref<Array<{
    day_of_week: number | null
    mode_override: string
    is_active: boolean
}>>([])

const hybridRuleForm = ref({
    day_of_week: null as number | null,
    mode_override: "onsite",
    is_active: true,
})

const scopeTypeOptions = [
    { value: "organisation", label: "Organisation" },
    { value: "employee", label: "Employee" },
]

const policyModeOptions = [
    { value: "onsite", label: "Onsite" },
    { value: "remote", label: "Remote" },
    { value: "hybrid", label: "Hybrid" },
]

const dayOfWeekOptions = [
    { value: 1, label: "Monday" },
    { value: 2, label: "Tuesday" },
    { value: 3, label: "Wednesday" },
    { value: 4, label: "Thursday" },
    { value: 5, label: "Friday" },
    { value: 6, label: "Saturday" },
    { value: 7, label: "Sunday" },
]

const modeOverrideOptions = [
    { value: "onsite", label: "Onsite" },
    { value: "remote", label: "Remote" },
]

const ruleModeToggleOptions = [
    { value: "onsite", label: "Onsite", icon: "fal fa-building" },
    { value: "remote", label: "Remote", icon: "fal fa-user" },
]

const modeLabel = (mode?: string | null): string => {
    if (!mode) {
        return "-"
    }

    if (mode === "onsite") {
        return "Onsite"
    }

    if (mode === "remote") {
        return "Remote"
    }

    if (mode === "hybrid") {
        return "Hybrid"
    }

    return mode
}

const scopeLabel = (scopeType?: string | null, scopeId?: number | null): string => {
    if (!scopeType) {
        return "-"
    }

    const label = scopeType.charAt(0).toUpperCase() + scopeType.slice(1)
    if (!scopeId) {
        return label
    }

    return `${label} #${scopeId}`
}

const modeTagClass = (mode?: string | null): string => {
    if (mode === "onsite") {
        return "bg-green-100 text-green-700"
    }

    if (mode === "remote") {
        return "bg-sky-100 text-sky-700"
    }

    if (mode === "hybrid") {
        return "bg-violet-100 text-violet-700"
    }

    return "bg-gray-100 text-gray-600"
}

const dateLabel = (date?: string | null): string => {
    return useFormatTime(date || undefined, { formatTime: "mdy" })
}

const toDateOrNull = (value?: string | null): Date | null => {
    if (!value) {
        return null
    }

    const parsed = new Date(value)
    return Number.isNaN(parsed.getTime()) ? null : parsed
}

const normalizeDateForApi = (value: Date | string | null | undefined): string | null => {
    if (!value) {
        return null
    }

    if (value instanceof Date) {
        return value.toISOString()
    }

    return value
}

const rules = computed(() => selectedPolicy.value?.rules ?? [])
const sortedRules = computed(() =>
    [...rules.value].sort((a: any, b: any) => (Number(a?.day_of_week) || 99) - (Number(b?.day_of_week) || 99))
)
const employeeOptions = computed(() => (props.data as any)?.employee_options ?? (props.data as any)?.meta?.employee_options ?? [])
const organisationOptions = computed(() => (props.data as any)?.organisation_options ?? (props.data as any)?.meta?.organisation_options ?? [])
const canEdit = computed<boolean>(() => {
    if (!props.data) {
        return false
    }

    if ("can_edit_clocking_policies" in props.data) {
        return !!props.data.can_edit_clocking_policies
    }

    if ("meta" in props.data && props.data.meta && "can_edit_clocking_policies" in props.data.meta) {
        return !!props.data.meta.can_edit_clocking_policies
    }

    return false
})

const showAddButton = computed<boolean>(() => {
    if (!canEdit.value) {
        return false
    }

    if (typeof window === "undefined") {
        return props.tab === "clocking_policies"
    }

    const queryTab = new URLSearchParams(window.location.search).get("tab")
    return queryTab === "clocking_policies"
})

const organisationId = computed<number | null>(() => {
    const currentOrganisationId = (props.data as any)?.current_organisation_id ?? (props.data as any)?.meta?.current_organisation_id
    if (currentOrganisationId) {
        return Number(currentOrganisationId)
    }

    const firstRow = (props.data as any)?.data?.[0]
    if (firstRow?.organisation_id) {
        return Number(firstRow.organisation_id)
    }

    return null
})

const scopeIdOptions = computed(() => {
    if (createForm.value.scope_type === "organisation") {
        return organisationOptions.value
    }

    return employeeOptions.value
})

const dayLabel = (day?: number | null): string => {
    const names = ["-", trans("Monday"), trans("Tuesday"), trans("Wednesday"), trans("Thursday"), trans("Friday"), trans("Saturday"), trans("Sunday")]
    if (!day) {
        return "-"
    }

    return names[day] ?? `${day}`
}

const availableDayOfWeekOptions = computed(() => {
    const selectedDays = new Set(
        createHybridDraftRules.value
            .map((rule) => rule.day_of_week)
            .filter((day): day is number => day !== null)
    )

    return dayOfWeekOptions.filter((option) => !selectedDays.has(option.value))
})

const availableEditRuleDayOfWeekOptions = computed(() => {
    const selectedDay = newRule.value.day_of_week ? Number(newRule.value.day_of_week) : null
    const selectedPolicyDays = new Set(
        rules.value
            .map((rule: any) => rule.day_of_week)
            .filter((day: number | null) => day !== null)
    )

    return dayOfWeekOptions.filter((option) => !selectedPolicyDays.has(option.value) || option.value === selectedDay)
})

const sortedCreateHybridDraftRules = computed(() =>
    [...createHybridDraftRules.value].sort((a, b) => (a.day_of_week ?? 99) - (b.day_of_week ?? 99))
)

const openDetailModal = (policy: Record<string, any>): void => {
    selectedPolicy.value = policy
    isDetailModalOpen.value = true
}

const openCreateModal = (): void => {
    createError.value = ""
    createFieldErrors.value = {}
    hybridRuleError.value = ""
    createForm.value = {
        scope_type: "employee",
        scope_id: null,
        mode: "onsite",
        is_active: true,
        start_at: null,
        end_at: null,
        reason: "",
    }
    createHybridDraftRules.value = []
    hybridRuleForm.value = {
        day_of_week: null,
        mode_override: "onsite",
        is_active: true,
    }
    isCreateModalOpen.value = true
}

const closeCreateModal = (): void => {
    isCreateModalOpen.value = false
    isHybridSetupModalOpen.value = false
    createFieldErrors.value = {}
}

const closeDetailModal = (): void => {
    isDetailModalOpen.value = false
    selectedPolicy.value = null
}

const openEditModal = (policy: Record<string, any>): void => {
    selectedPolicy.value = policy
    editForm.value = {
        mode: policy.mode ?? "onsite",
        is_active: !!policy.is_active,
        start_at: toDateOrNull(policy.start_at),
        end_at: toDateOrNull(policy.end_at),
        reason: policy.reason ?? "",
    }
    newRule.value = {
        day_of_week: "",
        mode_override: "onsite",
        start_at: "",
        end_at: "",
    }
    editRuleError.value = ""
    isEditModalOpen.value = true
}

const closeEditModal = (): void => {
    isEditModalOpen.value = false
    selectedPolicy.value = null
    editRuleError.value = ""
}

const savePolicy = (): void => {
    if (!selectedPolicy.value?.id) {
        return
    }

    isSubmitting.value = true

    router.patch(
        route("grp.models.clocking-machine-coordinate-policy.update", selectedPolicy.value.id),
        {
            mode: editForm.value.mode,
            is_active: editForm.value.is_active,
            start_at: normalizeDateForApi(editForm.value.start_at),
            end_at: normalizeDateForApi(editForm.value.end_at),
            reason: editForm.value.reason || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: [props.tab || "clocking_policies"] })
                closeEditModal()
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        }
    )
}

const deletePolicy = (policy: Record<string, any>): void => {
    if (!policy?.id) {
        return
    }

    router.delete(route("grp.models.clocking-machine-coordinate-policy.delete", policy.id), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: [props.tab || "clocking_policies"] })
        },
    })
}

const syncSelectedPolicyFromResource = (policyId: number): void => {
    const resourceRows = Array.isArray((props.data as any)?.data) ? (props.data as any).data : []
    const latestPolicy = resourceRows.find((row: any) => Number(row.id) === Number(policyId))
    if (latestPolicy) {
        selectedPolicy.value = latestPolicy
    }
}

const addRule = (): void => {
    if (!selectedPolicy.value?.id) {
        return
    }

    if (!newRule.value.day_of_week) {
        editRuleError.value = trans("Day is required")
        return
    }

    editRuleError.value = ""
    isSubmittingRule.value = true

    router.post(
        route("grp.models.clocking-machine-coordinate-policy.rule.store", selectedPolicy.value.id),
        {
            day_of_week: newRule.value.day_of_week ? Number(newRule.value.day_of_week) : null,
            mode_override: newRule.value.mode_override,
            start_at: newRule.value.start_at || null,
            end_at: newRule.value.end_at || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                const currentPolicyId = Number(selectedPolicy.value?.id)
                newRule.value.day_of_week = ""
                newRule.value.mode_override = "onsite"

                router.reload({
                    only: [props.tab || "clocking_policies"],
                    onSuccess: () => {
                        syncSelectedPolicyFromResource(currentPolicyId)
                    },
                })
            },
            onFinish: () => {
                isSubmittingRule.value = false
            },
        }
    )
}

const deleteRule = (ruleId: number): void => {
    router.delete(route("grp.models.clocking-machine-coordinate-policy-rule.delete", ruleId), {
        preserveScroll: true,
        onSuccess: () => {
            const currentPolicyId = Number(selectedPolicy.value?.id)
            router.reload({ only: [props.tab || "clocking_policies"], onSuccess: () => syncSelectedPolicyFromResource(currentPolicyId) })
        },
    })
}

const updateRuleMode = (rule: Record<string, any>, nextMode: string): void => {
    if (!rule?.id) {
        return
    }

    if (rule.mode_override === nextMode) {
        return
    }

    isUpdatingRuleMode.value = [...isUpdatingRuleMode.value, Number(rule.id)]

    router.patch(
        route("grp.models.clocking-machine-coordinate-policy-rule.update", rule.id),
        {
            mode_override: nextMode,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (!selectedPolicy.value) {
                    return
                }

                const currentRules = Array.isArray(selectedPolicy.value.rules) ? selectedPolicy.value.rules : []
                selectedPolicy.value = {
                    ...selectedPolicy.value,
                    rules: currentRules.map((item: any) =>
                        Number(item.id) === Number(rule.id) ? { ...item, mode_override: nextMode } : item
                    ),
                }
            },
            onFinish: () => {
                isUpdatingRuleMode.value = isUpdatingRuleMode.value.filter((id) => id !== Number(rule.id))
            },
        }
    )
}

const openHybridSetupModal = (): void => {
    hybridRuleError.value = ""
    isHybridSetupModalOpen.value = true
}

const closeHybridSetupModal = (): void => {
    isHybridSetupModalOpen.value = false
}

const addHybridDraftRule = (): void => {
    if (!hybridRuleForm.value.day_of_week) {
        hybridRuleError.value = trans("Day is required.")
        return
    }

    if (createHybridDraftRules.value.some((rule) => rule.day_of_week === hybridRuleForm.value.day_of_week)) {
        hybridRuleError.value = trans("Day already added.")
        return
    }

    createHybridDraftRules.value.push({
        day_of_week: hybridRuleForm.value.day_of_week,
        mode_override: hybridRuleForm.value.mode_override,
        is_active: hybridRuleForm.value.is_active,
    })
    createHybridDraftRules.value.sort((a, b) => (a.day_of_week ?? 99) - (b.day_of_week ?? 99))

    hybridRuleForm.value = {
        day_of_week: null,
        mode_override: "onsite",
        is_active: true,
    }
    hybridRuleError.value = ""
}

const removeHybridDraftRule = (dayOfWeek: number | null): void => {
    createHybridDraftRules.value = createHybridDraftRules.value.filter((rule) => rule.day_of_week !== dayOfWeek)
}

const finishHybridSetup = (): void => {
    if (!createHybridDraftRules.value.length) {
        hybridRuleError.value = trans("Please add at least one rule.")
        return
    }

    isHybridSetupModalOpen.value = false
}

const resetCreateErrors = (): void => {
    createError.value = ""
    createFieldErrors.value = {}
}

const createPolicy = (): void => {
    resetCreateErrors()

    if (!organisationId.value) {
        createError.value = trans("Organisation id is not available in current payload.")
        return
    }

    let hasError = false

    if (!createForm.value.scope_type) {
        createFieldErrors.value.scope_type = trans("Scope type is required.")
        hasError = true
    }

    if (!createForm.value.scope_id) {
        createFieldErrors.value.scope_id = trans("Scope is required.")
        hasError = true
    }

    if (!createForm.value.mode) {
        createFieldErrors.value.mode = trans("Mode is required.")
        hasError = true
    }

    if (createForm.value.mode === "hybrid" && createHybridDraftRules.value.length === 0) {
        createFieldErrors.value.hybrid_rules = trans("Please setup hybrid rules before saving.")
        hasError = true
    }

    if (hasError) {
        return
    }

    isCreating.value = true

    router.post(
        route("grp.models.clocking-machine-coordinate-policy.store"),
        {
            organisation_id: organisationId.value,
            scope_type: createForm.value.scope_type,
            scope_id: createForm.value.scope_id,
            mode: createForm.value.mode,
            is_active: createForm.value.is_active,
            start_at: normalizeDateForApi(createForm.value.start_at),
            end_at: normalizeDateForApi(createForm.value.end_at),
            reason: createForm.value.reason || null,
            rules: createForm.value.mode === "hybrid" ? sortedCreateHybridDraftRules.value : [],
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal()
                router.reload({ only: [props.tab || "clocking_policies"] })
            },
            onError: (errors: Record<string, any>) => {
                createFieldErrors.value = {
                    scope_type: errors.scope_type ? String(errors.scope_type) : "",
                    scope_id: errors.scope_id ? String(errors.scope_id) : "",
                    mode: errors.mode ? String(errors.mode) : "",
                    hybrid_rules: errors.rules ? String(errors.rules) : "",
                }
                createError.value = errors.message ? String(errors.message) : ""
            },
            onFinish: () => {
                isCreating.value = false
            },
        }
    )
}
</script>

<template>
    <div>
        <div v-if="showAddButton" class="mb-3 flex justify-end pr-4 pt-4">
            <Button
                type="create"
                :label="trans('Add Clocking Policy')"
                icon="fal fa-plus"
                @click="openCreateModal"
            />
        </div>

        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(scope_type)="{ item: policy }">
                {{ scopeLabel(policy.scope_type) }}
            </template>

            <template #cell(mode)="{ item: policy }">
                <Tag
                    :label="modeLabel(policy.mode)"
                    :class="modeTagClass(policy.mode)"
                />
            </template>

            <template #cell(is_active)="{ item: policy }">
                <Tag
                    :label="policy.is_active ? 'Active' : 'Inactive'"
                    :class="policy.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                />
            </template>

            <template #cell(start_at)="{ item: policy }">
                {{ dateLabel(policy.start_at) }}
            </template>

            <template #cell(end_at)="{ item: policy }">
                {{ dateLabel(policy.end_at) }}
            </template>

            <template #cell(actions)="{ item: policy }">
                <div class="flex items-center gap-2">
                    <Button
                        type="secondary"
                        size="xs"
                        :icon="faList"
                        :label="trans('Detail')"
                        @click="openDetailModal(policy)"
                    />
                    <Button
                        v-if="canEdit"
                        type="positive"
                        size="xs"
                        :icon="faEdit"
                        :label="trans('Edit')"
                        @click="openEditModal(policy)"
                    />
                    <Button
                        v-if="canEdit"
                        type="negative"
                        size="xs"
                        :icon="faTrash"
                        :label="trans('Delete')"
                        @click="deletePolicy(policy)"
                    />
                </div>
            </template>
        </Table>

        <Modal :isOpen="isDetailModalOpen" :closeButton="true" @onClose="closeDetailModal" width="w-full max-w-3xl">
            <div class="space-y-4">
                <div class="text-lg font-semibold">{{ trans("Clocking Policy Detail") }}</div>
                <div v-if="selectedPolicy" class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded border border-gray-200 bg-white px-3 py-2">
                        <div class="text-xs text-gray-500">{{ trans("Scope") }}</div>
                        <div>{{ scopeLabel(selectedPolicy.scope_type) }}</div>
                    </div>
                    <div class="rounded border border-gray-200 bg-white px-3 py-2">
                        <div class="text-xs text-gray-500">{{ trans("Name") }}</div>
                        <div>{{ selectedPolicy.scope_name ?? "-" }}</div>
                    </div>
                    <div class="rounded border border-gray-200 bg-white px-3 py-2">
                        <div class="text-xs text-gray-500">{{ trans("Mode") }}</div>
                        <div>{{ modeLabel(selectedPolicy.mode) }}</div>
                    </div>
                    <div class="rounded border border-gray-200 bg-white px-3 py-2">
                        <div class="text-xs text-gray-500">{{ trans("Start At") }}</div>
                        <div>{{ dateLabel(selectedPolicy.start_at) }}</div>
                    </div>
                    <div class="rounded border border-gray-200 bg-white px-3 py-2">
                        <div class="text-xs text-gray-500">{{ trans("End At") }}</div>
                        <div>{{ dateLabel(selectedPolicy.end_at) }}</div>
                    </div>
                    <div class="rounded border border-gray-200 bg-white px-3 py-2">
                        <div class="text-xs text-gray-500">{{ trans("Reason") }}</div>
                        <div>{{ selectedPolicy.reason ?? "-" }}</div>
                    </div>
                </div>
                <div v-if="selectedPolicy?.mode === 'hybrid'" class="space-y-2 rounded border border-gray-200 bg-gray-50 p-3">
                    <div class="text-sm">{{ trans("Rules") }}</div>
                    <div v-if="!sortedRules.length" class="text-sm text-gray-500">{{ trans("No rules") }}</div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="rule in sortedRules"
                            :key="rule.id"
                            class="flex flex-wrap items-center gap-3 rounded border border-gray-200 bg-white px-3 py-2 text-sm"
                        >
                            <span>{{ dayLabel(rule.day_of_week) }}</span>
                            <Tag :label="modeLabel(rule.mode_override)" :class="modeTagClass(rule.mode_override)" />
                            <FontAwesomeIcon
                                :icon="rule.is_active ? 'fal fa-check' : 'fal fa-times'"
                                :class="rule.is_active ? 'text-green-600' : 'text-red-600'"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :isOpen="isEditModalOpen" :isClosableInBackground="false" :closeButton="true" @onClose="closeEditModal" width="w-full max-w-3xl">
            <div class="space-y-4">
                <div class="text-lg font-semibold">{{ trans("Edit Clocking Policy") }}</div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Mode") }}</label>
                        <Select
                            v-model="editForm.mode"
                            :options="policyModeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                        />
                    </div>
                    <div class="flex items-end">
                        <div class="flex items-center gap-2 text-sm">
                            <ToggleSwitch v-model="editForm.is_active" />
                            <span>{{ trans("Active") }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Start At") }}</label>
                        <DatePicker
                            v-model="editForm.start_at"
                            class="w-full text-sm"
                            fluid
                            showIcon
                            dateFormat="yy-mm-dd"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("End At") }}</label>
                        <DatePicker
                            v-model="editForm.end_at"
                            class="w-full text-sm"
                            fluid
                            showIcon
                            dateFormat="yy-mm-dd"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">{{ trans("Reason") }}</label>
                        <textarea v-model="editForm.reason" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div v-if="editForm.mode === 'hybrid'" class="space-y-3 rounded border border-gray-200 bg-gray-50 p-3">
                    <div>{{ trans("Hybrid Rules Setup") }}</div>
                    <div class="grid grid-cols-2 gap-2">
                        <Select
                            v-model="newRule.day_of_week"
                            :options="availableEditRuleDayOfWeekOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                            :placeholder="trans('Day')"
                        />
                        <Select
                            v-model="newRule.mode_override"
                            :options="modeOverrideOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                        />
                    </div>
                    <Message v-if="editRuleError" severity="error" variant="simple" class="text-xs font-normal">{{
                        editRuleError }}</Message>
                    <div>
                        <Button
                            type="secondary"
                            size="xs"
                            :icon="faPlus"
                            :label="isSubmittingRule ? trans('Saving...') : trans('Add Rule')"
                            :disabled="isSubmittingRule"
                            @click="addRule"
                        />
                    </div>
                    <div v-if="sortedRules.length" class="space-y-2">
                        <div v-for="rule in sortedRules" :key="rule.id" class="flex items-center justify-between rounded border border-gray-200 bg-white px-3 py-2 text-sm">
                            <div class="flex items-center gap-3">
                                <span>{{ dayLabel(rule.day_of_week) }}</span>
                                <SelectButton
                                    :modelValue="rule.mode_override"
                                    :options="ruleModeToggleOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    dataKey="value"
                                    size="small"
                                    :disabled="isUpdatingRuleMode.includes(Number(rule.id))"
                                    @update:modelValue="(value) => updateRuleMode(rule, String(value))"
                                >
                                    <template #option="slotProps">
                                        <div class="flex items-center gap-2">
                                            <FontAwesomeIcon :icon="slotProps.option.icon" />
                                            <span>{{ slotProps.option.label }}</span>
                                        </div>
                                    </template>
                                </SelectButton>
                                <div class="flex items-center gap-2">
                                    <FontAwesomeIcon :icon="rule.is_active ? 'fal fa-check' : 'fal fa-times'"
                                        :class="rule.is_active ? 'text-green-600' : 'text-red-600'" />
                                </div>
                            </div>
                            <Button type="negative" size="xs" :icon="faTrash" :label="trans('Delete')" @click="deleteRule(rule.id)" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="secondary" :label="trans('Cancel')" @click="closeEditModal" />
                    <Button
                        type="primary"
                        :label="isSubmitting ? trans('Saving...') : trans('Save')"
                        :disabled="isSubmitting"
                        @click="savePolicy"
                    />
                </div>
            </div>
        </Modal>

        <Modal :isOpen="isCreateModalOpen" :isClosableInBackground="false" :closeButton="true" @onClose="closeCreateModal" width="w-full max-w-2xl">
            <div class="space-y-4">
                <div class="text-lg font-semibold">{{ trans("Add Clocking Policy") }}</div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Scope Type") }}</label>
                        <Select
                            v-model="createForm.scope_type"
                            :options="scopeTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                        />
                        <Message v-if="createFieldErrors.scope_type" severity="error" variant="simple" class="mt-1 text-xs font-normal">{{ createFieldErrors.scope_type }}</Message>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Scope Name") }}</label>
                        <Select
                            v-model="createForm.scope_id"
                            :options="scopeIdOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                            :placeholder="trans('Select scope')"
                        />
                        <Message v-if="createFieldErrors.scope_id" severity="error" variant="simple" class="mt-1 text-xs font-normal">{{ createFieldErrors.scope_id }}</Message>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Mode") }}</label>
                        <Select
                            v-model="createForm.mode"
                            :options="policyModeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                        />
                        <Message v-if="createFieldErrors.mode" severity="error" variant="simple" class="mt-1 text-xs font-normal">{{ createFieldErrors.mode }}</Message>
                    </div>
                    <div class="flex items-end">
                        <div class="flex items-center gap-2 text-sm">
                            <ToggleSwitch v-model="createForm.is_active" />
                            <span>{{ trans("Active") }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Start At") }}</label>
                        <DatePicker
                            v-model="createForm.start_at"
                            class="w-full text-sm"
                            fluid
                            showIcon
                            dateFormat="yy-mm-dd"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("End At") }}</label>
                        <DatePicker
                            v-model="createForm.end_at"
                            class="w-full text-sm"
                            fluid
                            showIcon
                            dateFormat="yy-mm-dd"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">{{ trans("Reason") }}</label>
                        <textarea v-model="createForm.reason" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div v-if="createForm.mode === 'hybrid'" class="col-span-2">
                        <Button
                            type="positive"
                            full
                            :label="trans('Setup Hybrid')"
                            @click="openHybridSetupModal"
                        />
                    </div>
                    <div v-if="createForm.mode === 'hybrid'" class="col-span-2 rounded border border-gray-200 p-3">
                        <div class="mb-2 text-sm">{{ trans("Hybrid Rules Preview") }}</div>
                        <div v-if="!createHybridDraftRules.length" class="text-sm text-gray-500">{{ trans("No rules setup yet.") }}</div>
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="rule in sortedCreateHybridDraftRules"
                                :key="`${rule.day_of_week}-${rule.mode_override}`"
                                class="flex flex-wrap items-center gap-3 rounded border border-green-200 bg-green-100 px-3 py-2 text-sm"
                            >
                                <div> {{ dayLabel(rule.day_of_week) }}</div>
                                <div class="flex items-center gap-2 ">
                                    <span>{{ modeLabel(rule.mode_override) }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <FontAwesomeIcon
                                        :icon="rule.is_active ? 'fal fa-check' : 'fal fa-times'"
                                        :class="rule.is_active ? 'text-green-600' : 'text-red-600'"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <Message v-if="createFieldErrors.hybrid_rules" severity="error" variant="simple" class="col-span-2 text-xs font-normal">{{ createFieldErrors.hybrid_rules }}</Message>
                </div>
                <div v-if="createError" class="text-sm text-red-600">{{ createError }}</div>
                <div class="flex justify-end gap-2">
                    <Button type="secondary" :label="trans('Cancel')" @click="closeCreateModal" />
                    <Button
                        type="primary"
                        :label="isCreating ? trans('Saving...') : trans('Save')"
                        :disabled="isCreating"
                        @click="createPolicy"
                    />
                </div>
            </div>
        </Modal>

        <Modal :isOpen="isHybridSetupModalOpen" :isClosableInBackground="false" :closeButton="true" @onClose="closeHybridSetupModal" width="w-full max-w-2xl">
            <div class="space-y-4">
                <div class="text-lg font-semibold">{{ trans("Setup Hybrid") }}</div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Day of week") }}</label>
                        <Select
                            v-model="hybridRuleForm.day_of_week"
                            :options="availableDayOfWeekOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Mode override") }}</label>
                        <Select
                            v-model="hybridRuleForm.mode_override"
                            :options="modeOverrideOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                        />
                    </div>
                    <div class="flex items-end">
                        <div class="flex items-center gap-2 text-sm">
                            <ToggleSwitch v-model="hybridRuleForm.is_active" />
                            <span>{{ trans("Active") }}</span>
                        </div>
                    </div>
                </div>
                <Message v-if="hybridRuleError" severity="error" variant="simple" class="text-xs font-normal">{{ hybridRuleError }}</Message>
                <div class="flex items-center justify-end gap-2">
                    <Button type="danger" :icon="faTimes" :label="trans('Cancel')" @click="closeHybridSetupModal" />
                    <Button type="secondary" :icon="faPlus" :label="trans('Add Rule')" @click="addHybridDraftRule" />
                    <Button type="positive" :icon="faCheck" :label="trans('Finish Setup')" @click="finishHybridSetup" />
                </div>
                <div v-if="sortedCreateHybridDraftRules.length" class="space-y-2">
                    <div
                        v-for="rule in sortedCreateHybridDraftRules"
                        :key="`${rule.day_of_week}-${rule.mode_override}`"
                        class="flex items-center justify-between rounded border border-gray-200 bg-white px-3 py-2 text-sm"
                    >
                        <div class="flex items-center gap-3">
                            <span>{{ dayLabel(rule.day_of_week) }}</span>
                            <span>{{ modeLabel(rule.mode_override) }}</span>
                            <FontAwesomeIcon
                                :icon="rule.is_active ? 'fal fa-check' : 'fal fa-times'"
                                :class="rule.is_active ? 'text-green-600' : 'text-red-600'"
                            />
                        </div>
                        <Button type="negative" size="xs" :icon="faTrash" :label="trans('Delete')" @click="removeHybridDraftRule(rule.day_of_week)" />
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
