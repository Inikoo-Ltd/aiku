<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue";
import SideEditorArrayEdit from "./SideEditorArrayEdit.vue";
import { ref, toRaw } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrash, faPen } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { debounce } from "lodash-es";
import draggable from "vuedraggable";
import Drawer from "primevue/drawer";
import { routeType } from "@/types/route";

// Add icons to FontAwesome
library.add(faPlus, faTrash, faPen);

// Props
const props = withDefaults(
  defineProps<{
    blueprint: any;
    uploadRoutes: routeType;
    order_name: string;
    can_drag?: boolean;
    can_delete?: boolean;
    can_add?: boolean;
    new_value_data: object;
  }>(),
  {
    can_drag: true,
    can_delete: true,
    can_add: true,
  }
);

const modelValue = defineModel<any[]>({ required: true });

if (!modelValue.value) modelValue.value = [];

// Drawer
const showDrawer = ref(false);
const activeIndex = ref<number | null>(null);

// Add, remove, and update functions
const addValue = () => {
  modelValue.value = [...toRaw(modelValue.value), props.new_value_data];
};

const removeValue = (index: number) => {
  const newValue = toRaw(modelValue.value);
  newValue.splice(index, 1);
  modelValue.value = newValue;
};

// Debounced property update
const updateProperty = (index: number, data: object) => {
  const newValue = toRaw(modelValue.value);
  newValue[index] = data;
  modelValue.value = newValue;
};
const onChangeProperty = debounce(updateProperty, 400);

const openEditor = (index: number) => {
  activeIndex.value = index;
  showDrawer.value = true;
};
</script>

<template>
  <div class="space-y-2">
    <draggable
      v-model="modelValue"
      item-key="index"
      :disabled="!can_drag"
      handle=".drag-handle"
      class="space-y-2"
    >
      <template #item="{ element: field, index }">
        <div
          class="flex items-center justify-between bg-gray-50 hover:bg-gray-100 
          px-3 py-2 rounded-lg border text-sm text-gray-800 transition-all"  @click="()=>openEditor(index)"
        >
          <div class="flex items-center gap-2">
            <span v-if="can_drag" class="drag-handle cursor-move text-gray-400">☰</span>
            <span class="font-medium">{{ order_name }} {{ index + 1 }}</span>
          </div>

          <div class="flex items-center gap-2">
            <FontAwesomeIcon
              v-if="can_delete"
              icon="fal fa-trash"
              class="text-red-500 hover:text-red-700 cursor-pointer"
              title="Delete"
              @click.stop="removeValue(index)"
            />
          </div>
        </div>
      </template>
    </draggable>

    <div v-if="can_add" class="pt-2">
      <Button
        type="dashed"
        :label="`Add ${order_name}`"
        :icon="faPlus"
        full
        @click="addValue"
      />
    </div>

    <!-- PrimeVue Drawer -->
    <Drawer
      v-model:visible="showDrawer"
      position="right"
      :modal="true"
      :header="`${ order_name } ${activeIndex + 1}`"
     :pt="{ root: { style: 'width: 30vw' } }"
    >
      <div v-if="activeIndex !== null">
        <SideEditorArrayEdit
          v-model="modelValue[activeIndex]"
          :blueprint="blueprint"
          :upload-routes="uploadRoutes"
          @update:modelValue="(val) => onChangeProperty(activeIndex!, val)"
        />
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
:deep(.p-drawer) {
  border-radius: 0 !important;
}
</style>
