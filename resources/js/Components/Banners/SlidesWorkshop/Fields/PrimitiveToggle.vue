<script setup lang="ts">
import Toggle from '@/Components/Pure/Toggle.vue'
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
        return false;
    }, obj);
}


const value = ref(props.data ? setFormValue(props.data, props.fieldName) : get(props,'value',false))

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
    console.log(target)
    emit('input', target);
};
</script>


<template>
    <div class="flex items-center gap-x-2 transition-all duration-1000 ease-in-out">
        <Toggle  v-model="value"/>
    </div>
</template>