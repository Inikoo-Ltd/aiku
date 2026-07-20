<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link, usePage } from "@inertiajs/vue3"
import Table from "../Tables/Table.vue"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core";

import { faYinYang, faDotCircle, faCheck,} from "@fal";
import Image from "@common/Components/Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExternalLink } from "@far";
import { GridProducts } from "@/Components/Product"



library.add(faCheck,faYinYang, faDotCircle)

const props = defineProps<{
    data: any
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    isCheckBox?: boolean
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
    <Table :resource="data" :name="tab" class="mt-5 hidden md:block">
        <template #cell(code)="{ item: department }">
            <Link
                :href="`/catalogue/family/${department.slug}`"
                class="primaryLink"
            >
                {{ department.code }}
            </Link>
        </template>
         <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image
                    :src="item.image_thumbnail ?? item.web_images?.main?.thumbnail ?? item.web_images?.main?.original"
                    class="w-6 aspect-square rounded-full overflow-hidden shadow"
                />
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

        <template #cell(public_url)="{ item: item }">
            <div class="flex justify-center">
                <a v-if="item.public_url" :href="item.public_url" target="_blank">
                    <FontAwesomeIcon :icon="faExternalLink" />
                </a>
            </div>
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
                    <Link :href="`/catalogue/family/${item.slug}`" class="primaryLink  truncate text-sm">
                        {{ item.code }}
                    </Link>

                    <p class="mt-2 p-1 truncate text-sm text-gray-500">
                        {{ item.name }}
                    </p>
                </div>

                <Tag :label="item.state.label" v-tooltip="item.state.label" class="flex-shrink-0">
                    <template #label>
                        <div class="flex items-center gap-1">
                            <Icon :data="item.state" />
                            <span :class="item.state.class">
                                {{ item.state.label }}
                            </span>
                        </div>
                    </template>
                </Tag>

                <a v-if="item.public_url" :href="item.public_url" target="_blank"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-primary-600"
                    title="Open public page">
                    <FontAwesomeIcon :icon="faExternalLink" />
                </a>
            </div>
        </template>
    </GridProducts>
</template>
