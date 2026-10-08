<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Oct 2026 12:00:00 British Summer Time, Sheffield, UK
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { format, parseISO } from "date-fns"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Textarea from "primevue/textarea"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import WarehouseTeamDashboard from "@/Components/Warehouse/Team/WarehouseTeamDashboard.vue"
import WarehouseTeamClockings from "@/Components/Warehouse/Team/WarehouseTeamClockings.vue"
import type { DashboardData } from "@/Components/Warehouse/Team/WarehouseTeamDashboard.vue"
import type { ClockingsData, ClockingRow } from "@/Components/Warehouse/Team/WarehouseTeamClockings.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserHardHat, faPlus, faTachometerAlt, faClock } from "@fal"

library.add(faUserHardHat, faPlus, faTachometerAlt, faClock)

type Route = { name: string; parameters: Record<string, number> }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    timezone: string
    team_members: { id: number; name: string }[]
    dashboard?: DashboardData
    clockings?: ClockingsData
    store_clocking_route: Route
    update_clocking_route: Route
    delete_clocking_route: Route
    backlog_route: { name: string; parameters: Record<string, string | number> }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const isDialogOpen = ref(false)
const editingClocking = ref<(ClockingRow & { employee_id: number }) | null>(null)
const form = useForm<{ employee_id: number | null; clocked_at: Date | null; notes: string }>({
    employee_id: null,
    clocked_at: null,
    notes: "",
})
const formMember = computed(() => props.team_members.find((member) => member.id === form.employee_id))
const formMemberOnSite = computed(() => {
    const person = props.dashboard?.floor.people.find((p) => p.id === form.employee_id)
    return person ? person.status === "on_site" : null
})

const openAddClocking = (employeeId: number | null = null, day?: string) => {
    editingClocking.value = null
    form.reset()
    form.clearErrors()
    form.employee_id = employeeId
    const at = new Date()
    if (day && day !== format(at, "yyyy-MM-dd")) {
        const target = parseISO(day)
        at.setFullYear(target.getFullYear(), target.getMonth(), target.getDate())
    }
    form.clocked_at = at
    isDialogOpen.value = true
}
const openEditClocking = (clocking: ClockingRow & { employee_id: number }) => {
    editingClocking.value = clocking
    form.clearErrors()
    form.employee_id = clocking.employee_id
    form.clocked_at = new Date(clocking.clocked_at)
    form.notes = clocking.notes ?? ""
    isDialogOpen.value = true
}
const submitClocking = () => {
    if (!form.employee_id || !form.clocked_at) {
        return
    }
    const options = {
        preserveScroll: true,
        only: [currentTab.value as string],
        onSuccess: () => {
            isDialogOpen.value = false
        },
    }
    const transformed = form.transform((data) => ({
        clocked_at: format(data.clocked_at as Date, "yyyy-MM-dd'T'HH:mm:ssXXX"),
        notes: data.notes || null,
    }))
    if (editingClocking.value) {
        transformed.patch(route(props.update_clocking_route.name, { ...props.update_clocking_route.parameters, clocking: editingClocking.value.id }), options)
        return
    }
    transformed.post(route(props.store_clocking_route.name, { ...props.store_clocking_route.parameters, employee: form.employee_id }), options)
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Button type="create" :icon="faPlus" :label="ctrans('Add clocking')" @click="openAddClocking()" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <WarehouseTeamDashboard v-if="currentTab === 'dashboard'" :data="dashboard" :backlogRoute="backlog_route" @add-clocking="openAddClocking" />
    <WarehouseTeamClockings v-else-if="currentTab === 'clockings'" :data="clockings" :deleteRoute="delete_clocking_route" @add-clocking="openAddClocking" @edit-clocking="openEditClocking" />

    <Dialog v-model:visible="isDialogOpen" modal :header="editingClocking ? ctrans('Edit clocking') : ctrans('Add clocking')" :style="{ width: '28rem' }">
        <div class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium">{{ ctrans("Employee") }}</label>
                <Select
                    v-model="form.employee_id"
                    :options="team_members"
                    optionLabel="name"
                    optionValue="id"
                    filter
                    :disabled="!!editingClocking"
                    class="w-full"
                    :placeholder="ctrans('Select an employee')"
                />
                <div v-if="form.errors.employee" class="mt-1 text-xs text-red-600">{{ form.errors.employee }}</div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ editingClocking ? ctrans("Time") : ctrans("Date and time") }}</label>
                <DatePicker v-if="editingClocking" v-model="form.clocked_at" timeOnly hourFormat="24" class="w-full" />
                <DatePicker v-else v-model="form.clocked_at" showTime hourFormat="24" :maxDate="new Date()" dateFormat="yy-mm-dd" class="w-full" />
                <div v-if="form.errors.clocked_at" class="mt-1 text-xs text-red-600">{{ form.errors.clocked_at }}</div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ ctrans("Note") }}</label>
                <Textarea v-model="form.notes" rows="2" class="w-full" :placeholder="ctrans('e.g. forgot to clock in')" />
                <div v-if="form.errors.notes" class="mt-1 text-xs text-red-600">{{ form.errors.notes }}</div>
            </div>
            <p class="text-xs text-gray-500">
                <template v-if="editingClocking">{{ ctrans("The clocking stays on its day; only the time changes.") }}</template>
                <template v-else-if="formMember && formMemberOnSite !== null">{{ formMemberOnSite ? ctrans(":name is clocked in, this clocking clocks them out.", { name: formMember.name }) : ctrans(":name is not clocked in, this clocking clocks them in.", { name: formMember.name }) }}</template>
                <template v-else>{{ ctrans("Each clocking switches the employee between clocked in and clocked out.") }}</template>
            </p>
        </div>
        <template #footer>
            <Button type="tertiary" :label="ctrans('Cancel')" @click="isDialogOpen = false" />
            <Button type="save" :label="editingClocking ? ctrans('Save') : ctrans('Add clocking')" :loading="form.processing" :disabled="!form.employee_id || !form.clocked_at" @click="submitClocking" />
        </template>
    </Dialog>
</template>
