<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { usePage } from "@inertiajs/vue3"
import Table from "../Tables/Table.vue";
import { Link } from "@inertiajs/vue3";
import Image from "@common/Components/Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExternalLink } from "@far";

const props = defineProps<{
    data: any
    tab?: string,
}>()

const page = usePage()

const labelMap: Record<string, string> = {
    department: 'Department',
    sub_department: 'Sub Department',
    family: 'Family',
    collection: 'Collection',
}

const parentInfo = computed(() => {
    const qs = page.url.split('?')[1] || ''
    const params = new URLSearchParams(qs)
    const parent = params.get('parent')
    if (!parent) return null

    return {
        label: labelMap[parent] ?? parent,
        code: params.get('parent_code') ?? '',
        name: params.get('parent_name') ?? '',
    }
})
</script>

<template>
    <div v-if="parentInfo" class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg mb-2">
        <span class="text-xs uppercase tracking-wide text-amber-700 font-semibold">{{ parentInfo.label }}:</span>
        <span class="text-sm font-medium text-gray-800" v-if="parentInfo.code">{{ parentInfo.code }}</span>
        <span class="text-sm text-gray-500" v-if="parentInfo.name">— {{ parentInfo.name }}</span>
    </div>
    <Table :resource="data" :name="tab" class="mt-5">
         <template #cell(code)="{ item: department }">
            <Link
                :href="route('iris.catalogue.sub_department.show', { subDepartment: department.slug })"
                class="primaryLink"
            >
                {{ department.code }}
            </Link>
        </template>
        <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image
                    :src="item.web_images?.main?.thumbnail ?? item.web_images?.main?.original"
                    class="w-6 aspect-square rounded-full overflow-hidden shadow"
                />
            </div>
        </template>
          <template #cell(department_code)="{ item }">
           <!--  <span class="text-gray-500 mr-1 text-xs">
                <Tag :label="item.department_code" v-tooltip="item.department_code">
                    <template #label>
                        {{ item.department_code }}
                    </template>
                </Tag>
            </span> -->
            <span class="font-medium">
                {{ item.department_name }}
            </span>
        </template>

        <template #cell(number_current_families)="{ item }">
            {{ item["number_current_families"] }}
        </template>
        <template #cell(number_current_collections)="{ item }">
            {{ item["number_current_collections"] }}
        </template>

        <template #cell(public_url)="{ item: item }">
            <div class="flex justify-center">
                <a v-if="item.public_url" :href="item.public_url" target="_blank">
                    <FontAwesomeIcon :icon="faExternalLink" />
                </a>
            </div>
        </template>


    </Table>
</template>
