<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 May 2025 17:37:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TablePortfolios from "@/Components/Tables/Grp/Org/CRM/TablePortfolios.vue"
import TablePortfoliosShopify from "@/Components/Tables/Grp/Org/CRM/TablePortfoliosShopify.vue"
import TablePortfoliosManual from "@/Components/Tables/Grp/Org/CRM/TablePortfoliosManual.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { ref, onMounted } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { trans } from "laravel-vue-i18n"
import ProductsSelector from "@/Components/Dropshipping/ProductsSelector.vue"
import { notify } from "@kyvg/vue3-notification"
import { Customer } from "@/types/customer"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBookmark } from "@fal"
import { ulid } from "ulid"
import {debounce} from "lodash-es"

library.add(faBookmark)

const props = defineProps<{
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
const loadingAction= ref([])
const selectedProducts = ref<number[]>([])
const key = ref(ulid())

const onSuccessEditCheckmark = () => {
  /*   router.reload({ only: ["data"] }) */
    key.value = ulid()
    notify({
        title: trans("Success!"),
        text: trans("Successfully added the portfolio"),
        type: "success",
    })
    selectedProducts.value = []
}

const onFailedEditCheckmark = (error: any) => {
    key.value = ulid()
    notify({
        title: "Something went wrong.",
        text: error?.products || "An error occurred.",
        type: "error",
    })
}

const submitPortfolioAction = (action: any) => {
    loadingAction.value.push(action.label)
    router.visit(route(action.route.name, action.route?.parameters), {
        method: action.route?.method || "get",
        data: { portfolios: selectedProducts.value },
        onSuccess: onSuccessEditCheckmark,
        onError: (error) => onFailedEditCheckmark(error),
        onFinish: () => {
          loadingAction.value = []
        }
    })
}


// Real-time Shopify Upload Listener
const debReloadPage = debounce(() => {
  router.reload({
    except: ['auth', 'breadcrumbs', 'flash', 'layout', 'localeData', 'pageHead', 'ziggy']
  })
}, 1200)

const selectShopifyEvent = (portfolio: { id: number }) => {
  return {
    event: `shopify.${props.customerSalesChannel?.platform_user_id}.upload-product.${portfolio.id}`,
    action: '.shopify-upload-progress',
  }
}

onMounted(() => {
  if (props.platform?.type === 'shopify') {
    props.data?.data?.forEach(portfolio => {
      const { event, action } = selectShopifyEvent(portfolio)
      if (event && action) {
        window.Echo.private(event).listen(action, (eventData) => {
          console.log('socket in: ', portfolio.id, eventData)
          if (!eventData?.errors_response) {
            debReloadPage()
          }
        })
      }
    })
  }
})
console.log(props)
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template v-if="is_show_add_products_modal" #other>
            <Button @click="() => isOpenModalPortfolios = true" :type="'secondary'" icon="fal fa-plus"
                :label="trans('Add products to portfolio')" />
        </template>

        <template #button-match-with-existing-product="{ action }">
            <Button v-if="selectedProducts.length > 0" :type="action.style" :label="action.label"
                 :loading="loadingAction.includes(action.label)"
                @click="() => submitPortfolioAction(action)" />
            <div v-else></div>
        </template>

        <template #button-create-new-product="{ action }">
            <Button v-if="selectedProducts.length > 0" :type="action.style" :label="action.label"
                :loading="loadingAction.includes(action.label)"
                @click="() => submitPortfolioAction(action)" />
            <div v-else></div>
        </template>
    </PageHeading>

    <TablePortfoliosShopify v-if="platform.type === 'shopify'" :data="data" :customerSalesChannel
        v-model:selectedProducts="selectedProducts" :key="key" />
    <TablePortfoliosManual v-else-if="platform.type === 'manual'" :data="data" :customerSalesChannel />
    <TablePortfolios v-else :data="data" :customerSalesChannel />


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