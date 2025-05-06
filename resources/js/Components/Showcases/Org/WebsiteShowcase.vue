<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 12:36:21 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faGlobe, faLink, faPencil } from "@fal";
import { ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { trans } from "laravel-vue-i18n";

library.add(faGlobe, faLink);

const props = defineProps<{
  data: {
    slug: string
    url: string
    domain: string
    state: string
    status: string
    created_at: string
    updated_at: string
    layout: string
  },
}>();

const links = ref([
  { label: trans("Edit Header"), route_target: props.data.layout.headerRoute, icon: faPencil },
  { label: trans("Edit Menu"), route_target: props.data.layout.menuRoute, icon: faPencil },
  { label: trans("Edit Footer"), route_target: props.data.layout.footerRoute, icon: faPencil }
]);

</script>
<template>
  <!-- Box: Url and Buttons in a single row -->
  <div class="px-6 py-12 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- URL Box -->
      <div class="bg-white w-fit h-fit flex items-center gap-x-3 md:w-96">
        <a :href="props.data.url" target="_blank"
           v-tooltip="trans('Go To Website')"
           class="hover:bg-gray-50 ring-1 ring-gray-300 cursor-pointer rounded overflow-hidden flex text-xxs md:text-base text-gray-500">
          <div class="bg-gray-200 py-2 px-2">
            <FontAwesomeIcon :icon="faGlobe" class="px-1" aria-hidden="true" />
          </div>
          <div class="flex items-center px-4">
            {{ props.data.url }}
          </div>
        </a>
      </div>


      <!-- Buttons Card (in the right part of the grid) -->
      <div class="bg-white flex justify-end">
        <div class="w-64 border border-gray-300 rounded-md p-2">
          <div v-for="(item, index) in links" :key="index" class="p-2">
            <ButtonWithLink
              :routeTarget="item.route_target"
              full
              :icon="item.icon"
              :label="item.label"
              type="secondary"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
