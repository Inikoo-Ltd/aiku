<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject } from "vue"
import { usePage, Link } from "@inertiajs/vue3"
import Table from "../Tables/Table.vue"
import Icon from "@/Components/Icon.vue"
import Tag from "@/Components/Tag.vue"
import Image from "@common/Components/Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExternalLink } from "@far";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure.js"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure.js"
import { GridProducts } from "@/Components/Product"

const props = defineProps<{
    data: any
    tab?: string,
}>()

const page = usePage()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

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
        <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image
                    :src="item.image_thumbnail ?? item.web_images?.main?.thumbnail ?? item.web_images?.main?.original"
                    class="w-6 aspect-square rounded-full overflow-hidden shadow"
                />
            </div>
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

        <template #cell(rrp)="{ item }">
            <div class="!text-right w-full">
                {{ locale.currencyFormat(layout.iris.currency.code, item.rrp) }}
            </div>
        </template>

        <template #cell(price)="{ item }">
            <div class="!text-right w-full">
                {{ locale.currencyFormat(layout.iris.currency.code, item.price) }}
            </div>
        </template>

        <template #cell(public_url)="{ item: item }">
            <div class="flex justify-center">
                <a v-if="item.public_url" :href="item.public_url ?? item.iris_url" target="_blank">
                    <FontAwesomeIcon :icon="faExternalLink" />
                </a>
            </div>
        </template>

        <template #cell(code)="{ item: item }">
            <a :href="item.canonical_url ?? item.iris_url" class="primaryLink"> 
                {{ item.code }}          
            </a>
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
                    <div  class="  truncate text-sm">
                        {{ item.code }}
                    </div>

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
