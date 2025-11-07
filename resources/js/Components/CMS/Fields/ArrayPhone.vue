<script setup lang="ts">
import { ref, watch } from 'vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import InputText from 'primevue/inputtext';
// import { isArray } from 'lodash-es';

const props = defineProps<{
    modelValue: any[],
    background?:string
}>();

const emit = defineEmits(['update:modelValue']);

// Function to add a new item
const addItem = () => {
    props.modelValue.push(''); // Add an empty string as a new item
};

// Function to remove an item by index
const removeItem = (index: number) => {
    props.modelValue.splice(index, 1);
};
</script>

<template>
    <div class="px-0 py-3 max-w-md mx-auto bg-white">
        
        <!-- Input fields with delete button -->
        <div v-for="(item, index) in modelValue" :key="index" class="flex items-center mb-3 space-x-2">
            <InputText 
                type="text" 
                v-model="modelValue[index]" 
                placeholder="Enter value" 
                class="w-full p-2 border border-gray-300 rounded focus:outline-hidden focus:ring-2 focus:ring-blue-400"
            />
            <Button 
                :icon="['far', 'fa-trash-alt']"
                @click="removeItem(index)" 
                :style="'red'"
            />
        </div>
        
        <!-- Add button -->
        <Button 
            label="Add Item" 
            icon="fa fa-plus" 
            @click="addItem" 
            type="dashed"
            full
        />
    </div>
</template>

<style scoped>
/* Optional: Custom styling for PrimeVue InputText */
.p-inputtext {
    background-color: #f9fafb; /* Light background */
    color: #374151; /* Text color */
}
</style>
