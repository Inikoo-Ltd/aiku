<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { library } from "@fortawesome/fontawesome-svg-core";
import { faChevronLeft, faChevronRight, faFilter, faTachometerAlt, faList, faLayerGroup } from "@fal";

library.add(faChevronLeft, faChevronRight, faFilter, faTachometerAlt, faList, faLayerGroup);

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    filters: {
        year: number
        month: number
        employee_id?: number
        overtime_type_id?: number
    }
    calendarData: {
        id: number
        name: string
        overtimes: {
            id: number
            date: string
            type_name: string
            color: string | null
            duration: number
            formatted_duration: string
            reason: string
            status: string
            start_time: string
            end_time: string
            recorded_start_time: string | null
            recorded_end_time: string | null
            recorded_duration: number | null
            recorded_formatted_duration: string | null
            employee_name: string
            approver_name: string
            overtime_type: string
        }[]
    }[]
    daysInMonth: number
    monthName: string
    employeeOptions: { value: number; label: string }[]
    overtimeTypeOptions: { value: number; label: string }[]
}>()

const showModal = ref(false)
const selectedOvertime = ref<any>(null)

const openModal = (overtime: any) => {
    selectedOvertime.value = overtime
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    selectedOvertime.value = null
}

const monthOptions = computed(() => {
    return Array.from({ length: 12 }, (_, i) => {
        const date = new Date(2000, i, 1);
        return {
            value: i + 1,
            label: date.toLocaleString('default', { month: 'long' })
        };
    });
});

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 5 }, (_, i) => {
        return {
            value: currentYear - 2 + i,
            label: String(currentYear - 2 + i)
        };
    });
});

