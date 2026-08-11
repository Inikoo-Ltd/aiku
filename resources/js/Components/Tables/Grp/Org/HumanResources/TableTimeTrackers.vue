<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 16 May 2024 17:12:16 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Clocking } from "@/types/clocking"
import Icon from "@/Components/Icon.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Modal from '@/Components/Utils/Modal.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import DatePicker from 'primevue/datepicker'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClock, faDoorClosed, faDoorOpen, faTrash } from "@fal"
import { faEdit, faPlus } from "@fas"
import { format } from 'date-fns'
import axios from 'axios'
import { computed, ref } from 'vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { useFormatTime, useHMAP } from '@/Composables/useFormatTime'

const props = defineProps<{
    data: any
    tab?: string
    storeClockingRoute?: string
}>()

library.add(faClock, faDoorOpen, faDoorClosed, faEdit, faPlus, faTrash)

const isClockOutModalOpen = ref(false)
const isClockInModalOpen = ref(false)
const selectedTimeTracker = ref<any | null>(null)
const clockOutTime = ref<Date | null>(null)
const clockInTime = ref<Date | null>(null)
const isSubmitting = ref(false)
const errorMsg = ref<string | null>(null)

const isAddModalOpen = ref(false)
const newClockedAt = ref<Date | null>(null)
const newNotes = ref<string>('')
const isAddSubmitting = ref(false)
const addErrorMsg = ref<string | null>(null)

const isEditTimeModalOpen = ref(false)
const editTimeRoute = ref<string | null>(null)
const editTimeLabel = ref<string>('')
const editTimeValue = ref<Date | null>(null)
const isEditTimeSubmitting = ref(false)
const editTimeErrorMsg = ref<string | null>(null)

const canEdit = computed<boolean>(() => {
    if (!props.data) {
        return false
    }

    if ('can_edit_time_trackers' in props.data) {
        return !!props.data.can_edit_time_trackers
    }

    if ('meta' in props.data && props.data.meta && 'can_edit_time_trackers' in props.data.meta) {
        return !!props.data.meta.can_edit_time_trackers
    }

    return false
})

function clockingRoute(clocking: Clocking) {
    const routeParams = route().params as any

    switch (route().current()) {
        case 'grp.org.hr.clocking_machines.show':
            return route(
                'grp.org.hr.clocking_machines.show.clockings.show',
                [routeParams['clockingMachine'], clocking.slug])

        case 'grp.org.hr.workplaces.show.clocking_machines.show':
            return route(
                'grp.org.hr.workplaces.show.clocking_machines.show.clockings.show',
                [routeParams['workplace'], routeParams['clockingMachine'], clocking.slug])
        case 'grp.org.hr.workplaces.show.clockings.index':
            return route(
                'grp.org.hr.workplaces.show.clockings.show',
                [clocking.workplace_slug, clocking.slug])
        case 'grp.org.hr.clocking_machines.clockings.index':
            return route(
                'grp.org.hr.clocking_machines.show.clockings.show',
                [clocking.clocking_machine_slug, clocking.slug])
        case 'grp.org.hr.workplaces.show.clocking_machines.show.clockings.index':
            return route(
                'grp.org.hr.workplaces.show.clocking_machines.show.clockings.show',
                [clocking.workplace_slug, clocking.clocking_machine_slug, clocking.slug]
            )
        default:
            return route(
                'grp.org.hr.clockings.show',
                [clocking.slug])
    }

}

const openClockOutModal = (timeTracker: any): void => {
    selectedTimeTracker.value = timeTracker
    clockOutTime.value = timeTracker?.starts_at ? new Date(timeTracker.starts_at) : new Date()
    errorMsg.value = null
    isClockOutModalOpen.value = true
}

const closeClockOutModal = (): void => {
    isClockOutModalOpen.value = false
    selectedTimeTracker.value = null
    clockOutTime.value = null
    errorMsg.value = null
}

