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

const props = defineProps<{
    data: object
    tab?: string,
}>()



</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image :src="item['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
        <template #cell(state)="{ item: SubDepartment }">
            <Tag :label="SubDepartment.state.label" v-tooltip="SubDepartment.state.label">
                <template #label>
                    <Icon :data="SubDepartment.state" /> <span :class="SubDepartment.state.class">{{
                        SubDepartment.state.label
                        }}</span>
                </template>
            </Tag>
        </template>
    </Table>
</template>
