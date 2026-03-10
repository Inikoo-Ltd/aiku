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
import UnderConstruction from '@/Pages/Iris/Disclosure/UnderConstruction.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    url_master?: routeType
    currency_code: string

    data: {

    }
}>()


const layout = inject('layout', layoutStructure)
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

    <div v-if="layout.app.environment === 'production'">
        <UnderConstruction />
    </div>

    <div v-else>
        <div class="p-5 border-b border-gray-300 mb-4 flex flex-col items-center offer">
            
        </div>

        <div class="flex justify-between gap-8 mx-8">
            <!-- <div class="ml-4 max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
                <div class="bg-green-100 font-bold border-b border-gray-200 text-green-700 text-center mb-1 py-1">
                    Discounts
                </div>
                <div v-if="(typeof props.data.data_allowance_signature?.percentage_off !== 'undefined')" class="grid grid-cols-7 gap-x-4 items-center justify-between">
                    <dt class="col-span-4 flex flex-col">
                        <div class="flex items-center leading-none">
                            <span>Discount percentage</span>
                            <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'fieldSummary.information_icon'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                        </div>
                        <span v-tooltip="'fieldSummary.information'" class="text-xs text-gray-400 truncate">
                            The discount of the product price
                        </span>
                    </dt>
            
                    <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                        <dd class="">
                            {{ props.data.data_allowance_signature?.percentage_off * 100 }}%
                        </dd>
                    </div>
                </div>
            </div> -->
        </div>
    </div>
</template>