<script setup lang="ts">
import { ref } from 'vue'
import SelectButton from 'primevue/selectbutton';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faAlignCenter, faAlignLeft, faAlignRight } from '@fal';

const model = defineModel<string>({ required: true })

const options = [
    { icon: faAlignLeft, value: 'left', label: 'Left' },
    { icon: faAlignCenter, value: 'center', label: 'Center', },
    { icon: faAlignRight, value: 'right', label: 'Right', },
]

</script>

<template>
    <div>
        <SelectButton 
            v-model="model" 
            :options="options" 
            optionLabel="label" 
            optionValue="value" 
            dataKey="value" 
            aria-labelledby="custom"
            class="w-full flex"
        >
            <template #option="slotProps">
                <div class="w-full flex justify-center">
                    <FontAwesomeIcon :icon="slotProps.option.icon" />
                </div>
            </template>
        </SelectButton>
    </div>
</template>

<style scoped>
</style>

