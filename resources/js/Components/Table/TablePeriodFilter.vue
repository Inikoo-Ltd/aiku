<script setup lang="ts">
import { inject, ref } from 'vue'
import { onMounted } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { format } from 'date-fns'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faCheckSquare, faSquare, faCalendarAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import VueDatePicker from '@vuepic/vue-datepicker'
library.add(faChevronDown, faCheckSquare, faSquare, faCalendarAlt)

interface Period {
    type: string  // 'quarter'
    label: string  // 'Quarter'
    date: string  // '2024Q3'
}

const props = defineProps<{
    periodList: Period[]
    tableName: string
}>()

const layout = inject('layout', layoutStructure)

const emits = defineEmits<{
    (e: 'periodChanged', value: {[key: string]: string}): void
}>()

const selectedPeriodType = ref<string | null>('')  // 'quarter' | 'year | etc
const onClickPeriodType = (period: Period) => {
    // If click on active period, then clear the period
    if(selectedPeriodType.value === period.type) {
        emits('periodChanged', {})
        selectedPeriodType.value = null
    }
    else {
        emits('periodChanged', { [period.type]: period.date })  // { quarter: "2024Q2" }
        selectedPeriodType.value = period.type
        if(period.type === 'year') {
            datePickerValue.value = new Date().getFullYear()  // 2024

        } else {
            datePickerValue.value = new Date()
        }
    }

    
}
const onSelectDate = (date: string | number | string[]) => {
    datePickerValue.value = date
    
    emits('periodChanged', { [selectedPeriodType.value]: generateDateToQuery(date) })
}

const datePickerValue = ref()

const generateDateToQuery = (date: Date | string | string[] | number | {year: string, month: string}) => {
    if (!date) return false

    if(selectedPeriodType.value === 'day') {
        return format(date, 'yyyyMMdd')  // 20240528
    }

    if(selectedPeriodType.value === 'week') {
        return format(date[0], 'yyyyww')  // 202438
    }

    if(selectedPeriodType.value === 'month') {
        return date.year + (date.month + 1).toString().padStart(2, 0)  // 202405
    }

    if(selectedPeriodType.value === 'quarter') {
        const dateQuarter = new Date(date)
        const year = dateQuarter.getFullYear()
        const month = dateQuarter.getMonth() + 1 // getMonth() returns 0-based month
        let quarter

        if (month >= 1 && month <= 3) {
            quarter = 1
        } else if (month >= 4 && month <= 6) {
            quarter = 2
        } else if (month >= 7 && month <= 9) {
            quarter = 3
        } else if (month >= 10 && month <= 12) {
            quarter = 4
        }

        return `${year}Q${quarter}`  // 2024Q3
    }

    if(selectedPeriodType.value === 'year') {
        return date  // 2024
    }
}

const generateQueryReverse = (query) => {
    if (!query) return false;

    if (selectedPeriodType.value === 'day') {
        const year = query.slice(0, 4);
        const month = query.slice(4, 6) - 1;  // JavaScript months are 0-based
        const day = query.slice(6, 8);
        return new Date(year, month, day).toISOString();  // '2024-05-28T00:00:00.000Z'
    }

    if (selectedPeriodType.value === 'week') {
        const year = query.slice(0, 4);
        const week = query.slice(4, 6);
        const firstDayOfYear = new Date(year, 0, 1);
        const days = (week - 1) * 7;
        const resultDate = new Date(firstDayOfYear.setDate(firstDayOfYear.getDate() + days));
        // Adjustment to start of the week (Monday)
        const dayOfWeek = resultDate.getUTCDay();
        const startOfWeek = resultDate.getUTCDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
        return [new Date(resultDate.setUTCDate(startOfWeek)).toISOString()];  // '2024-09-15T00:00:00.000Z'
    }

    if (selectedPeriodType.value === 'month') {
        const year = query.slice(0, 4);
        const month = query.slice(4, 6) - 1;  // JavaScript months are 0-based
        return {
            month: month,
            year: year
        }
    }

    if (selectedPeriodType.value === 'quarter') {
        const year = query.slice(0, 4);
        const quarter = query.slice(5, 6);
        const month = (quarter - 1) * 3;  // Calculate the start month of the quarter
        return new Date(year, month, 1).toISOString();  // '2024-07-01T00:00:00.000Z' for Q3
    }

    if (selectedPeriodType.value === 'year') {
        const year = query;
        return new Date(year, 0, 1).toISOString();  // '2024-01-01T00:00:00.000Z'
    }
};


onMounted(() => {
    // To preserve active period on hard-refresh
    const prefix = props.tableName === 'default' ? 'period' : props.tableName + '_' + 'period'  // To handle banners_elements, users_elements, etc
    if(route().params[prefix]) {
        selectedPeriodType.value = Object.keys(route().params[prefix] || [])[0]  // 'quarter' || 'year' || etc
        datePickerValue.value = generateQueryReverse(route().params[prefix][selectedPeriodType.value])
    }
})

</script>

<template>
    <div class="shadow border border-gray-300 flex rounded-md">
        <div v-for="period, idxPeriod in periodList"
            :key="'datePickerPeriod' + idxPeriod"
            @click="() => onClickPeriodType(period)"
            class="px-3 py-1 cursor-pointer flex items-center gap-x-2"
            :class="[selectedPeriodType === period.type ? '' : 'rounded-lg bg-white hover:bg-gray-50']"
            :style="{
                backgroundColor: selectedPeriodType === period.type ? layout?.app?.theme[4] + '22' : '' 
            }"
        >
            {{ period.label }}

            <VueDatePicker
                v-if="period.type == selectedPeriodType"
                :modelValue="datePickerValue"
                @update:modelValue="(value: string | string[] | number) => onSelectDate(value)"
                v-bind="{ [`${selectedPeriodType}-picker`]: true }"
                auto-apply
                :enableTimePicker="false"
            >
                <template #trigger>
                    <div class="px-1 py-0.5 bg-gray-700 text-white rounded w-fit">
                        <FontAwesomeIcon icon='fal fa-calendar-alt' class='cursor-pointer hover:text-gray-600'
                            fixed-width aria-hidden='true' />
                    </div>
                </template>
            </VueDatePicker>
        </div>
    </div>
</template>
