<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { toRaw } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrash } from "@fal";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import SideEditorArrayEdit from "./SideEditorArrayEdit.vue";
import draggable from "vuedraggable";

// Add icons to FontAwesome library
library.add(faPlus, faTrash);

// Define props
const props = withDefaults(defineProps<{
  blueprint: any;
  uploadRoutes: routeType;
  order_name: string;
  can_drag: boolean;
  can_delete: boolean;
  can_add: boolean;
  new_value_data: object;
}>(), {
  can_drag: true,
  can_delete: true,
  can_add: true
});
const modelValue = defineModel<Array<object>>({ required: true })

if (!modelValue.value) {
  modelValue.value = []
}

// Functions
const addValue = () => {
  const newValue = toRaw(modelValue.value);
  newValue.push(props.new_value_data);
  modelValue.value = newValue;
};

const removeValue = (index: number) => {
  const newValue = toRaw(modelValue.value);
  newValue.splice(index, 1);
  modelValue.value = newValue;
};

const onChangeProperty = (index: number, data: object) => {
  const newValue = toRaw(modelValue.value);
  newValue[index] = data;
  modelValue.value = newValue;
};

console.log('sss',modelValue.value)
</script>



<template>
    <div>
        <draggable v-model="modelValue" item-key="index" :disabled="!can_drag" class="space-y-2" handle=".drag-handle">
            <template #item="{ element: field, index }">
                <div class="py-1">
                    <Disclosure v-slot="{ open }">
                        <DisclosureButton
                            class="flex w-full items-center justify-between bg-gray-100 px-4 py-2 text-left text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-none focus-visible:ring focus-visible:ring-gray-500/75">
                            <span class="drag-handle cursor-move">☰</span>
                            <span>{{ order_name }} {{ index + 1 }}</span>
                            <FontAwesomeIcon v-if="can_delete" :icon="faTrash" class="text-red-500 hover:text-red-700"
                                @click.stop="removeValue(index)" />
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            <SideEditorArrayEdit 
                                :modelValue="modelValue[index]" 
                                :uploadRoutes="uploadRoutes" 
                                :blueprint="blueprint"  
                                @update:model-value="(data) => onChangeProperty(index, data)" 
                            />
                        </DisclosurePanel>
                    </Disclosure>
                </div>
            </template>
        </draggable>
        <div v-if="can_add" class="my-2">
            <Button type="dashed" :label="`Add ${order_name}`" :icon="faPlus" full @click="addValue"></Button>
        </div>
    </div>
</template>

<style scoped></style>
