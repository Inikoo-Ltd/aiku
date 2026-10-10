<script setup lang="ts">
import { ref, watch } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import SelectButton from 'primevue/selectbutton'

// Define props
// const props = defineProps({
//     modelValue: {
//         type: Object,
//         required: true,
//     }
// });

const model = defineModel()

// Emits to notify parent component of changes
// const emit = defineEmits(['update:modelValue']);
// Options for the SelectButton
const options = ref([
    { label: 'Hidden', value: null },
    { label: 'Always', value: 'all' },
    { label: 'On logged in', value: 'login' },
    { label: 'On logged out', value: 'logout' },
]);

// Local ref for visibility value
// const visible = ref(props.modelValue.visible ?  options.value.find((item)=>item.value == props.modelValue.visible)   : 'all');

// Watch for changes in the local visible value and emit updates
// watch(visible, (newValue) => {
//     console.log(newValue)
//     emit('update:modelValue', { ...props.modelValue, visible: newValue.value });
// });



</script>



<template>
    <div class="">
        <!-- {{ model }} === -->
    <!--     <div class="my-2 text-gray-500 text-xs font-semibold">{{ ctrans('Visibility') }}</div> -->
        <SelectButton
            v-model="model"
            :options="options"
            optionLabel="label"
            optionValue="value"
            :allowEmpty="false"
        >
            <template #option="slotProps">
                <span class="text-xs">{{ slotProps.option.label }}</span>
            </template>
        </SelectButton>
    </div>
</template>




<style lang="scss" scoped></style>