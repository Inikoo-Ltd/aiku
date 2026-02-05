<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import Tag from "@/Components/Tag.vue"
import { Link } from "@inertiajs/vue3";
import Image from "@/Components/Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExternalLink } from "@far";

const props = defineProps<{
    data: object
    tab?: string,
}>()



</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image :src="item.web_images.main" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
         <template #cell(code)="{ item: department }">
            <span class="primaryLink" @click="$emit('select-collection', department.id)">
                {{ department.code }}
            </span>
        </template>
        <template #cell(state)="{ item: product }">  
            <Tag :label="product.state.label" v-tooltip="product.state.label">
                <template #label>
                    <Icon :data="product.state" /> <span :class="product.state.class">{{ product.state.label }}</span>
                </template>
            </Tag>
        </template>

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

         <template #cell(family)="{ item }">
            <span class="font-medium">
                {{ item.family_name }}
            </span>
        </template>

          <template #cell(url)="{ item }">
           <a :href="item.canonical_url"> 
                <FontAwesomeIcon :icon="faExternalLink" />
           </a>
        </template>
    </Table>
</template>
