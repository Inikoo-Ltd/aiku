<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 May 2025 17:37:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TablePortfolios from '@/Components/Tables/Grp/Org/CRM/TablePortfolios.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { inject, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import { RouteParams } from '@/types/route-params'
import ProductsSelector from '@/Components/Dropshipping/ProductsSelector.vue'
import { notify } from '@kyvg/vue3-notification'

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    customer: {}
}>()

const layout = inject('layout', layoutStructure)
const locale = inject('locale', null)

const isOpenModalPortfolios = ref(false)


// Method: Submit the selected item
const isLoadingSubmit = ref(false)
const onSubmitAddItem = async (idProduct: number[], customerSalesChannelId: number) => {
    router.post(route('grp.models.customer_sales_channel.portfolio.store_multiple_manual', { customerSalesChannel: customerSalesChannelId} ), {
        items: idProduct
    }, {
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
        <template v-if="(route().params as RouteParams).platform === 'manual'" #other>
            <Button
                @click="() => isOpenModalPortfolios = true"
                :type="'secondary'"
                icon="fal fa-plus"
                :xxstooltip="'action.tooltip'"
                :label="trans('Add products to portfolios')"
            />
        </template>
    </PageHeading>
    
    <TablePortfolios :data="data" />

    <Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-6xl">
        <ProductsSelector
            :headLabel="trans('Add products to portfolios')"
            :route-fetch="{
                name: 'grp.org.shops.show.crm.customers.show.portfolios.filtered-products',
                parameters: {
                    organisation: layout?.currentParams?.organisation,
                    shop: layout?.currentParams?.shop,
                    customer: layout?.currentParams?.customer,
                }
            }"
            :isLoadingSubmit
            @submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id), props.data.data[0].customer_sales_channel_id)"
        >
        </ProductsSelector>
    </Modal>
</template>