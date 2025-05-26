<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { toRaw } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrash } from "@fal";
import ImagesProperty from "@/Components/Workshop/Properties/ImagesProperty.vue";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import draggable from "vuedraggable";

library.add(faPlus, faTrash);

const modelValue = defineModel<Array<object>>({ required: true });
const props = defineProps<{ uploadRoutes: routeType }>();

const onChangeProperty = (index: number, data: object) => {
    const newValue = toRaw(modelValue.value);
    newValue[index] = { ...newValue[index], ...data };
    modelValue.value = newValue;
};

const addImage = () => {
    const newValue = toRaw(modelValue.value);
    newValue.push({
        link_data: null,
        source: null,
    });
    modelValue.value = newValue;
};

const removeImage = (index: number) => {
    const newValue = toRaw(modelValue.value);
    newValue.splice(index, 1);
    modelValue.value = newValue;
};

</script>

<template>
    <div>
        <draggable v-model="modelValue" item-key="index" class="space-y-2" handle=".drag-handle">
            <template #item="{ element: field, index }">
                <div class="py-1">
                    <Disclosure v-slot="{ open }">
                        <DisclosureButton
                            class="flex w-full items-center justify-between bg-gray-100 px-4 py-2 text-left text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-none focus-visible:ring focus-visible:ring-gray-500/75">
                            <span class="drag-handle cursor-move">☰</span>
                            <span>Image {{ index + 1 }}</span>
                            <FontAwesomeIcon :icon="faTrash" class="text-red-500 hover:text-red-700" @click.stop="removeImage(index)" />
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                            <ImagesProperty :modelValue="modelValue[index]" :uploadRoutes="uploadRoutes"
                                @update:model-value="(data) => onChangeProperty(index, data)" />
                        </DisclosurePanel>
                    </Disclosure>
                </div>
            </template>
        </draggable>
        <div  class="my-2">
            <Button type="dashed" label="Add image" :icon="faPlus" full @click="addImage"></Button>
        </div>
    </div>
</template>

<style scoped></style>
