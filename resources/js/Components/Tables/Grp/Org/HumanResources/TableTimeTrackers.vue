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
import Modal from '@/Components/Utils/Modal.vue'
import DatePicker from 'primevue/datepicker'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClock, faDoorClosed, faDoorOpen } from "@fal"
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
}>()

library.add(faClock, faDoorOpen, faDoorClosed, faEdit, faPlus)

const isClockOutModalOpen = ref(false)
const selectedTimeTracker = ref<any | null>(null)
const clockOutTime = ref<Date | null>(null)
const isSubmitting = ref(false)
const errorMsg = ref<string | null>(null)

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

</script>

<template>
    <div>
        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(slug)="{ item: clocking }">
                <Link :href="clockingRoute(clocking)">
                    {{ clocking['slug'] }}
                </Link>
            </template>

            <template #cell(starts_at)="{ item: clocking }">
                <div :href="'x'">
                    {{ useHMAP(clocking.starts_at) }}
                </div>
            </template>

            <template #cell(ends_at)="{ item: clocking }">
                <div :href="'x'">
                    {{ useHMAP(clocking.ends_at) }}
                </div>
            </template>

            <template #cell(status)="{ item: clocking }">
                <Icon :data="clocking['status']" class="px-1" />
            </template>

            <template v-if="canEdit" #cell(action)="{ item: clocking }">
                <div class="flex items-center gap-x-2 whitespace-nowrap">
                    <Button
                        v-if="canEdit && clocking.can_add_clock_out"
                        type="transparent"
                        size="xs"
                        :icon="faPlus"
                        :label="trans('Add clockout')"
                        class="whitespace-nowrap"
                        @click="openClockOutModal(clocking)"
                    />
                </div>
            </template>
        </Table>

        <Modal
            :isOpen="isClockOutModalOpen"
            @onClose="closeClockOutModal"
            width="w-full max-w-md"
        >
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                {{ trans('Add Clockout') }}
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
    </div>
</template>
