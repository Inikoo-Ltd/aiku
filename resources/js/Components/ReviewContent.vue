<script setup lang="ts">
import { computed } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage, faCheck, faTimesCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { faInfoCircle } from "@far";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faAlbumCollection, faImage, faCheck, faTimesCircle, faInfoCircle);

const props = defineProps<{
  data?: {
    is_name_reviewed?: boolean | null;
    is_description_extra_reviewed?: boolean | null;
    is_description_title_reviewed?: boolean | null;
    is_description_reviewed?: boolean | null;
  };
}>();

console.log(props)

// Status mapping based on actual boolean values
const status = computed(() => ({
  name: props.data?.is_name_reviewed ?? false,
  title: props.data?.is_description_title_reviewed ?? false,
  description: props.data?.is_description_reviewed ?? false,
  extra: props.data?.is_description_extra_reviewed ?? false,
}));

// Styling helpers
const dotClass = (filled: boolean) =>
  filled ? "bg-green-100 text-green-600" : "bg-red-100 text-red-600";
const statusIcon = (filled: boolean) => (filled ? faCheck : faTimesCircle);
</script>

<template>
  <!-- Content Section -->
  <div class="border rounded p-4 space-y-4 text-sm text-gray-700">
    <!-- Status Row -->
    <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-50">
      <div class="flex-1 text-sm font-semibold text-gray-800 cursor-help">
        Content Status
        <FontAwesomeIcon
          :icon="faInfoCircle"
          v-tooltip="'Filling all fields will improve page quality & SEO'"
        />
        :
      </div>
      <div class="flex gap-2">
        <!-- Name -->
        <div
          :class="[
            'flex items-center justify-center w-7 h-7 rounded-full',
            dotClass(status.name),
          ]"
        >
          <FontAwesomeIcon
            :icon="statusIcon(status.name)"
            v-tooltip="'Review name'"
          />
        </div>

        <!-- Title -->
        <div
          :class="[
            'flex items-center justify-center w-7 h-7 rounded-full',
            dotClass(status.title),
          ]"
        >
          <FontAwesomeIcon
            :icon="statusIcon(status.title)"
            v-tooltip="'Review description title'"
          />
        </div>

        <!-- Description -->
        <div
          :class="[
            'flex items-center justify-center w-7 h-7 rounded-full',
            dotClass(status.description),
          ]"
        >
          <FontAwesomeIcon
            :icon="statusIcon(status.description)"
            v-tooltip="'Review description'"
          />
        </div>

        <!-- Extra -->
        <div
          :class="[
            'flex items-center justify-center w-7 h-7 rounded-full',
            dotClass(status.extra),
          ]"
        >
          <FontAwesomeIcon
            :icon="statusIcon(status.extra)"
            v-tooltip="'Review description extra'"
          />
        </div>
      </div>
    </div>
  </div>
</template>
