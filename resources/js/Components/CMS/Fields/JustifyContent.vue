<script setup lang="ts">
import { onMounted } from 'vue'
import SelectButton from 'primevue/selectbutton';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faAlignCenter, faAlignLeft, faAlignRight } from '@fal';


const model = defineModel<typeof localModel>({
    required: true,
})

const localModel = {}



const options = [
    { icon: faAlignLeft, value: 'Left' },
    { icon: faAlignCenter, value: 'Right' },
    { icon: faAlignRight, value: 'Center' },
  /*   { icon: 'pi pi-align-justify', value: 'Justify' } */
]

onMounted(async () => {
    
})

</script>

<template>
    <div >
        <SelectButton v-model="model" :options="options" optionLabel="value" dataKey="value" aria-labelledby="custom">
            <template #option="slotProps">
               <FontAwesomeIcon :icon="slotProps.option.icon" />
            </template>
        </SelectButton>
    </div>
</template>

<style scoped></style>
