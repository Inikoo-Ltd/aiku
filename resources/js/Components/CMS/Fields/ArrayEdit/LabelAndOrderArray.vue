<script setup lang="ts">
import { ref, toRaw, inject, isRef } from "vue";
import Accordion from "primevue/accordion";
import AccordionPanel from "primevue/accordionpanel";
import AccordionHeader from "primevue/accordionheader";
import AccordionContent from "primevue/accordioncontent";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrashAlt } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import SideEditorArrayEdit from "./SideEditorArrayEdit.vue";
import draggable from "vuedraggable";
import { debounce } from "lodash-es";
import { routeType } from "@/types/route";
import { ulid } from "ulid"

library.add(faPlus, faTrashAlt)

const props = withDefaults(
  defineProps<{
    blueprint: any;
    uploadRoutes: routeType;
    order_name: string;
    can_drag: boolean;
    can_delete: boolean;
    can_add: boolean;
    new_value_data: object;
  }>(),
  {
    can_drag: true,
    can_delete: true,
    can_add: true,
  }
);

const emits = defineEmits<{
    (e: "update:modelValue", value: any): void
}>()

const injectedActive: any = inject("activeChildBlockArray", ref(null));
const activeChildBlockArrayBlock: any = inject("activeChildBlockArrayBlock", null);

const modelValue = defineModel({ required: true });
if (!modelValue.value) modelValue.value = [];

/* -----------------------
   Helpers for active state
   ----------------------- */
const readRaw = () => (isRef(injectedActive) ? injectedActive.value : injectedActive);
const isArrayMode = () => Array.isArray(readRaw());
const isOpen = (index: number) => {
  const raw = readRaw();
  if (Array.isArray(raw)) return raw.includes(index);
  return raw === index;
};
const setRaw = (next: any) => {
  if (isRef(injectedActive)) injectedActive.value = next;
  else injectedActive.value = next;
};
const toggleActive = (index: number) => {
  const raw = readRaw();
  if (Array.isArray(raw)) {
    const next = Array.from(raw);
    const pos = next.indexOf(index);
    if (pos === -1) next.push(index);
    else next.splice(pos, 1);
    setRaw(next);
  } else {
    setRaw(raw === index ? null : index);
  }
};

/* -----------------------
   CRUD and update
   ----------------------- */
const addValue = () => {
  // const newValue = toRaw(modelValue.value);
  // newValue.push(props.new_value_data);
  // modelValue.value = newValue;
  console.log('vxzxxx', props.new_value_data)
  const newValueData = {
    ...props.new_value_data,
    ulid: ulid()
  }
  modelValue.value?.push(newValueData);
  emits("update:modelValue", modelValue.value)

};

const removeValue = (index: number) => {
  // const newValue = toRaw(modelValue.value);
  // newValue.splice(index, 1);
  // modelValue.value = newValue;
  modelValue.value?.splice(index, 1)
  emits("update:modelValue", modelValue.value)

  const raw = readRaw();
  if (Array.isArray(raw)) {
    setRaw(raw.filter((i: number) => i !== index));
  } else if (raw === index) {
    setRaw(null);
  }
};

const updateProperty = (index: number, data: object) => {
  const newValue = toRaw(modelValue.value);
  newValue[index] = data;
  modelValue.value = newValue;
};
const onChangeProperty = debounce(updateProperty, 500);
</script>

<template>
  <div>
    <draggable
      v-model="modelValue"
      item-key="index"
      :disabled="!can_drag"
      class="space-y-2"
      handle=".drag-handle"
    >
      <template #item="{ element: field, index }">
        <Accordion :value="isOpen(index) ? [index] : []" :multiple="isArrayMode()" @update:value="toggleActive(index)"
          class="border border-gray-200 rounded-none">
          <template #collapseicon>
            <span></span>
          </template>
          <template #expandicon>
            <span></span>
          </template>
          <AccordionPanel :value="index">
            <AccordionHeader>
              <div class="flex items-center justify-between w-full gap-2">
                <div class="flex items-center gap-2">
                  <span class="drag-handle cursor-move text-gray-500 hover:text-gray-700">☰</span>
                  <span class="font-medium text-gray-700">{{ order_name }} {{ index + 1 }}</span>
                </div>
                <div>
                  <FontAwesomeIcon 
                    v-if="can_delete" 
                    :icon="faTrashAlt" 
                    class="text-red-500 hover:text-red-700 transition-colors duration-200"
                    @click.stop="removeValue(index)" 
                  />
                </div>
              </div>
            </AccordionHeader>

            <AccordionContent v-show="isOpen(index)" class="p-3 bg-gray-50 rounded-b">
              <SideEditorArrayEdit
                :key="field.ulid"
                :modelValue="modelValue[index]" 
                :uploadRoutes="uploadRoutes" 
                :blueprint="blueprint"
                @update:model-value="(data) => onChangeProperty(index, data)" 
              />
            </AccordionContent>
          </AccordionPanel>
        </Accordion>
      </template>
    </draggable>

    <div v-if="can_add" class="my-2">
      <Button type="dashed" :label="`Add ${order_name}`" :icon="faPlus" full @click="addValue" />
    </div>
  </div>
</template>

<style scoped>
.drag-handle {
  cursor: grab;
  font-size: 1rem;
}

:deep(.p-accordionheader) {
  @apply flex items-center justify-between bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded-none transition-colors duration-200 text-sm font-medium px-3 py-2;
}

</style>
