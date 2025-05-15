<script setup lang="ts">
import { ref, watch, toRaw } from 'vue';
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Button from "@/Components/Elements/Buttons/Button.vue";
import OverviewProperty from "@/Components/Workshop/Properties/OverviewProperty.vue";

import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrash } from "@fal";
import { cloneDeep } from "lodash";
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue";
import CardsProperty from "@/Components/Workshop/Properties/CardsProperty.vue";
import { routeType } from "@/types/route";

library.add(faPlus, faTrash);

// Define reactive modelValue prop
const props = defineProps<{  modelValue: {
        type: [],
        required: true,
    }, uploadRoutes: routeType }>();


// Emit changes to modelValue
const emit = defineEmits(["update:modelValue"]);


const onChangeProperty = (index, data) => {
   /*  const setData = [...props.modelValue]

    setData[index] = data; */
  /*   emit("update:modelValue", [...props.modelValue]); */
     const updatedData = [...props.modelValue]; // Clone the array to maintain reactivity
    updatedData[index] = { ...updatedData[index], ...data }; // Merge existing data with new changes
    emit("update:modelValue", updatedData); // Emit the updated array
};


</script>

<template>
    <div>
        <div v-if="modelValue?.length" v-for="(field, index) in modelValue" :key="index">
            <Disclosure v-slot="{ open }">
                <DisclosureButton
                    class="flex w-full mb-1 justify-between bg-gray-100 px-4 py-2 text-left text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-none focus-visible:ring focus-visible:ring-gray-500/75">
                    <span>{{ field?.name }}</span>
                </DisclosureButton>
                <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                   <CardsProperty :modelValue="modelValue[index]" :uploadRoutes="uploadRoutes"  @update:model-value="(data) => onChangeProperty(index, data)"/>
                </DisclosurePanel>
            </Disclosure>
        </div>
        <div v-else class="my-2">
            <Button type="dashed" label="No Image Upload"  full ></Button>
        </div>
    </div> 
</template>

<style scoped></style>
