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
import Image from "@/Components/Image.vue";

library.add(faSeedling);

defineProps<{
    data: {}
    tab?: string
}>();


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(image)="{ item: product }">
            <div class="flex justify-center">
                <Image :src="product['image_thumbnail']"
                    class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>

        <template #cell(state)="{ item: department }">
            <Tag :label="department.state.label" v-tooltip="department.state.label">
                <template #label>
                    <Icon :data="department.state" /> <span :class="department.state.class">{{ department.state.label
                        }}</span>
                </template>
            </Tag>
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
</template>
