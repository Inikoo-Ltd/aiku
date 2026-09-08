<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import Icon from "@/Components/Icon.vue";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Tag from "@/Components/Tag.vue";
import { Department } from "@/types/department";
import Image from "@common/Components/Image.vue";
import { GridProducts } from "@/Components/Product"
import { faExternalLink } from "@far";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

library.add(faSeedling);

defineProps<{
  data: {}
  tab?: string
}>();

function departmentRoute(department: Department): string {
  const current = route().current()
  if (current === "retina.catalogue.sub_departments.index") {
    return route("retina.catalogue.departments.show", [department.slug])
  }
  return route("retina.catalogue.departments.show", [department.slug])
}


</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5 hidden md:block">
     <template #cell(image)="{ item: product }">
            <div class="flex justify-center">
                <Image :src="product['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
    </template>
    <template #cell(state)="{ item: department }">
      <Tag :label="department.state.label" v-tooltip="department.state.label">
        <template #label>
          <Icon :data="department.state" /> <span :class="department.state.class">{{ department.state.label }}</span>
        </template>
      </Tag>
    </template>
    <template #cell(code)="{ item: department }">
      <Link :href="departmentRoute(department)" class="primaryLink">
        {{ department["code"] }}
      </Link>
    </template>
    <template #cell(number_current_families)="{ item: department }">
      {{ department["number_current_families"] }}
    </template>
    <template #cell(number_current_sub_departments)="{ item: department }">
      {{ department["number_current_sub_departments"] }}
    </template>
    <template #cell(number_current_collections)="{ item: department }">
      {{ department["number_current_collections"] }}
    </template>
    <template #cell(number_current_products)="{ item: department }">
      {{ department["number_current_products"] }}
    </template>
  </Table>

  <GridProducts :resource="data" :preserve-scroll="true" class="mt-5 md:hidden" :name="tab"
        :gridClass="'grid grid-cols-1'">
        <template #card="{ item }">
            <div
                class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition-all hover:border-primary-300 hover:shadow-sm">
                <Image
                    :src="item.image_thumbnail ?? item.web_images?.main?.thumbnail ?? item.web_images?.main?.original"
                    class="h-12 w-12 rounded-full object-cover shadow-sm flex-shrink-0" />

                <div class="min-w-0 flex-1">
                    <Link :href="departmentRoute(item)" class="primaryLink">
                        {{ item.code }}
                    </Link>

                    <p class="mt-2 p-1 truncate text-sm text-gray-500">
                        {{ item.name }}
                    </p>
                </div>

                <a v-if="item.public_url" :href="item.public_url" target="_blank"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-primary-600"
                    title="Open public page">
                    <FontAwesomeIcon :icon="faExternalLink" />
                </a>
            </div>
        </template>
  </GridProducts>
</template>
