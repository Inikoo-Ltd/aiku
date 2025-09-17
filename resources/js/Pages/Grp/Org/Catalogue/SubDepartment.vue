<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, router, Link} from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder, faMoneyBillWave, faProjectDiagram, faTag, faUser, faBrowser
} from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, ref,inject } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableMailshots from "@/Components/Tables/TableMailshots.vue"
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import { capitalize } from "@/Composables/capitalize"
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import ProductsSelector from '@/Components/Dropshipping/ProductsSelector.vue'
import { notify } from '@kyvg/vue3-notification'
import SubDepartmentShowcase from "@/Components/Shop/SubDepartmentShowcase.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import Image from '@/Components/Image.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { routeType } from '@/types/route'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faOctopusDeploy } from '@fortawesome/free-brands-svg-icons'
import ImagesManagement from '@/Components/Goods/ImagesManagement.vue'
import Breadcrumb from 'primevue/breadcrumb'

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faDiagramNext,faBrowser
)

const locale = inject('locale', aikuLocaleStructure)

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    }

    routes: {
        fetch_families: routeType
        attach_families: routeType
        detach_families: routeType
    }
    url_master : routeType
    showcase: {

    }
    mini_breadcrumbs?: any[]
    customers: {}
    mailshots: {}
    products: {}
    history?: {}
    images?:object
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component: Component = computed(() => {
    const components = {
        showcase: SubDepartmentShowcase,
        products: TableProducts,
        mailshots: TableMailshots,
        customers: TableCustomers,
        details: ModelDetails,
        history: TableHistories,
        images :ImagesManagement
    }
    return components[currentTab.value]

});

// Method: Submit the family
const isOpenModalPortfolios = ref(false)
const isLoadingSubmit = ref(false)
const onSubmitAddItem = async (idProduct: number[]) => {
    router.post(route(props.routes.attach_families.name, props.routes.attach_families.parameters ),
    {
        families_id: idProduct
    },
    {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            notify({
                title: "Something went wrong.",
                text: error.products || undefined,
                type: "error"
            })
        },
        onSuccess: () => {
            router.reload({only: ['data']})
            notify({
                title: trans("Success!"),
                text: trans("Successfully added portfolios"),
                type: "success"
            })
            isOpenModalPortfolios.value = false
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button @click="() => isOpenModalPortfolios = true" :label="trans('Add families')" icon="fas fa-plus" />
        </template>

        <template #afterTitle>
           <div class="whitespace-nowrap">
            <Link v-if="url_master"  :href="route(url_master.name,url_master.parameters)"  v-tooltip="'Go to Master'" class="mr-1"  :class="'opacity-70 hover:opacity-100'">
                <FontAwesomeIcon
                    :icon="faOctopusDeploy"
                    color="#4B0082"
                />
            </Link>
            </div>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
     <div v-if="mini_breadcrumbs" class="bg-white shadow-sm rounded px-4 py-2 mx-4 mt-2 w-fit border border-gray-200 overflow-x-auto">
        <Breadcrumb  :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <!-- Breadcrumb link or text -->
                    <component :is="item.to ? Link : 'span'" :href="route(item.to.name,item.to.parameters)" v-tooltip="item.tooltip"
                        :title="item.title" class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span class="truncate max-w-[150px]">{{ item.label || '-' }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>

    <Modal v-if="true" :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-6xl">
        <ProductsSelector
            :headLabel="trans('Add Family to portfolios')"
            :route-fetch="routes.fetch_families"
            :isLoadingSubmit
            @submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id))"
        >
            <template #product="{ item }">
                <Image v-if="item.image" :src="item.image" class="w-16 h-16 overflow-hidden" imageCover :alt="item.name" />
                <div class="flex flex-col justify-between">
                    <div class="w-fit">
                        <div v-tooltip="trans('Name')" class="w-fit font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
                        <div v-if="!item.no_code" v-tooltip="trans('Code')" class="w-fit text-xs text-gray-400 italic">{{ item.code || 'no code' }}</div>
                        <div v-if="item.reference" v-tooltip="trans('Reference')" class="w-fit text-xs text-gray-400 italic">{{ item.reference || 'no reference' }}</div>
                        <div v-if="item.gross_weight" v-tooltip="trans('Weight')" class="w-fit text-xs text-gray-400 italic">{{ item.gross_weight }}</div>
                    </div>
                    <div v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
                        {{ locale?.number(item.number_current_products || 0) }} {{ trans("products") }}
                    </div>
                </div>
            </template>
        </ProductsSelector>
    </Modal>
</template>
