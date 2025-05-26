<script setup lang="ts">
import { ref, watch } from "vue";
import Modal from "@/Components/Utils/Modal.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faImage } from "@fas";
import { set } from "lodash";
import { trans } from "laravel-vue-i18n";
import { routeType } from "@/types/route";

library.add(faImage);

const props = defineProps<{ uploadRoutes: routeType }>();

type Layout = {
  name: string;
  layout_type: string;
  images: number;
  grid?: string;
  flex?: string[];
};

const model = defineModel<string|number>();
const isModalOpen = ref(false);
const layouts = ref<Layout[]>([
  { name: "Layout Single Wide Image", layout_type: "1", grid: "grid-cols-1", images: 1 },
  { name: "Layout Two Equal Images", layout_type: "2", grid: "grid-cols-2", images: 2 },
  { name: "Layout Three Equal Images", layout_type: "3", grid: "grid-cols-3", images: 3 },
  { name: "Layout Four Equal Images", layout_type: "4", grid: "grid-cols-4", images: 4 },
   { name: "Layout six Equal Images", layout_type: "6", grid: "grid-cols-6", images: 6 },
  { name: "Layout 12 (1/3, 2/3)", layout_type: "12", flex: ["w-1/3", "w-2/3"], images: 2 },
  { name: "Layout 21 (2/3, 1/3)", layout_type: "21", flex: ["w-2/3", "w-1/3"], images: 2 },
  { name: "Layout 13 (1/4, 3/4)", layout_type: "13", flex: ["w-1/4", "w-3/4"], images: 2 },
  { name: "Layout 31 (3/4, 1/4)", layout_type: "31", flex: ["w-3/4", "w-1/4"], images: 2 },
  { name: "Layout One Large, Two Small", layout_type: "211", flex: ["w-1/2", "w-1/4", "w-1/4"], images: 3 },
]);

const selectedLayout = ref<Layout | null>(layouts.value.find((layout) => layout.layout_type == model.value) || null);

watch(model, (newValue) => {
  selectedLayout.value = layouts.value.find((layout) => layout.layout_type === newValue) || null;
});

const onSubmitLayout = (layoutType: string) => {
  set(model, "value", layoutType);
  isModalOpen.value = false;
};
</script>

<template>
  <div class="w-full flex justify-center">
    <button @click="isModalOpen = true" class="relative flex w-full items-center justify-center p-4 border rounded-md">
      <div v-if="selectedLayout" class="w-full">
        <div v-if="selectedLayout.grid" :class="'grid gap-2 ' + selectedLayout.grid" class="h-14">
          <div v-for="(i, indexI) in selectedLayout.images" :key="i" class="text-2xl flex items-center justify-center font-bold text-gray-400 bg-gray-200 h-15 w-full border border-dashed rounded">
            {{ indexI+1 }}
          </div>
        </div>
        <div v-else class="flex gap-2 h-12">
          <div v-for="(flexClass, index) in selectedLayout.flex" :key="index" :class="flexClass"
            class="p-2 text-2xl flex items-center justify-center font-bold text-gray-400 bg-gray-200 h-15 text-center border border-dashed rounded-lg">
              {{ index+1 }}
          </div>
        </div>
        <div class="absolute inset-0 bg-black/50 text-white opacity-0 hover:opacity-100 flex items-center justify-center">
          {{ trans('Change layout') }}
        </div>
      </div>
      <div v-else>Change Layout</div>
    </button>
  </div>

  <Modal :isOpen="isModalOpen" @onClose="() => (isModalOpen = false)">
    <div class="h-auto px-2 overflow-auto">
      <div class="text-xl font-semibold mb-2">{{ trans("Select a Layout Type") }}</div>
      <div class="grid grid-cols-3 gap-4">
        <div v-for="layout in layouts" :key="layout.layout_type"
          class="p-2 cursor-pointer transition-all group relative"
          :class="{'border-blue-500 ring-2 rounded': model === layout.layout_type, 'hover:border-blue-300 hover:bg-gray-100': model !== layout.layout_type}">
          <div class="border rounded-lg p-4">
            <div v-if="layout.grid" :class="'grid gap-2 ' + layout.grid">
              <div v-for="(i, indexI) in layout.images" :key="i" class="p-2 text-2xl flex items-center justify-center font-bold text-gray-400  bg-gray-200 h-20 border border-dashed rounded-lg">
                {{ indexI+1 }}
              </div>
            </div>
            <div v-else class="flex gap-2">
              <div v-for="(flexClass, index) in layout.flex" :key="index" :class="flexClass"
                class="p-2 text-2xl flex items-center justify-center font-bold text-gray-400 bg-gray-200 h-20 border border-dashed rounded-lg">
                {{ index+1 }}
              </div>
            </div>
          </div>
          <div class="absolute inset-0 flex items-center justify-center bg-opacity-50 bg-black text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100">
            <Button label="Select" @click="() => onSubmitLayout(layout.layout_type)"
              class="transition-colors bg-blue-500 hover:bg-blue-600" />
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-x-2 mt-3">
        <Button label="Cancel" @click="() => (isModalOpen = false)" type="tertiary" />
      </div>
    </div>
  </Modal>
</template>
