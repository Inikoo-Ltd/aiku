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

import { faYinYang, faDotCircle, faCheck,} from "@fal";
import Image from "@/Components/Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExternalLink } from "@far";


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



</script>

<template>
    <Table :resource="data" :name="tab">
         <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image :src="item.web_images.main" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
        <template #cell(current_products)="{ item: family }">
            {{ family["current_products"] }}
        </template>
        <!-- Column: Department name -->
        <template #cell(department_code)="{ item }">
            <span class="font-medium">
                {{ item.department_name }}
            </span>
        </template>

        <template #cell(sub_department)="{ item }">
            <span class="font-medium">
                {{ item.sub_department_name }}
            </span>
        </template>

           <template #cell(url)="{ item }">
           <a :href="`/${item.code}`"> 
                <FontAwesomeIcon :icon="faExternalLink" />
           </a>
        </template>
    </Table>
</template>
