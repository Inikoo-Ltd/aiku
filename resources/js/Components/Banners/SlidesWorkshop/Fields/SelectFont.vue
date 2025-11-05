<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 May 2023 09:18:00 Malaysia Time, Pantai Lembeng, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Multiselect from "@vueform/multiselect"
import { set, lowerCase, snakeCase } from 'lodash-es'
import { ref, watch, computed } from 'vue'

const props = defineProps<{
    data: any
    fieldName: any
    fieldData: {
        placeholder: string
        searchable: boolean
        options: {
            value: string
            label: string
        }[]
        mode?: string
        required?: boolean
    }
}>()

const options = [
    "Arial",
    "Avenir",
    "Bunya",
    "Cardinal",
    "Comfortaa",
    "Lobster",
    "Laila",
    "Port Lligat Slab",
    "Playfair",
    "Raleway",
    "Roman Melikhov",
    "Shoemaker",
    "Source Sans Pro",
    "Quicksand",
    "Times New Roman",
    "Yatra One"
]

const compOptions = computed(() => {
    // Handle if parent provide options = ['value', 'value', 'value']
    return props.fieldData?.options ? options.filter((option: any) => props.fieldData?.options.includes(option)) : options
})

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName);
    } else {
        return data[fieldName];
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === 'object' && key in acc) return acc[key];
        return null;
    }, obj);
}


const value = ref(setFormValue(props.data, props.fieldName))

watch(value, (newValue) => {
    // Update the form field value when the value ref changes
    updateFormValue(newValue);
});

const updateFormValue = (newValue: any) => {
    let target = { ...props.data };

    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue);
    } else {
        target[props.fieldName] = newValue;
    }

    // Emit an event to notify the parent component
    set(props,"data",target)
}

</script>

<template>
    <div class="">
        <div class="relative">
            <Multiselect v-model="value" :options="compOptions"
                :placeholder="props.fieldData.placeholder ?? 'Select your option'" :canClear="false"
                :closeOnSelect="props.fieldData.mode == 'multiple' ? false : true" :canDeselect="!props.fieldData.required"
                :hideSelected="false" :searchable="!!props.fieldData.searchable">
                <template v-slot:singlelabel="{ value }">
                    <div class="multiselect-single-label text-gray-600">
                        <span :style="`font-family : ${snakeCase(lowerCase(value.value))}`">
                            {{ value.value }}
                        </span>
                    </div>
                </template>
                <template #option="{ option }">
                    <span :style="`font-family : ${snakeCase(lowerCase(option.value))}`">
                        {{ option.label }}
                    </span>
                </template>
            </Multiselect>
        </div>
    </div>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>
