<script setup lang="ts">
import { ref, computed } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage, faCheck, faTimesCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { routeType } from "@/types/route";
import Image from "@/Components/Image.vue";
import { trans } from "laravel-vue-i18n"

library.add(faAlbumCollection, faImage, faCheck, faTimesCircle);

const props = defineProps<{
  data?: {
    name?: string | null;
    description?: string | null;
    description_title?: string | null;
    description_extra?: string | null;
    image?: string[] | null;
    url_master?: any;
    translation_box?: {
      title?: string;
      save_route?: routeType;
    } | null;
  };
}>();

const showExtra = ref(false);
const isFilled = (val: unknown) =>
  val !== null && val !== undefined && String(val).trim().length > 0;

const status = computed(() => ({
  description: isFilled(props.data?.description),
  extra: isFilled(props.data?.description_extra),
}));

</script>

<template>
  <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
    <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
      <slot name="image">
        <div class="w-full aspect-square" :class="props.data?.image ? '' : 'h-32'">
          <Image v-if="props.data?.image" :src="props.data.image"
            class="w-full h-full object-cover object-center rounded-t-lg" />
          <div v-else class="flex justify-center items-center bg-gray-100 w-full h-full">
            <FontAwesomeIcon :icon="faImage" class="w-10 h-10 text-gray-400" />
          </div>
        </div>
      </slot>
    </div>

    <!-- Content Section -->
    <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
      <div class="text-lg font-semibold text-gray-800">{{ data?.name }}</div>
      <div class="space-y-2">
        <div class="text-gray-600 leading-relaxed" v-if="status.description" v-html="props.data?.description"></div>
        <div v-if="showExtra" class="text-gray-600 leading-relaxed" v-html="props.data?.description_extra"></div>
        <button v-if="props.data?.description_extra" @click="showExtra = !showExtra"
          class="text-blue-500 text-xs font-medium hover:underline focus:outline-none">
          {{ showExtra ? trans("Show Less") : trans("Read More") }}
        </button>
      </div>
    </div>
  </div>
</template>