const updateFilter = () => {
    router.get(
        route('grp.org.hr.overtime.dashboard', route().params),
        {
            year: props.filters.year,
            month: props.filters.month,
            employee_id: props.filters.employee_id,
            overtime_type_id: props.filters.overtime_type_id,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const prevMonth = () => {
    let newMonth = props.filters.month - 1;
    let newYear = props.filters.year;
    if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    }

    router.visit(route('grp.org.hr.overtime.dashboard', { ...route().params, year: newYear, month: newMonth }));
}

const nextMonth = () => {
    let newMonth = props.filters.month + 1;
    let newYear = props.filters.year;
    if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    }

    router.visit(route('grp.org.hr.overtime.dashboard', { ...route().params, year: newYear, month: newMonth }));
}

const days = computed(() => {
    return Array.from({ length: props.daysInMonth }, (_, i) => i + 1);
});

const getDayName = (day: number) => {
    const date = new Date(props.filters.year, props.filters.month - 1, day);
    return date.toLocaleDateString('en-US', { weekday: 'short' }).slice(0, 2);
};

const isWeekend = (day: number) => {
    const date = new Date(props.filters.year, props.filters.month - 1, day);
    const dayOfWeek = date.getDay();
    return dayOfWeek === 0 || dayOfWeek === 6;
};

const getOvertimeForDay = (overtimes: any[], day: number) => {
    const dateStr = `${props.filters.year}-${String(props.filters.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return overtimes.filter(o => o.date === dateStr);
};
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #before-title>
            <!-- Add any extra elements if needed -->
        </template>
    </PageHeading>

    <div class="mt-5 bg-white shadow-sm rounded-lg p-4">
        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between mb-6">
            <div class="flex gap-2 items-center">
                <Button type="secondary" :icon="faChevronLeft" size="sm" @click="prevMonth" />
                <h2 class="text-xl font-bold text-gray-800 w-48 text-center">
                    {{ monthName }} {{ filters.year }}
                </h2>
                <Button type="secondary" :icon="faChevronRight" size="sm" @click="nextMonth" />
            </div>

            <div class="flex gap-2 items-center flex-wrap">
                <select
                    v-model="filters.employee_id"
                    @change="updateFilter"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option :value="null">{{ trans('All Employees') }}</option>
                    <option v-for="employee in employeeOptions" :key="employee.value" :value="employee.value">
                        {{ employee.label }}
                    </option>
                </select>

                <select
                    v-model="filters.overtime_type_id"
                    @change="updateFilter"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option :value="null">{{ trans('All Types') }}</option>
                    <option v-for="type in overtimeTypeOptions" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>

                <select
                    v-model="filters.year"
                    @change="updateFilter"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option v-for="year in yearOptions" :key="year.value" :value="year.value">
                        {{ year.label }}
                    </option>
                </select>

                <select
                    v-model="filters.month"
                    @change="updateFilter"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                        {{ month.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="overflow-x-auto">
            <div class="min-w-max">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="p-2 border-b border-r border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 z-10 w-48 min-w-[12rem]">
                                {{ trans('Employee') }}
                            </th>
                            <th
                                v-for="day in days"
                                :key="day"
                                class="p-1 border-b border-gray-200 bg-gray-50 text-center w-10 min-w-[2.5rem]"
                                :class="{ 'bg-gray-100': isWeekend(day) }"
                            >
                                <div class="text-xs font-semibold text-gray-700">{{ day }}</div>
                                <div class="text-[10px] text-gray-500">{{ getDayName(day) }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="employee in calendarData" :key="employee.id" class="hover:bg-gray-50">
                            <td class="p-2 border-r border-gray-200 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-white z-10">
                                {{ employee.name }}
                            </td>
                            <td
                                v-for="day in days"
                                :key="day"
                                class="p-1 border-r border-gray-100 h-12 relative align-top"
                                :class="{ 'bg-gray-50': isWeekend(day) }"
                            >
                                <div class="w-full h-full flex flex-col gap-0.5 justify-center">
                                    <template v-for="overtime in getOvertimeForDay(employee.overtimes, day)" :key="overtime.id">
                                        <div
                                            class="flex-1 min-h-[4px] rounded w-full cursor-pointer group relative flex items-center justify-center text-[10px] text-white font-medium hover:opacity-80 transition-opacity"
                                            :style="{ backgroundColor: overtime.color || '#4F46E5' }"
                                            :title="`${overtime.type_name} (${overtime.formatted_duration})`"
                                            @click="openModal(overtime)"
                                        >
                                            {{ overtime.formatted_duration }}
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="calendarData.length === 0">
                            <td :colspan="daysInMonth + 1" class="p-8 text-center text-gray-500">
                                {{ trans('No employees found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Modal :show="showModal" @close="closeModal">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ trans('Overtime Details') }}
                </h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div v-if="selectedOvertime" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Staff Member') }}</label>
                        <div class="mt-1 text-sm text-gray-900">{{ selectedOvertime.employee_name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Status') }}</label>
                        <div class="mt-1">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="{
                                    'bg-green-100 text-green-800': selectedOvertime.status === 'approved',
                                    'bg-yellow-100 text-yellow-800': selectedOvertime.status === 'pending',
                                    'bg-red-100 text-red-800': selectedOvertime.status === 'rejected',
                                }"
                            >
                                {{ capitalize(selectedOvertime.status) }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Date') }}</label>
                        <div class="mt-1 text-sm text-gray-900">{{ selectedOvertime.date }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Requested duration') }}</label>
                        <div class="mt-1 text-sm text-gray-900">{{ selectedOvertime.formatted_duration }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Requested time') }}</label>
                        <div class="mt-1 text-sm text-gray-900">
                            {{ selectedOvertime.start_time }} - {{ selectedOvertime.end_time }}
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Overtime Type') }}</label>
                        <div class="mt-1 text-sm text-gray-900 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: selectedOvertime.color || '#4F46E5' }"></span>
                            {{ selectedOvertime.overtime_type }}
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Approver') }}</label>
                        <div class="mt-1 text-sm text-gray-900">{{ selectedOvertime.approver_name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Recorded duration') }}</label>
                        <div class="mt-1 text-sm text-gray-900">
                            {{ selectedOvertime.recorded_formatted_duration ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">{{ trans('Recorded time') }}</label>
                        <div class="mt-1 text-sm text-gray-900">
                            <template v-if="selectedOvertime.recorded_start_time && selectedOvertime.recorded_end_time">
                                {{ selectedOvertime.recorded_start_time }} - {{ selectedOvertime.recorded_end_time }}
                            </template>
                            <template v-else>
                                —
                            </template>
                        </div>
                    </div>
                </div>

                <div v-if="selectedOvertime.reason">
                    <label class="block text-sm font-medium text-gray-500">{{ trans('Reason') }}</label>
                    <div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md border border-gray-100">
                        {{ selectedOvertime.reason }}
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <Button type="secondary" @click="closeModal">
                    {{ trans('Close') }}
                </Button>
            </div>
        </div>
    </Modal>
</template>