const submitClockOut = async (): Promise<void> => {
    if (!selectedTimeTracker.value || !clockOutTime.value || !selectedTimeTracker.value.clock_out_route) {
        return
    }

    isSubmitting.value = true
    errorMsg.value = null

    try {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
        const clockedAtTime = `${format(clockOutTime.value, 'HH:mm')}:00`

        await axios.patch(
            selectedTimeTracker.value.clock_out_route,
            {
                clocked_at_time: clockedAtTime,
                timezone,
            }
        )

        notify({
            title: trans('Success'),
            text: trans('Clock out added successfully.'),
            type: 'success',
        })

        router.reload({
            only: [props.tab || 'time_trackers', 'timesheet']
        })

        closeClockOutModal()
    } catch (e: any) {
        const message = e?.response?.data?.message ?? trans('Failed to add clock out.')
        errorMsg.value = message

        notify({
            title: trans('Failed'),
            text: message,
            type: 'error',
        })
    } finally {
        isSubmitting.value = false
    }
}

const openClockInModal = (timeTracker: any): void => {
    selectedTimeTracker.value = timeTracker
    clockInTime.value = timeTracker?.ends_at ? new Date(timeTracker.ends_at) : new Date()
    errorMsg.value = null
    isClockInModalOpen.value = true
}

const closeClockInModal = (): void => {
    isClockInModalOpen.value = false
    selectedTimeTracker.value = null
    clockInTime.value = null
    errorMsg.value = null
}

const submitClockIn = async (): Promise<void> => {
    if (!selectedTimeTracker.value || !clockInTime.value || !selectedTimeTracker.value.clock_in_route) {
        return
    }

    isSubmitting.value = true
    errorMsg.value = null

    try {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
        const clockedAtTime = `${format(clockInTime.value, 'HH:mm')}:00`

        await axios.patch(
            selectedTimeTracker.value.clock_in_route,
            {
                clocked_at_time: clockedAtTime,
                timezone,
            }
        )

        notify({
            title: trans('Success'),
            text: trans('Clock in added successfully.'),
            type: 'success',
        })

        router.reload({
            only: [props.tab || 'time_trackers', 'timesheet']
        })

        closeClockInModal()
    } catch (e: any) {
        const message = e?.response?.data?.message ?? trans('Failed to add clock in.')
        errorMsg.value = message

        notify({
            title: trans('Failed'),
            text: message,
            type: 'error',
        })
    } finally {
        isSubmitting.value = false
    }
}

const openEditTimeModal = (route: string, label: string, currentValue: string | null): void => {
    editTimeRoute.value = route
    editTimeLabel.value = label
    editTimeValue.value = currentValue ? new Date(currentValue) : new Date()
    editTimeErrorMsg.value = null
    isEditTimeModalOpen.value = true
}

const closeEditTimeModal = (): void => {
    isEditTimeModalOpen.value = false
    editTimeRoute.value = null
    editTimeValue.value = null
    editTimeErrorMsg.value = null
}

const submitEditTime = async (): Promise<void> => {
    if (!editTimeRoute.value || !editTimeValue.value) {
        return
    }

    isEditTimeSubmitting.value = true
    editTimeErrorMsg.value = null

    try {
        await axios.patch(editTimeRoute.value, {
            clocked_at: format(editTimeValue.value, "yyyy-MM-dd'T'HH:mm:ssXXX"),
        })

        notify({
            title: trans('Success'),
            text: trans('Clocking time updated successfully.'),
            type: 'success',
        })

        router.reload({
            only: [props.tab || 'time_trackers', 'clockings', 'timesheet']
        })

        closeEditTimeModal()
    } catch (e: any) {
        const message = e?.response?.data?.message ?? trans('Failed to update clocking time.')
        editTimeErrorMsg.value = message

        notify({
            title: trans('Failed'),
            text: message,
            type: 'error',
        })
    } finally {
        isEditTimeSubmitting.value = false
    }
}

const openAddModal = (): void => {
    newClockedAt.value = new Date()
    newNotes.value = ''
    addErrorMsg.value = null
    isAddModalOpen.value = true
}

const closeAddModal = (): void => {
    isAddModalOpen.value = false
    newClockedAt.value = null
    newNotes.value = ''
    addErrorMsg.value = null
}

