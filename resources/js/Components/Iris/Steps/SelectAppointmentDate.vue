<script setup lang='ts'>
import { ref } from 'vue'
// import { DatePicker } from 'v-calendar'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { RadioGroup, RadioGroupLabel, RadioGroupOption } from '@headlessui/vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { DataToSubmit } from '@/types/Iris/Appointment'

const props = defineProps<{
    title?: string
    modelValue: Date
    availableSchedulesOnMonth: {
        [key: string]: {
            [key: string]: string[]
        }[]
    }
    isLoading?: boolean
    dataAppointmentToSubmit: DataToSubmit
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: Date): void
    (e: 'onFinish'): void
    (e: 'onSelectHour', value: string): void
}>()

// To convert date to yyyy-mm-dd
const getDateOnly = (dateString: Date): string => {
    const date = new Date(dateString)

    // Extract the year, month, and day components
    const year = date.getFullYear()
    const month = date.getMonth() + 1 // Month is zero-based, so add 1
    const day = date.getDate()

    const formattedDate = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`
    return formattedDate  // 2023-11-23
}

// Attribute for DatePicker
const attrs = ref([
    {
        key: "today",
        highlight: {
            color: "gray",
            fillMode: "outline",
        },
        dates: new Date(),
    }
])

// Options: Meet event
const meetOptions = [
    {
        name: 'callback',
        label: 'Callback'
    },
    {
        name: 'in_person',
        label: 'In person'
    }
]
</script>

<template>
    <div class="mt-4 space-y-4">
        <!-- <div class="bg-white rounded text-center text-gray-500">
            Select date & time
        </div> -->
        
        <div class="grid grid-cols-2 divide-x divide-gray-300">
            <!-- Section: Date picker -->
            <div class="w-full px-8">
                <!-- <DatePicker :value="modelValue" @update:modelValue="(newVal: Date) => emits('update:modelValue', newVal)" expanded :attributes="attrs" :min-date='new Date()'/> -->
            </div>

            <!-- Section: Button Hour, Button Submit -->
            <div class="px-6 text-gray-600">
                <div>Select hour on {{ useFormatTime(modelValue) }}:</div>
                <div v-if="modelValue" class="h-fit grid grid-cols-5 justify-center gap-x-1.5 gap-y-2 mt-1">
                    <template v-if="isLoading">
                        <div v-for="_ in 7" class="w-full h-9 rounded skeleton" />
                    </template>
        
                    <template v-else>
                        <template v-if="availableSchedulesOnMonth[`${modelValue?.getFullYear()}-${modelValue?.getMonth() + 1}` as keyof any]?.[getDateOnly(modelValue)].length > 0">
                            <Button v-for="time in availableSchedulesOnMonth[`${modelValue?.getFullYear()}-${modelValue?.getMonth() + 1}`][getDateOnly(modelValue)]"
                                full
                                :key="modelValue + time"
                                @click="emits('onSelectHour', time)"
                                :style="time.split(':')[0] == modelValue.getHours() && time.split(':')[1] == modelValue.getMinutes()
                                    ? 'secondary'
                                    : 'dashed'
                                "
                                :label="time"
                                class="h-fit"
                            />
        
                            <!-- Section: Meet Event -->
                            <div class="col-span-5 mt-5 text-base">
                                Select your type of appointment:
                                <RadioGroup v-model="dataAppointmentToSubmit.meetType" class="mt-2">
                                    <RadioGroupLabel class="sr-only">Choose the radio</RadioGroupLabel>
                                    <div class="flex gap-x-1.5 gap-y-1 flex-wrap">
                                        <RadioGroupOption
                                            v-for="(option, index) in meetOptions"
                                            :key="index"
                                            as="div"
                                            :value="option.name" v-slot="{ active, checked }">
                                            <Button
                                                :label="option.label"
                                                :key="`${index}${option.name}${checked}`"
                                                :style="checked ? 'secondary' : 'dashed'"
                                            />
                                            <!-- <div
                                                :class="[
                                                    'cursor-pointer focus:outline-hidden flex items-center justify-center rounded-md py-3 px-3 text-sm font-medium',
                                                    active ? 'ring-2 ring-gray-600' : '',
                                                    checked ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white' : 'border border-dashed border-gray-300 bg-white text-gray-700 hover:bg-gray-200',
                                                ]">
                                                <RadioGroupLabel as="span">{{ option.label }}</RadioGroupLabel>
                                            </div> -->
                                        </RadioGroupOption>
                                    </div>
                                </RadioGroup>
                            </div>
                            <!-- Button: Submit -->
                            <div v-if="dataAppointmentToSubmit.meetType && (availableSchedulesOnMonth[`${modelValue?.getFullYear()}-${modelValue?.getMonth() + 1}` as keyof any]?.[getDateOnly(modelValue)]).includes(`${(modelValue.getHours()).toString().padStart(2, '0')}:00`)"
                                class="col-span-5 mt-6">
                                <Button @click="emits('onFinish')" iconRight="fas fa-arrow-alt-right" label="Next step" full />
                            </div>
                        </template>
                        <div v-else class="col-span-3 flex gap-x-1 items-center justify-center text-gray-400">
                            No schedules available <span class="text-gray-500"> {{ useFormatTime(modelValue) }}</span>
                        </div>
                    </template>
                </div>
                
                <!-- If not selected date yet -->
                <div v-else class="text-gray-500 italic">
                    ---- Select date to make an appointment ----
                </div>
            </div>
        </div>
    </div>
    
</template>