<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 May 2023 09:18:00 Malaysia Time, Pantai Lembeng, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Multiselect from "@vueform/multiselect"
import { set, get } from 'lodash-es'
import { ref, watch } from 'vue'
const props = defineProps<{
    data?: any
    fieldName?: any
    fieldData?: {
        placeholder: string,
        searchable: boolean,
        options : Array,
    }
    value?: any
}>()
const emit = defineEmits()
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


const value = ref(props.data ? setFormValue(props.data, props.fieldName) : get(props,'value',null))

watch(value, (newValue) => {
    // Update the form field value when the value ref changes
    updateFormValue(newValue);
    emit('onChange', newValue);
});



const updateFormValue = (newValue) => {
    let target = { ...props.data };

    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue);
    } else {
        target[props.fieldName] = newValue;
    }

    // Emit an event to notify the parent component
    emit('input', target);
};
</script>

<template>
    <div class="">
        <div class="relative">
            <Multiselect v-model="value"
                :options="props.fieldData.options" :placeholder="props.fieldData.placeholder ?? 'Select your option'"
                :canClear="!props.fieldData.required"
                :closeOnSelect="props.fieldData.mode == 'multiple' ? false : true" :canDeselect="!props.fieldData.required"
                :hideSelected="false" :searchable="!!props.fieldData.searchable" />
        </div>
    </div>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>
