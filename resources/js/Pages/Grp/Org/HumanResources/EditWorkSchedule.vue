<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { ref, computed } from 'vue'
import InputText from 'primevue/inputtext'
import Button from '@/Components/Elements/Buttons/Button.vue'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'

interface WorkScheduleDay {
    id: number
    day_of_week: number
    is_working_day: boolean
    start_time: string | null
    end_time: string | null
}

const props = defineProps<{
    pageHead: {}
    title: string
    schedule: any
}>()

const days = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 7, label: 'Sunday' },
]

const form = ref({
    name: props.schedule.name,
})

const workingHours = ref<Record<number, { s: string | null, e: string | null, b: any[] }>>({})

const initializeForm = () => {
    form.value.name = props.schedule.name

    days.forEach(day => {
        const dayData = props.schedule.days?.find((d: WorkScheduleDay) => d.day_of_week === day.value)
        if (dayData && dayData.is_working_day) {
            workingHours.value[day.value] = {
                s: dayData.start_time || null,
                e: dayData.end_time || null,
                b: dayData.breaks?.map((b: any) => ({
                    s: b.start_time || null,
                    e: b.end_time || null,
                    n: b.break_name,
                    p: b.is_paid,
                })) || []
            }
        } else {
            workingHours.value[day.value] = { s: null, e: null, b: [] }
        }
    })
}

initializeForm()

const isSubmitting = ref(false)

const toggleWorkingDay = (dayOfWeek: number) => {
    const current = workingHours.value[dayOfWeek]
    if (current && current.s) {
        workingHours.value[dayOfWeek] = { s: null, e: null, b: [] }
    } else {
        workingHours.value[dayOfWeek] = { s: '08:00', e: '17:00', b: [] }
    }
}

const submitForm = async () => {
    isSubmitting.value = true
    try {
        await axios.patch(route('grp.org.hr.shift_schedules.update', [route().params.organisation, props.schedule.id]), {
            name: form.value.name,
            working_hours: {
                data: workingHours.value
            }
        })
        router.visit(route('grp.org.hr.shift_schedules.index', route().params.organisation))
    } catch (error: any) {
        console.error('Failed to update shift schedule:', error)
        alert('Failed to update shift schedule')
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Edit Shift: {{ schedule.name }}</h2>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Shift Name</label>
                <InputText v-model="form.name" class="w-full" />
            </div>

            <div class="space-y-4">
                <h3 class="font-medium text-gray-900">Working Hours</h3>

                <div v-for="day in days" :key="day.value" class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                :checked="workingHours[day.value]?.s"
                                @change="toggleWorkingDay(day.value)"
                                class="h-4 w-4 text-blue-600 rounded focus:ring-blue-500"
                            />
                            <span class="font-medium">{{ day.label }}</span>
                        </div>
                        <div v-if="workingHours[day.value]?.s" class="flex items-center gap-2 text-sm text-gray-500">
                            <span>{{ workingHours[day.value].s }}</span>
                            <span>-</span>
                            <span>{{ workingHours[day.value].e }}</span>
                        </div>
                    </div>

                    <div v-if="workingHours[day.value]?.s" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Start Time</label>
                            <input
                                type="time"
                                v-model="workingHours[day.value].s"
                                class="border rounded px-3 py-2 w-full"
                            />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">End Time</label>
                            <input
                                type="time"
                                v-model="workingHours[day.value].e"
                                class="border rounded px-3 py-2 w-full"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
                <Button
                    :label="trans('Cancel')"
                    type="exit"
                    @click="router.visit(route('grp.org.hr.shift_schedules.index', route().params.organisation))"
                />
                <Button
                    :label="isSubmitting ? 'Saving...' : 'Save Changes'"
                    type="save"
                    @click="submitForm"
                    :disabled="isSubmitting"
                />
            </div>
        </div>
    </div>
</template>
