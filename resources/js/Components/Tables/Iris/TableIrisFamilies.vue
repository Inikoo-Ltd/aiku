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
                <Image :src="item['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
        <template #cell(state)="{ item: family }">
            <Icon :data="family.state" />
        </template>
        <template #cell(current_products)="{ item: family }">
            {{ family["current_products"] }}
        </template>

         <template #cell(code)="{ item: department }">
            <span class="primaryLink" @click="$emit('select-department', department.slug)">
                {{ department.code }}
            </span>
        </template>

        <!-- Column: Department code -->
        <template #cell(department_code)="{ item: family }">
            {{ family["department_code"] }}
        </template>

        <!-- Column: Department name -->
        <template #cell(department_name)="{ item: family }">
            {{ family["department_name"] }}
        </template>
    </Table>
</template>
