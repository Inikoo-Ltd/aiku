<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrash } from "@fal";
import ImagesProperty from "@/Components/Workshop/Properties/ImagesProperty.vue";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import draggable from "vuedraggable";

library.add(faPlus, faTrash);

const modelValue = defineModel<Array<Record<string, any>>>({ required: true });
const props = defineProps<{ uploadRoutes: routeType }>();

// Fungsi untuk generate ID unik
const generateId = () =>
  typeof crypto !== "undefined" && crypto.randomUUID
    ? crypto.randomUUID()
    : Math.random().toString(36).substring(2, 10);

// Update data gambar berdasarkan index
const onChangeProperty = (index: number, data: object) => {
  const newValue = modelValue.value.slice();
  newValue[index] = { ...newValue[index], ...data };
  modelValue.value = newValue;
};

// Tambah gambar baru
const addImage = () => {
  const newValue = modelValue.value.slice();
  newValue.push({
    id: generateId(),
    link_data: null,
    source: null,
  });
  modelValue.value = newValue;
};

// Hapus gambar berdasarkan index
const removeImage = (index: number) => {
  const newValue = modelValue.value.slice();
  newValue.splice(index, 1);
  modelValue.value = newValue;
};
</script>

<template>
  <div>
    <draggable
      v-model="modelValue"
      item-key="id"
      class="space-y-2"
      handle=".drag-handle"
    >
      <template #item="{ element: field, index }">
        <div class="py-1">
          <Disclosure v-slot="{ open }">
            <DisclosureButton
              class="flex w-full items-center justify-between bg-gray-100 px-4 py-2 text-left text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-none focus-visible:ring focus-visible:ring-gray-500/75"
            >
              <span class="drag-handle cursor-move text-gray-500">☰</span>
              <span>Image {{ index + 1 }}</span>
              <FontAwesomeIcon
                :icon="faTrash"
                class="text-red-500 hover:text-red-700"
                @click.stop="removeImage(index)"
              />
            </DisclosureButton>
            <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
              <ImagesProperty
                :modelValue="modelValue[index]"
                :uploadRoutes="uploadRoutes"
                @update:model-value="(data) => onChangeProperty(index, data)"
              />
            </DisclosurePanel>
          </Disclosure>
        </div>
      </template>
    </draggable>

    <div class="my-2">
      <Button
        type="dashed"
        label="Add image"
        :icon="faPlus"
        full
        @click="addImage"
      />
    </div>
  </div>
</template>

<style scoped></style>
