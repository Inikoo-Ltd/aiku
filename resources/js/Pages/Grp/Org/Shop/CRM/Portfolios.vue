<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TablePortfolios from '@/Components/Tables/Grp/Org/CRM/TablePortfolios.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { inject, ref, watch } from 'vue'
import axios from 'axios'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import Tag from '@/Components/Tag.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faArrowRight } from "@fas"
import { debounce } from 'lodash'

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    customer: {}
}>()

const layout = inject('layout', layoutStructure)
const locale = inject('locale', null)

const isLoadingSubmit = ref(false)
const isLoadingFetch = ref(false)
const selectedPortfolio = ref<number | null>(null)
const errorMessage = ref<any>(null)

// Method: Get portfolios list
const queryPortfolio = ref('')
const portfoliosList = ref([])
const portfoliosMeta = ref()
const portfoliosLinks = ref()
const getPortfoliosList = async (url?: string) => {
    isLoadingFetch.value = true
    try {
        const urlToFetch = url || route('grp.org.shops.show.crm.customers.show.portfolios.filtered-products', {
            "organisation": layout?.currentParams?.organisation,
            "shop": layout?.currentParams?.shop,
            "customer": layout?.currentParams?.customer,
            "filter[global]": queryPortfolio.value
        })
        const response = await axios.get(urlToFetch)
        // filter[global]=GATOR GRIPZ%20
        portfoliosList.value = response.data.data
        portfoliosMeta.value = response?.data.meta || null
        portfoliosLinks.value = response?.data.links || null
        isLoadingFetch.value = false
    } catch {
        isLoadingFetch.value = false
        notify({
            title: "Something went wrong.",
            text: "Error while get the products list.",
            type: "error"
        })
    }
}