const submitAddClocking = async (): Promise<void> => {
    if (!props.storeClockingRoute || !newClockedAt.value) {
        return
    }

    isAddSubmitting.value = true
    addErrorMsg.value = null

    try {
        await axios.post(props.storeClockingRoute, {
            clocked_at: format(newClockedAt.value, "yyyy-MM-dd'T'HH:mm:ssXXX"),
            notes: newNotes.value || null,
        })

        notify({
            title: trans('Success'),
            text: trans('Clocking added successfully.'),
            type: 'success',
        })

        router.reload({
            only: [props.tab || 'time_trackers', 'timesheet']
        })

        closeAddModal()
    } catch (e: any) {
        const message = e?.response?.data?.message ?? trans('Failed to add clocking.')
        addErrorMsg.value = message

        notify({
            title: trans('Failed'),
            text: message,
            type: 'error',
        })
    } finally {
        isAddSubmitting.value = false
    }
}

</script>

<template>
    <div>
        <div v-if="canEdit && storeClockingRoute" class="flex justify-end mb-3">
            <Button
                type="secondary"
                size="xs"
                :icon="faPlus"
                :label="trans('Add clocking')"
                @click="openAddModal"
            />
        </div>

        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(slug)="{ item: clocking }">
                <Link :href="clockingRoute(clocking)">
                    {{ clocking['slug'] }}
                </Link>
            </template>

            <template #cell(starts_at)="{ item: clocking }">
                <div class="flex items-center gap-x-1.5">
                    {{ useHMAP(clocking.starts_at) }}
                    <button
                        v-if="canEdit && clocking.edit_clock_in_route"
                        type="button"
                        class="text-gray-300 hover:text-gray-600"
                        :aria-label="trans('Edit clock in time')"
                        @click="openEditTimeModal(clocking.edit_clock_in_route, trans('Edit clock in time'), clocking.starts_at)"
                    >
                        <FontAwesomeIcon :icon="faEdit" class="w-3 h-3" />
                    </button>
                </div>
            </template>

            <template #cell(ends_at)="{ item: clocking }">
                <div class="flex items-center gap-x-1.5">
                    {{ useHMAP(clocking.ends_at) }}
                    <button
                        v-if="canEdit && clocking.edit_clock_out_route"
                        type="button"
                        class="text-gray-300 hover:text-gray-600"
                        :aria-label="trans('Edit clock out time')"
                        @click="openEditTimeModal(clocking.edit_clock_out_route, trans('Edit clock out time'), clocking.ends_at)"
                    >
                        <FontAwesomeIcon :icon="faEdit" class="w-3 h-3" />
                    </button>
                </div>
            </template>

            <template #cell(status)="{ item: clocking }">
                <Icon :data="clocking['status']" class="px-1" />
            </template>

            <template v-if="canEdit" #cell(action)="{ item: clocking }">
                <div class="flex items-center gap-x-2 whitespace-nowrap">
                    <Button
                        v-if="canEdit && clocking.can_add_clock_in"
                        type="transparent"
                        size="xs"
                        :icon="faPlus"
                        :label="trans('Add clock in')"
                        class="whitespace-nowrap"
                        @click="openClockInModal(clocking)"
                    />

                    <Button
                        v-if="canEdit && clocking.can_add_clock_out"
                        type="transparent"
                        size="xs"
                        :icon="faPlus"
                        :label="trans('Add clock out')"
                        class="whitespace-nowrap"
                        @click="openClockOutModal(clocking)"
                    />

                    <ModalConfirmationDelete
                        v-if="clocking.delete_route"
                        :routeDelete="clocking.delete_route"
                        :title="trans('Delete this time tracker?')"
                        :description="trans('This will also permanently delete the clock in and clock out records for this working period. This action cannot be undone.')"
                    >
                        <template #default="{ changeModel }">
                            <Button
                                type="cancel"
                                size="xs"
                                :icon="faTrash"
                                :label="trans('Delete')"
                                @click="changeModel(true)"
                            />
                        </template>
                    </ModalConfirmationDelete>
                </div>
            </template>
        </Table>

        <Modal
            :isOpen="isClockOutModalOpen"
            @onClose="closeClockOutModal"
            width="w-full max-w-md"
        >
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                {{ trans('Add clock out') }}
            </h2>

            <form @submit.prevent="submitClockOut" class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Date') }}
                        </label>
                        <div class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                            {{ selectedTimeTracker?.starts_at ? useFormatTime(selectedTimeTracker.starts_at) : '-' }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Clock out time') }}
                        </label>
                        <DatePicker
                            v-model="clockOutTime"
                            timeOnly
                            hourFormat="24"
                            showIcon
                            fluid
                            class="mt-1"
                        />
                    </div>

                    <p v-if="errorMsg" class="text-sm text-red-600">
                        {{ errorMsg }}
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button
                        type="secondary"
                        :label="trans('Cancel')"
                        :disabled="isSubmitting"
                        @click="closeClockOutModal"
                    />
                    <Button
                        type="primary"
                        :label="isSubmitting ? trans('Saving...') : trans('Save')"
                        :disabled="isSubmitting"
                        nativeType="submit"
                    />
                </div>
            </form>
        </Modal>

        <Modal
            :isOpen="isClockInModalOpen"
            @onClose="closeClockInModal"
            width="w-full max-w-md"
        >
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                {{ trans('Add clock in') }}
            </h2>

            <form @submit.prevent="submitClockIn" class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Date') }}
                        </label>
                        <div class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-500">
                            {{ selectedTimeTracker?.ends_at ? useFormatTime(selectedTimeTracker.ends_at) : '-' }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Clock in time') }}
                        </label>
                        <DatePicker
                            v-model="clockInTime"
                            timeOnly
                            hourFormat="24"
                            showIcon
                            fluid
                            class="mt-1"
                        />
                    </div>

                    <p v-if="errorMsg" class="text-sm text-red-600">
                        {{ errorMsg }}
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button
                        type="secondary"
                        :label="trans('Cancel')"
                        :disabled="isSubmitting"
                        @click="closeClockInModal"
                    />
                    <Button
                        type="primary"
                        :label="isSubmitting ? trans('Saving...') : trans('Save')"
                        :disabled="isSubmitting"
                        nativeType="submit"
                    />
                </div>
            </form>
        </Modal>

        <Modal
            :isOpen="isAddModalOpen"
            @onClose="closeAddModal"
            width="w-full max-w-md"
        >
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                {{ trans('Add Clocking') }}
            </h2>

            <form @submit.prevent="submitAddClocking" class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Clocked At') }}
                        </label>
                        <DatePicker
                            v-model="newClockedAt"
                            showTime
                            showSeconds
                            hourFormat="24"
                            showIcon
                            fluid
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Notes') }}
                        </label>
                        <textarea
                            v-model="newNotes"
                            rows="4"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <p v-if="addErrorMsg" class="text-sm text-red-600">
                        {{ addErrorMsg }}
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button
                        type="secondary"
                        :label="trans('Cancel')"
                        :disabled="isAddSubmitting"
                        @click="closeAddModal"
                    />
                    <Button
                        type="primary"
                        :label="isAddSubmitting ? trans('Saving...') : trans('Save')"
                        :disabled="isAddSubmitting"
                        nativeType="submit"
                    />
                </div>
            </form>
        </Modal>

        <Modal
            :isOpen="isEditTimeModalOpen"
            @onClose="closeEditTimeModal"
            width="w-full max-w-md"
        >
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                {{ editTimeLabel }}
            </h2>

            <form @submit.prevent="submitEditTime" class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans('Clocked At') }}
                        </label>
                        <DatePicker
                            v-model="editTimeValue"
                            showTime
                            showSeconds
                            hourFormat="24"
                            showIcon
                            fluid
                            class="mt-1"
                        />
                    </div>

                    <p v-if="editTimeErrorMsg" class="text-sm text-red-600">
                        {{ editTimeErrorMsg }}
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button
                        type="secondary"
                        :label="trans('Cancel')"
                        :disabled="isEditTimeSubmitting"
                        @click="closeEditTimeModal"
                    />
                    <Button
                        type="primary"
                        :label="isEditTimeSubmitting ? trans('Saving...') : trans('Save')"
                        :disabled="isEditTimeSubmitting"
                        nativeType="submit"
                    />
                </div>
            </form>
        </Modal>
    </div>
</template>
