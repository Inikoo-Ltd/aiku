<script setup lang="ts">

// import { RadioGroup, RadioGroupLabel, RadioGroupOption, RadioGroupDescription } from '@headlessui/vue'
import { ref, watch } from 'vue'
import { set , isEqual, get } from 'lodash-es'

const props = defineProps<{
    fieldName?: string | [];
    fieldData?:Object
    data?: Object;
    radioValue?: Object | String;
}>()
const emit = defineEmits()


const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName);
    } else {
        return get(acc,key,get(props,['fieldData','defaultValue']))
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === 'object' && key in acc) return get(acc,key,get(props,['fieldData','defaultValue']))
        return props.fieldData.defaultValue ? props.fieldData.defaultValue : null;
    }, obj);
}


const value = ref(props.data ? setFormValue(props.data, props.fieldName) : get(props,'radioValue',get(props,['fieldData','defaultValue'])))

watch(value, (newValue) => {
    emit('onChange', newValue);
    updateFormValue(newValue);
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
    <div>
        <fieldset class="select-none">
            <legend class="sr-only"></legend>
            <div class="flex items-center gap-x-5 gap-y-1 flex-wrap">

                <!-- Radio: Default -->
                <label :for="option.label + index" v-for="(option, index) in fieldData.options"
                    :key="option.label + index" class="inline-flex items-center gap-x-1.5 cursor-pointer py-1">
                    <input v-model="value" :id="option.label + index" :key="option.label + index"
                        :name="option.value" type="radio" :value="option.value" :checked="isEqual(value,option.value)"
                        class="h-4 w-4 border-gray-300 text-gray-600 focus:ring-0 focus:outline-none focus:ring-transparent cursor-pointer" />
                    <div class="flex items-center gap-x-1.5">
                        <span v-if="option.label" class="font-light text-sm text-gray-400">
                            {{ option.label }}
                        </span>
                    </div>
                </label>
            </div>
        </fieldset>
    </div>
</template>