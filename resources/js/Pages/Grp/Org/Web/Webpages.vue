<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableWebpages from "@/Components/Tables/Grp/Org/Web/TableWebpages.vue"
import { capitalize } from "@/Composables/capitalize"
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faSignInAlt, faBooks, faColumns, faInfoCircle, faNewspaper } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Popover from '@/Components/Popover.vue'
import { trans } from 'laravel-vue-i18n'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { get } from 'lodash'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { ref } from 'vue'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faSignInAlt, faBooks, faColumns, faInfoCircle, faNewspaper)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    routes_list: {
        fetch_products_without_webpage: routeType
        submit_product_webpage: routeType
    }
}>()

const formAddWebpageProduct = useForm({ product_id: null })
const isLoadingData = ref<boolean>(false)
const onSubmitCreateWebpageProduct = (closedPopover: Function) => {
    formAddWebpageProduct.post(
        route(props.routes_list.submit_product_webpage.name, {
            product: formAddWebpageProduct.product_id,
        }),
        {
            preserveScroll: true,
            onStart: () => {
                isLoadingData.value = true
            },
            onSuccess: () => {
                closedPopover()
                formAddWebpageProduct.reset()
            },
            onError: (errors) => {
                notify({
                    title: trans('Something went wrong.'),
                    text: trans('Failed to create product webpage, please try again.'),
                    type: 'error',
                })
            },
            onFinish: () => {
                isLoadingData.value = false
            }
        }
    )
}
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">

        <template #button-product-webpage-create>
            <div class="relative">
                <Popover>
                    <template #button="{ open }">
                        <Button :label="trans('Product Webpage')" icon="fas fa-plus">

                        </Button>
                    </template>

                    <template #content="{ close: closed }">
                        <div class="w-[300px]">
                            <span class="text-xs px-1 my-2">{{ trans('Select product:') }}: </span>
                            <div class="">

                                <PureMultiselectInfiniteScroll
                                    v-model="formAddWebpageProduct.product_id"
                                    :fetchRoute="props.routes_list.fetch_products_without_webpage"
                                    :placeholder="trans('Select product')"
                                    valueProp="id"
                                    aoptionsList="(options) => dataServiceList = options">
                                    <template #singlelabel="{ value }">
                                        <!-- <div class="w-full text-left pl-4">
                                            {{ value.name }}
                                            <span
                                                class="text-sm text-gray-400">
                                                ({{
                                                    locale.currencyFormat(value.currency_code, value.price) }}/{{ value.unit
                                                }})
                                            </span>
                                        </div> -->
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <!-- <div class="">{{ option.name }} <span class="text-sm text-gray-400">({{
                                            locale.currencyFormat(option.currency_code, option.price) }}/{{
                                                option.unit }})</span></div> -->
                                    </template>
                                </PureMultiselectInfiniteScroll>

                                <p v-if="get(formAddWebpageProduct, ['errors', 'product_id'])"
                                    class="mt-2 text-sm text-red-500">
                                    {{ formAddWebpageProduct.errors.product_id }}
                                </p>
                            </div>

                            <div class="flex justify-end mt-3">
                                <Button
                                    @click="() => onSubmitCreateWebpageProduct(closed)"
                                    :style="'save'"
                                    :loading="isLoadingData"
                                    :disabled="!formAddWebpageProduct.product_id"
                                    :label="trans('Create')" full />
                            </div>

                            <!-- Loading: fetching service list -->
                            <div v-if="isLoadingData"
                                class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <LoadingIcon />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
        </template>
    </PageHeading>
    <TableWebpages :data="data" />
</template>
