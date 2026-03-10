<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { trans } from 'laravel-vue-i18n'

import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject } from 'vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    url_master?: routeType
    currency_code: string

    data: {

    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const getCategoryLink = (productCategory: {}) => {
    if (productCategory) {
        return route('grp.org.shops.show.catalogue.families.show', {
            organisation: route().params.organisation,
            shop: route().params.shop,
            family: productCategory.slug,
        })
    }
    return '#'
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <div class="whitespace-nowrap">
                <Link v-if="url_master?.name" :href="route(url_master.name,url_master.parameters)" v-tooltip="trans('Go to Master Family section Offer GR/Vol')" class="mr-1 opacity-70 hover:opacity-100">
                    <FontAwesomeIcon
                        icon="fab fa-octopus-deploy"
                        color="#4B0082"
                        fixed-width
                    />
                </Link>
            </div>
        </template>
    </PageHeading>

    <div>
        <div class="p-5 border-b border-gray-300 mb-4 flex flex-col items-center offer">
            
        </div>

        <div class="flex justify-between gap-8 mx-8">
            
        </div>
    </div>
</template>