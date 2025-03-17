<script setup lang="ts">
import { set } from 'lodash-es'
import { ref, watch } from 'vue'

const props = defineProps<{
    fieldName: string
    fieldData?: {
        placeholder: string
        readonly: boolean
        copyButton: boolean
        timeRange: {
            max: number
            min: number
            step: number
            range: string
        }
    }
    data: Object
    counter: boolean
}>()

const emit = defineEmits()

const setFormValue = (data: Object, fieldName: string) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName) / 1000
    } else {
        return data[fieldName] / 1000
    }
}

const getNestedValue = (obj: Object, keys: Array<string>) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === 'object' && key in acc) return acc[key]
        return null
    }, obj)
}

const value = ref(setFormValue(props.data, props.fieldName))

watch(value, (newValue) => {
    updateLocalFormValue(newValue);
});

const updateLocalFormValue = (newValue) => {
      let localData = { ...props.data }
      if (Array.isArray(props.fieldName)) {
          set(localData, props.fieldName, newValue * 1000); // Convert back to milliseconds
      } else {
          localData[props.fieldName] = newValue * 1000; // Convert back to milliseconds
      }
      
      props.data[props.fieldName] = localData[props.fieldName]
  };
</script>

<template>
    <div class="flex flex-col space-y-2 p-2 w-full">
        <p class="text-gray-600">Duration: <span class="font-bold">{{ value }}</span> seconds</p>
        <input v-model="value" type="range" class="w-full range accent-amber-400 hover:accent-amber-300" :min='fieldData?.timeRange.min'
            :max="fieldData?.timeRange.max" :step="fieldData?.timeRange.step" />
        <ul class="flex justify-between w-full px-2.5">
            <li v-for="item in fieldData?.timeRange.range" :key="item" class="flex justify-center relative"><span
                    class="absolute">{{ item }}</span>
            </li>
        </ul>
    </div>
</template>

<style scoped></style>
