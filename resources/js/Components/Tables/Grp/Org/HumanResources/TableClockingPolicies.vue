<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Tag from "@/Components/Tag.vue"
import Select from "primevue/select"
import ToggleSwitch from "primevue/toggleswitch"

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
const isCreating = ref(false)
const createError = ref("")
const hybridRuleError = ref("")

const editForm = ref({
    mode: "onsite",
    is_active: true,
    start_at: "",
    end_at: "",
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
    start_at: "",
    end_at: "",
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
    { value: 1, label: "Mon" },
    { value: 2, label: "Tue" },
    { value: 3, label: "Wed" },
    { value: 4, label: "Thu" },
    { value: 5, label: "Fri" },
    { value: 6, label: "Sat" },
    { value: 7, label: "Sun" },
]

const modeOverrideOptions = [
    { value: "onsite", label: "Onsite" },
    { value: "remote", label: "Remote" },
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

const rules = computed(() => selectedPolicy.value?.rules ?? [])
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
    const names = ["-", trans("Mon"), trans("Tue"), trans("Wed"), trans("Thu"), trans("Fri"), trans("Sat"), trans("Sun")]
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

const openDetailModal = (policy: Record<string, any>): void => {
    selectedPolicy.value = policy
    isDetailModalOpen.value = true
}

const openCreateModal = (): void => {
    createError.value = ""
    hybridRuleError.value = ""
    createForm.value = {
        scope_type: "employee",
        scope_id: null,
        mode: "onsite",
        is_active: true,
        start_at: "",
        end_at: "",
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
        start_at: policy.start_at ?? "",
        end_at: policy.end_at ?? "",
        reason: policy.reason ?? "",
    }
    newRule.value = {
        day_of_week: "",
        mode_override: "onsite",
        start_at: "",
        end_at: "",
    }
    isEditModalOpen.value = true
}

const closeEditModal = (): void => {
    isEditModalOpen.value = false
    selectedPolicy.value = null
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
            start_at: editForm.value.start_at || null,
            end_at: editForm.value.end_at || null,
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

const addRule = (): void => {
    if (!selectedPolicy.value?.id) {
        return
    }

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
                router.reload({ only: [props.tab || "clocking_policies"] })
                closeEditModal()
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
            router.reload({ only: [props.tab || "clocking_policies"] })
            closeEditModal()
        },
    })
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

    hybridRuleForm.value = {
        day_of_week: null,
        mode_override: "onsite",
        is_active: true,
    }
    hybridRuleError.value = ""
}

const removeHybridDraftRule = (index: number): void => {
    createHybridDraftRules.value.splice(index, 1)
}

const finishHybridSetup = (): void => {
    if (!createHybridDraftRules.value.length) {
        hybridRuleError.value = trans("Please add at least one rule.")
        return
    }

    isHybridSetupModalOpen.value = false
}

const resolveCreatedPolicyId = (payload: any): number | null => {
    if (payload?.id) {
        return Number(payload.id)
    }

    if (payload?.data?.id) {
        return Number(payload.data.id)
    }

    if (payload?.clocking_machine_coordinate_policy?.id) {
        return Number(payload.clocking_machine_coordinate_policy.id)
    }

    return null
}