// Method: Submit the selected item
const onSubmitAddItem = async (close: Function, idProduct: number) => {
    router.post(route('grp.models.customer.portfolio.store', { customer: props.customer?.id} ), {
        products: idProduct
    }, {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            errorMessage.value = error
            notify({
                title: "Something went wrong.",
                text: error.products || undefined,
                type: "error"
            })
        },
        onSuccess: () => {
            router.reload({only: ['data']}),
            close()
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}

const selectedProduct = ref<{}[]>([])
const selectProduct = (item: any) => {
    const index = selectedProduct.value?.indexOf(item);
    if (index === -1) {
        selectedProduct.value?.push(item);
    } else {
        selectedProduct.value?.splice(index, 1);
    }
}
    
const isOpenModalPortfolios = ref(false)
watch(isOpenModalPortfolios, (newVal) => {
    if (newVal) {
        getPortfoliosList()
    }
})

const debounceGetPortfoliosList = debounce(() => getPortfoliosList(), 500)
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                @click="() => isOpenModalPortfolios = true"
                :type="'secondary'"
                icon="fal fa-plus"
                :xxstooltip="'action.tooltip'"
                :label="trans('Add portfolios')"
            />
        </template>
    </PageHeading>

    <TablePortfolios :data="data" />

    <Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" class="w-full max-w-5xl">
        <div class="">
            
            <div class="mb-2">
                <PureInput
                    v-model="queryPortfolio"
                    @update:modelValue="() => debounceGetPortfoliosList()"
                    :placeholder="trans('Input to search')"
                />
            </div>
            
            <div class="h-[500px] grid grid-cols-5 text-base font-normal">
                <div class="overflow-y-auto bg-gray-200 rounded h-full px-3 py-1">
                    <div class="font-semibold text-lg py-1">{{ trans("Suggestions") }}</div>
                    <div class="border-t border-gray-300 mb-1"></div>
                </div>

                <div class="col-span-4 pb-2 px-4 h-fit overflow-auto flex flex-col">
                    <div class="font-semibold text-lg py-1">{{ trans("Product") }} ({{ locale?.number(portfoliosMeta?.total || 0) }})</div>
                    <div class="border-t border-gray-300 mb-1"></div>
                    <div class="h-[400px] overflow-auto py-2 relative">
                        <!-- Products list -->
                        <div class="grid grid-cols-3 gap-3">
                            <template v-if="!isLoadingFetch">
                                <template v-if="portfoliosList.length > 0">
                                    <div
                                        v-for="(item, index) in portfoliosList"
                                        :key="index"
                                        @click="() => selectProduct(item)"
                                        class="h-fit rounded cursor-pointer p-2 flex gap-x-2 border"
                                        :class="selectedProduct.includes(item) ? 'bg-indigo-100 border-indigo-300' : 'bg-white hover:bg-gray-200 border-transparent'"
                                    >
                                        <img :src="item.imageSrc" class="w-16 h-16 object-cover" alt="" />
                                        <div class="flex flex-col justify-between">
                                            <div>
                                                <div class="font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
                                                <div class="text-xs text-gray-400 italic">{{ item.code || 'no code' }}</div>
                                            </div>
                                            <div class="text-xs text-gray-500">{{ item.price || 'no price' }}</div>
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="text-center text-gray-500 col-span-3">
                                    {{ trans("No products found") }}
                                </div>
                            </template>

                            <div
                                v-else
                                v-for="(item, index) in 6"
                                :key="index"
                                class="rounded cursor-pointer w-full h-20 flex gap-x-2 border skeleton"
                            >
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="portfoliosLinks?.previous || portfoliosLinks?.next" class="mt-2 mx-auto flex justify-center gap-x-2">
                            <div :class="{
                                'cursor-not-allowed text-gray-400': !portfoliosLinks?.previous,
                                'text-gray-700 hover:text-gray-500 cursor-pointer': portfoliosLinks?.previous
                            }" :dusk="portfoliosLinks?.previous ? 'pagination-simple-previous' : null"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md bg-white"
                                @click="() => portfoliosLinks?.previous ? getPortfoliosList(portfoliosLinks?.previous) : false"
                            >
                                <FontAwesomeIcon :icon="faArrowLeft" class="" fixed-width aria-hidden="true" />
                                <span class="hidden sm:inline ml-2">{{ trans('Prev') }}</span>
                            </div>

                            <div class="flex items-center">{{ portfoliosMeta?.current_page }}/{{portfoliosMeta?.from}}</div>

                            <div :class="{
                                'cursor-not-allowed text-gray-400': !portfoliosLinks?.next,
                                'text-gray-700 hover:text-gray-500 cursor-pointer': portfoliosLinks?.next
                            }" :dusk="portfoliosLinks?.next ? 'pagination-simple-previous' : null"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md bg-white"
                                @click="() => portfoliosLinks?.next ? getPortfoliosList(portfoliosLinks?.next) : false"
                            >
                                <span class="hidden sm:inline ml-2">{{ trans('Next') }}</span>
                                <FontAwesomeIcon :icon="faArrowRight" class="" fixed-width aria-hidden="true" />
                            </div>
                        </div>


                        <TransitionGroup name="list" tag="ul" class="mt-2 flex flex-wrap gap-x-2 gap-y-1">
                            <li
                                v-for="product in selectedProduct"
                                :key="product.id"
                            >
                                <Tag
                                    :label="product.name"
                                    closeButton
                                    noHoverColor
                                    @onClose="() => {
                                        selectProduct(product)
                                    }"
                                />
                            </li>
                        </TransitionGroup>
                    </div>
                    
                    <div class="mt-4">
                        <Button
                            @click="() => onSubmitAddItem(() => isOpenModalPortfolios = false, selectedProduct.map(item => item.id))"
                            :disabled="selectedProduct.length < 1"
                            v-tooltip="selectedProduct.length < 1 ? trans('Select at least one product') : ''"
                            :label="`${trans('Submit')} (${selectedProduct.length} ${trans('portfolios')})`"
                            type="primary"
                            full
                        />
                        
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>