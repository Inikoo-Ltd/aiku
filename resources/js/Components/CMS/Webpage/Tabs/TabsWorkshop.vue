<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { ulid } from "ulid"
import { faCube, faLink } from "@fal";
import { faStar, faCircle } from "@fas";
import { faChevronCircleLeft, faChevronCircleRight } from "@far";
import { library } from "@fortawesome/fontawesome-svg-core";
import ColumnWebppage from "@/Components/CMS/Webpage/WorkshopComponentsHelper/ColumnWebppageWorkshop.vue"
import { getStyles } from "@/Composables/styles"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronCircleLeft,
  faChevronCircleRight
);

interface TabItem {
  id: string;
  name: string;
  data?: {
    modelValue?: any;
  };
}

interface ModelValue {
  tabs: TabItem[];
}

const props = defineProps<{
  modelValue: ModelValue;
  blockData?: Record<string, any>;
  screenType: "mobile" | "tablet" | "desktop";
  indexBlock?: number;
  webpageData?: any
}>();

const activeTab = ref(0);

const currentTab = computed(() => {
  return props.modelValue.tabs?.[activeTab.value];
});

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()

const key = ref(ulid())

watch(
  () => props.screenType,
  () => {
    key.value = ulid()
  }
)
</script>

<template>
   <div :id="modelValue?.id ? modelValue?.id : 'Tabs' + indexBlock" class="w-full pb-6 md:px-[50px]" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width: 'auto'
    }">
  <div v-if="modelValue?.tabs?.length">
    <!-- Tab Headers -->
    <div class="flex border-b">
      <button v-for="(tab, index) in modelValue.tabs" :key="tab.id" @click="activeTab = index"
        class="px-4 py-2 border-b-2 transition" :class="activeTab === index
            ? 'border-primary text-primary font-semibold'
            : 'border-transparent'
          ">
        {{ tab.name }}
      </button>
    </div>

    <!-- Tab Content -->
    <div class="p-4">

      <ColumnWebppage 
        v-model="currentTab" 
        :webpageData="webpageData" 
        :blockData="blockData" 
        :screenType="screenType"
        @update:modelValue="() => emits('autoSave')"
        :key="`col-${currentTab.name}-${key}`"
      />
    </div>
  </div>

  <div v-else>
    No tabs found
  </div>
  </div>
</template>

<style scoped></style>