<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 May 2025 17:37:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router, Link} from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TablePortfolios from "@/Components/Tables/Grp/Org/CRM/TablePortfolios.vue"
import TablePortfoliosShopify from "@/Components/Tables/Grp/Org/CRM/TablePortfoliosShopify.vue"
import TablePortfoliosManual from "@/Components/Tables/Grp/Org/CRM/TablePortfoliosManual.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { trans } from "laravel-vue-i18n"
import ProductsSelector from "@/Components/Dropshipping/ProductsSelector.vue"
import { notify } from "@kyvg/vue3-notification"
import { Customer } from "@/types/customer"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBookmark } from "@fal"

library.add(faBookmark)

defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    customer: Customer
    is_show_add_products_modal: boolean
    customerSalesChannelId: number
    platform: {}
    customerSalesChannel: {}
}>()


const isOpenModalPortfolios = ref(false)


// Method: Submit the selected item
const isLoadingSubmit = ref(false)
const onSubmitAddItem = async (idProduct: number[], customerSalesChannelId: number) => {
    router.post(route("grp.models.customer_sales_channel.portfolio.store_multiple_manual", { customerSalesChannel: customerSalesChannelId }), {
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
            router.reload({ only: ["data"] })
            notify({
                title: trans("Success!"),
                text: trans("Successfully added the portfolio"),
                type: "success"
            })
            isOpenModalPortfolios.value = false
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}
const loadingCreateNewProducts = ref(false)
const loadingMatchWithExistingProduct= ref(false)
const selectedProducts = ref<number[]>([])
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template v-if="is_show_add_products_modal" #other>
            <Button @click="() => isOpenModalPortfolios = true" :type="'secondary'" icon="fal fa-plus"
                :label="trans('Add products to portfolio')" />
        </template>

        <template #button-match-with-existing-product="{ action }">
            <template v-if="action && selectedProducts.length > 0">
                <Link :href="route(action.route.name, action.route?.parameters)" :method="action.route?.method || 'get'"
                    as="button" :data="{ portfolios: selectedProducts }" @start="loadingMatchWithExistingProduct = true"
                    @finish="loadingMatchWithExistingProduct = false">
                <Button :type="action.style" :label="action.label" />
                </Link>
            </template>
        </template>


        <template #button-create-new-product="{ action }">
            <Link v-if="selectedProducts.length > 0" @start="loadingCreateNewProducts = true"
                @finish="loadingCreateNewProducts = false" :href="route(action.route.name, action.route?.parameters)"
                :method="action.route?.method || 'get'" as="button" :data="{ portfolios : selectedProducts }">
            <Button :type="action.style" :label="action.label"/>
            </Link>
        </template>


    </PageHeading>

    <TablePortfoliosShopify v-if="platform.type === 'shopify'" :data="data" :customerSalesChannel
        v-model:selectedProducts="selectedProducts" />
    <TablePortfoliosManual v-else-if="platform.type === 'manual'" :data="data" :customerSalesChannel
        v-model:selectedProducts="selectedProducts" />
    <TablePortfolios v-else :data="data" :customerSalesChannel v-model:selectedProducts="selectedProducts" />


    <Modal v-if="is_show_add_products_modal" :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false"
        width="w-full max-w-6xl">
        <ProductsSelector :headLabel="trans('Add products to portfolios')" :route-fetch="{
                name: 'grp.json.products_for_portfolio_select',
                parameters: {
                    customerSalesChannel: customerSalesChannelId
                }
            }" :isLoadingSubmit
            @submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id), customerSalesChannelId)">
        </ProductsSelector>
    </Modal>
</template>