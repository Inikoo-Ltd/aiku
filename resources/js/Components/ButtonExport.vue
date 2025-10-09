<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Popover } from "primevue";
import { ref, computed } from "vue";
import { route } from "ziggy-js";

import {
  faSyncAlt, faBracketsCurly, faPawClaws, faFileCsv, faFileExcel, faImages,
  faImage, faArrowLeft, faArrowRight, faUpload, faBox,
  faEllipsisV, faDownload, faTruck
} from "@fal";

library.add(faFileExcel, faBracketsCurly, faSyncAlt, faPawClaws, faImage, faSyncAlt, faBox, faArrowLeft, faArrowRight, faUpload, faDownload, faTruck, faImages, faFileCsv);

const props = defineProps<{
  data?: any;
}>();

const _popover = ref();

const flatRoutes = computed(() => {
  return props.data?.flatMap((item: any) => item.routes) || [];
});

const directRoutes = computed(() => flatRoutes.value.filter((r: any) => r.popover === false));
const popoverRoutes = computed(() => flatRoutes.value.filter((r: any) => r.inside_popover));

const downloadUrl = (routeObj: any) => {
  if (!routeObj?.name) return "#";
  return route(routeObj.name, routeObj.parameters || {});
};


</script>

<template>
  <div class="rounded-md flex items-center">
    <!-- Direct buttons -->
    <template v-for="(item, index) in directRoutes" :key="index">
      <a :href="downloadUrl(item.route)" target="_blank" rel="noopener">
        <Button :icon="['fal', item.icon?.[1] || 'fa-download']" :label="item.label" type="tertiary"
          :class="index === 0 ? 'rounded-r-none' : 'border-l-0 border-r-0 rounded-none'" />
      </a>
    </template>

    <!-- Popover trigger -->
    <template v-if="popoverRoutes.length">
      <Button
        @click="(e) => _popover?.toggle(e)"
        v-tooltip="trans('Open other export options')"
        :icon="faEllipsisV"
        class="!px-2 border-l-0 rounded-l-none h-full"
        type="tertiary"
      />

      <Popover ref="_popover">
        <div class="w-64 relative">
          <div class="text-sm mb-2">
            {{ trans("Select another download file type") }}:
          </div>

          <div class="flex flex-col gap-y-2">
            <template v-for="(item, idx) in popoverRoutes" :key="idx">
              <a :href="downloadUrl(item.route)" target="_blank" rel="noopener">
                <Button :icon="['fal', item.icon?.[1] || 'fa-download']" :label="item.label" full type="tertiary" />
              </a>
            </template>
          </div>
        </div>
      </Popover>
    </template>
  </div>
</template>
