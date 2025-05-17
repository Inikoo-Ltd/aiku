<script setup lang='ts'>
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import DatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

const props = defineProps<{
    modelValue: Date | string
    format?: string  // 'dd MMMM yyyy'
    timePicker?: boolean
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: Date): void
}>()

const _dp = ref()  // Element of DatePicker

defineOptions({
    inheritAttrs: false
})
</script>

<template>
    <div class="relative">
        <DatePicker
            v-bind="$attrs"
            ref="_dp"
            :modelValue="modelValue"
            :enableTimePicker="false"
            :format="format ?? undefined"
            auto-apply
            :clearable="!$attrs.required || false"
            @update:modelValue="(newVal: Date) => emits('update:modelValue', newVal)"
        >
            <!-- Button: 'Today' -->
            <template #action-extra="{ selectCurrentDate }">
                <div class="mb-2">
                    <Button @click="selectCurrentDate()" size="xs" label="Today" :style="'tertiary'" />
                </div>
            </template>

            <!-- Button: Select -->
            <!-- <template #action-buttons>
                <div class="pb-2 asdzxc">
                ssssssssssssssss
                    <Button size="xs" label="Select" @click="_dp.selectDate()"/>
                </div>
            </template> -->
        </DatePicker>
    </div>
</template>