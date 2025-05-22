<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder, faMoneyBillWave, faProjectDiagram, faTag, faUser
} from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, defineAsyncComponent, ref } from "vue"
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
import { inject } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import Button from '@/Components/Elements/Buttons/Button.vue'

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faDiagramNext,
)

const layout = inject('layout', layoutStructure)
const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    }
    customers: object
    mailshots: object
    products: object
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
        history: ModelChangelog,
    }
    return components[currentTab.value]

});

// Method: Submit the family
const isOpenModalPortfolios = ref(false)
const isLoadingSubmit = ref(false)
const onSubmitAddItem = async (idProduct: number[], customerSalesChannelId: number) => {
    // router.post(route('grp.models.customer_sales_channel.portfolio.store_multiple_manual', { customerSalesChannel: customerSalesChannelId} ), {
    //     items: idProduct
    // }, {
    //     onBefore: () => isLoadingSubmit.value = true,
    //     onError: (error) => {
    //         notify({
    //             title: "Something went wrong.",
    //             text: error.products || undefined,
    //             type: "error"
    //         })
    //     },
    //     onSuccess: () => {
    //         router.reload({only: ['data']})
    //         notify({
    //             title: trans("Success!"),
    //             text: trans("Successfully added portfolios"),
    //             type: "success"
    //         })
    //         isOpenModalPortfolios.value = false
    //     },
    //     onFinish: () => isLoadingSubmit.value = false
    // })
}
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button @click="() => isOpenModalPortfolios = true" label="xxx" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>

    <!-- {{ layout?.currentParams }} -->
    <Modal v-if="true" :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-6xl">
        <ProductsSelector
            :headLabel="trans('Add products to portfolios')"
            :route-fetch="{
                name: 'grp.json.shop.catalogue.departments.families',
                parameters: {
                    shop: layout?.currentParams?.shop,
                    productCategory: layout?.currentParams?.department,
                }
            }"
            :isLoadingSubmit
            @submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id))"
        >
        </ProductsSelector>
    </Modal>
</template>
