<script setup lang="ts">
import { computed } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage, faCheck, faTimesCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faInfoCircle } from "@far";

library.add(faAlbumCollection, faImage, faCheck, faTimesCircle);

const props = defineProps<{
  data?: {
    is_description_extra_reviewed?: string | null;
    is_description_title_reviewed?: string | null;
    is_description_reviewed?: string | null;
  };
}>();


console.log(props)

const isFilled = (val: unknown) =>
  val !== null && val !== undefined && String(val).trim().length > 0;

const status = computed(() => ({
  title: isFilled(props.data?.is_description_title_reviewed),
  description: isFilled(props.data?.is_description_reviewed),
  extra: isFilled(props.data?.is_description_extra_reviewed),
}));

const dotClass = (filled: boolean) =>
  filled ? "bg-green-100 text-green-600" : "bg-red-100 text-red-600";
const statusIcon = (filled: boolean) => (filled ? faCheck : faTimesCircle);
</script>

<template>
    <!-- Content Section -->
    <div class="border rounded p-4 space-y-4 text-sm text-gray-700">
      <!-- Status Row -->
      <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-50">
        <div
         
          class="flex-1 text-sm font-semibold text-gray-800 cursor-help"
        >
          Content Status  <FontAwesomeIcon :icon="faInfoCircle"  v-tooltip="'Filling all fields will improve page quality & SEO'" /> :  
        </div>
        <div class="flex gap-2">
          <div
            :class="[
              'flex items-center justify-center w-7 h-7 rounded-full',
              dotClass(status.title),
            ]"
          >
            <FontAwesomeIcon :icon="statusIcon(status.title)" v-tooltip="'Review description title'" />
          </div>
          <div
            :class="[
              'flex items-center justify-center w-7 h-7 rounded-full',
              dotClass(status.description),
            ]"
          >
            <FontAwesomeIcon :icon="statusIcon(status.description)"  v-tooltip="'Review description'" />
          </div>
          <div
            :class="[
              'flex items-center justify-center w-7 h-7 rounded-full',
              dotClass(status.extra),
            ]"
          >
            <FontAwesomeIcon :icon="statusIcon(status.extra)"  v-tooltip="'Review description extra'"/>
          </div>
        </div>
      </div>
    </div>
</template>