const createPolicy = async (): Promise<void> => {
    if (!organisationId.value) {
        createError.value = trans("Organisation id is not available in current payload.")
        return
    }

    if (!createForm.value.scope_id) {
        createError.value = trans("Scope is required.")
        return
    }

    if (createForm.value.mode === "hybrid" && createHybridDraftRules.value.length === 0) {
        createError.value = trans("Please setup hybrid rules before saving.")
        return
    }

    isCreating.value = true
    createError.value = ""

    try {
        const response = await axios.post(route("grp.models.clocking-machine-coordinate-policy.store"), {
            organisation_id: organisationId.value,
            scope_type: createForm.value.scope_type,
            scope_id: createForm.value.scope_id,
            mode: createForm.value.mode,
            is_active: createForm.value.is_active,
            start_at: createForm.value.start_at || null,
            end_at: createForm.value.end_at || null,
            reason: createForm.value.reason || null,
        })

        const createdPolicyId = resolveCreatedPolicyId(response?.data)
        if (!createdPolicyId) {
            throw new Error(trans("Failed to resolve created policy id."))
        }

        if (createForm.value.mode === "hybrid") {
            for (const rule of createHybridDraftRules.value) {
                await axios.post(route("grp.models.clocking-machine-coordinate-policy.rule.store", createdPolicyId), {
                    day_of_week: rule.day_of_week,
                    mode_override: rule.mode_override,
                    is_active: rule.is_active,
                })
            }
        }

        closeCreateModal()
        router.reload({ only: [props.tab || "clocking_policies"] })
    } catch (error: any) {
        createError.value = error?.response?.data?.message ?? trans("Failed to create policy.")
    } finally {
        isCreating.value = false
    }
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
                {{ scopeLabel(policy.scope_type, policy.scope_id) }}
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

            <template #cell(actions)="{ item: policy }">
                <div class="flex items-center gap-2">
                    <Button
                        type="secondary"
                        size="xs"
                        :label="trans('Detail')"
                        @click="openDetailModal(policy)"
                    />
                    <Button
                        v-if="canEdit"
                        type="secondary"
                        size="xs"
                        :label="trans('Edit')"
                        @click="openEditModal(policy)"
                    />
                    <Button
                        v-if="canEdit"
                        type="negative"
                        size="xs"
                        :label="trans('Delete')"
                        @click="deletePolicy(policy)"
                    />
                </div>
            </template>
        </Table>

        <Modal :isOpen="isDetailModalOpen" @onClose="closeDetailModal" width="w-full max-w-3xl">
            <div class="space-y-4">
                <div class="text-lg font-semibold">{{ trans("Clocking Policy Detail") }}</div>
                <div v-if="selectedPolicy" class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="font-medium">{{ trans("Scope") }}:</span> {{ scopeLabel(selectedPolicy.scope_type, selectedPolicy.scope_id) }}</div>
                    <div><span class="font-medium">{{ trans("Mode") }}:</span> {{ modeLabel(selectedPolicy.mode) }}</div>
                    <div><span class="font-medium">{{ trans("Start At") }}:</span> {{ selectedPolicy.start_at ?? "-" }}</div>
                    <div><span class="font-medium">{{ trans("End At") }}:</span> {{ selectedPolicy.end_at ?? "-" }}</div>
                    <div class="col-span-2"><span class="font-medium">{{ trans("Reason") }}:</span> {{ selectedPolicy.reason ?? "-" }}</div>
                </div>
                <div>
                    <div class="mb-2 text-sm font-medium">{{ trans("Rules") }}</div>
                    <div v-if="!rules.length" class="text-sm text-gray-500">{{ trans("No rules") }}</div>
                    <div v-else class="space-y-2">
                        <div v-for="rule in rules" :key="rule.id" class="rounded border border-gray-200 p-2 text-sm">
                            <div>{{ trans("Day") }}: {{ dayLabel(rule.day_of_week) }}</div>
                            <div>{{ trans("Mode") }}: {{ modeLabel(rule.mode_override) }}</div>
                            <div>{{ trans("Start At") }}: {{ rule.start_at ?? "-" }}</div>
                            <div>{{ trans("End At") }}: {{ rule.end_at ?? "-" }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :isOpen="isEditModalOpen" :isClosableInBackground="false" @onClose="closeEditModal" width="w-full max-w-3xl">
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
                        <input v-model="editForm.start_at" type="datetime-local" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("End At") }}</label>
                        <input v-model="editForm.end_at" type="datetime-local" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">{{ trans("Reason") }}</label>
                        <textarea v-model="editForm.reason" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div v-if="editForm.mode === 'hybrid'" class="space-y-3 rounded border border-violet-200 bg-violet-50 p-3">
                    <div class="font-medium">{{ trans("Hybrid Rules Setup") }}</div>
                    <div class="grid grid-cols-4 gap-2">
                        <select v-model="newRule.day_of_week" class="rounded border border-gray-300 px-2 py-2 text-sm">
                            <option value="">{{ trans("Day") }}</option>
                            <option :value="1">Mon</option>
                            <option :value="2">Tue</option>
                            <option :value="3">Wed</option>
                            <option :value="4">Thu</option>
                            <option :value="5">Fri</option>
                            <option :value="6">Sat</option>
                            <option :value="7">Sun</option>
                        </select>
                        <select v-model="newRule.mode_override" class="rounded border border-gray-300 px-2 py-2 text-sm">
                            <option value="onsite">Onsite</option>
                            <option value="remote">Remote</option>
                        </select>
                        <input v-model="newRule.start_at" type="datetime-local" class="rounded border border-gray-300 px-2 py-2 text-sm" />
                        <input v-model="newRule.end_at" type="datetime-local" class="rounded border border-gray-300 px-2 py-2 text-sm" />
                    </div>
                    <div>
                        <Button
                            type="secondary"
                            size="xs"
                            :label="isSubmittingRule ? trans('Saving...') : trans('Add Rule')"
                            :disabled="isSubmittingRule"
                            @click="addRule"
                        />
                    </div>
                    <div v-if="rules.length" class="space-y-2">
                        <div v-for="rule in rules" :key="rule.id" class="flex items-center justify-between rounded border border-gray-200 bg-white px-3 py-2 text-sm">
                            <div class="flex items-center gap-3">
                                <span>{{ dayLabel(rule.day_of_week) }}</span>
                                <span>{{ modeLabel(rule.mode_override) }}</span>
                                <span>{{ rule.start_at ?? "-" }}</span>
                                <span>{{ rule.end_at ?? "-" }}</span>
                            </div>
                            <Button type="negative" size="xs" :label="trans('Delete')" @click="deleteRule(rule.id)" />
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

        <Modal :isOpen="isCreateModalOpen" :isClosableInBackground="false" @onClose="closeCreateModal" width="w-full max-w-2xl">
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
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Scope ID") }}</label>
                        <Select
                            v-model="createForm.scope_id"
                            :options="scopeIdOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full text-sm"
                            :placeholder="trans('Select scope')"
                        />
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
                    </div>
                    <div class="flex items-end">
                        <div class="flex items-center gap-2 text-sm">
                            <ToggleSwitch v-model="createForm.is_active" />
                            <span>{{ trans("Active") }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("Start At") }}</label>
                        <input v-model="createForm.start_at" type="datetime-local" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ trans("End At") }}</label>
                        <input v-model="createForm.end_at" type="datetime-local" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
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
                    <div v-if="createForm.mode === 'hybrid'" class="col-span-2 rounded border border-violet-200 bg-violet-50 p-3">
                        <div class="mb-2 text-sm font-medium">{{ trans("Hybrid Rules Preview") }}</div>
                        <div v-if="!createHybridDraftRules.length" class="text-sm text-gray-500">{{ trans("No rules setup yet.") }}</div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="(rule, index) in createHybridDraftRules"
                                :key="`${rule.day_of_week}-${index}`"
                                class="rounded border border-gray-200 bg-white px-3 py-2 text-sm"
                            >
                                <div>{{ trans("Day") }}: {{ dayLabel(rule.day_of_week) }}</div>
                                <div>{{ trans("Mode") }}: {{ modeLabel(rule.mode_override) }}</div>
                                <div>{{ trans("Active") }}: {{ rule.is_active ? trans("Yes") : trans("No") }}</div>
                            </div>
                        </div>
                    </div>
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

        <Modal :isOpen="isHybridSetupModalOpen" :isClosableInBackground="false" @onClose="closeHybridSetupModal" width="w-full max-w-2xl">
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
                    <div></div>
                    <div></div>
                    <div></div>
                </div>
                <div v-if="hybridRuleError" class="text-sm text-red-600">{{ hybridRuleError }}</div>
                <div class="flex items-center justify-between gap-2">
                    <Button type="secondary" :label="trans('Add Rule')" @click="addHybridDraftRule" />
                    <Button type="positive" :label="trans('Finish Setup')" @click="finishHybridSetup" />
                </div>
                <div v-if="createHybridDraftRules.length" class="space-y-2">
                    <div
                        v-for="(rule, index) in createHybridDraftRules"
                        :key="`${rule.day_of_week}-${rule.mode_override}-${index}`"
                        class="flex items-center justify-between rounded border border-gray-200 bg-white px-3 py-2 text-sm"
                    >
                        <div class="flex items-center gap-3">
                            <span>{{ dayLabel(rule.day_of_week) }}</span>
                            <span>{{ modeLabel(rule.mode_override) }}</span>
                            <span>{{ rule.is_active ? trans("Active") : trans("Inactive") }}</span>
                        </div>
                        <Button type="negative" size="xs" :label="trans('Delete')" @click="removeHybridDraftRule(index)" />
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
