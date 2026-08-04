<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core";
import { Link } from "@inertiajs/vue3";
import { faYinYang, faDotCircle, faCheck,} from "@fal";
import Image from "@common/Components/Image.vue";
import { GridProducts } from "@/Components/Product"


library.add(faCheck,faYinYang, faDotCircle)

const props = defineProps<{
    data: object
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    isCheckBox?: boolean
}>()



const emits = defineEmits<{
    (e: "selectedRow", value: {}): void
}>()


function familyRoute(family): string {
  const current = route().current()
  if (current === "retina.catalogue.families.index") {
    return route("retina.catalogue.families.show", [family.slug])
  }
  return route("retina.catalogue.families.show", [family.slug])
}


</script>

<template>
    <Table :resource="data" :name="tab"  class="mt-5 hidden md:block"> 
          <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image :src="item['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
        <template #cell(state)="{ item: family }">
            <Icon :data="family.state" />
        </template>
        <template #cell(code)="{ item: family }">
             <Link :href="familyRoute(family)" class="primaryLink">
            {{ family["code"] }}
            </Link>
        </template>
        <template #cell(shop_code)="{ item: family }">
            {{ family["shop_code"] }}
        </template>
        <template #cell(current_products)="{ item: family }">
            {{ family["current_products"] }}
        </template>

        <!-- Column: Department code -->
        <template #cell(department_code)="{ item: family }">
            {{ family["department_code"] }}
        </template>

        <!-- Column: Department name -->
        <template #cell(department_name)="{ item: family }">
            {{ family["department_name"] }}
        </template>

        <template #cell(sub_department_name)="{ item: family }">
            {{ family["sub_department_code"] }}
        </template>

         <template #cell(actions)="{ item: family }">
           <RetinaButtonAddPortofolio />
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
                     <Link :href="familyRoute(item)" class="primaryLink">
                        {{ item.code }}
                    </Link>

                    <p class="mt-2 p-1 truncate text-sm text-gray-500">
                        {{ item.name }}
                    </p>
                </div>
            </div>
        </template>
    </GridProducts>
</template>
