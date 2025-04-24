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
import Popover from "@/Components/Popover.vue"
import { inject, ref } from 'vue'
import axios from 'axios'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'

defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
}>()

const layout = inject('layout', layoutStructure)

const isLoadingSubmit = ref(false)
const isLoadingFetch = ref(false)
const portfoliosList = ref([])
const selectedPortfolio = ref<number | null>(null)
const errorMessage = ref(null)

// Method: Get portfolios list
const getPortfoliosList = async () => {
    isLoadingFetch.value = true
    try {
        const response = await axios.get(route("grp.org.shops.show.crm.customers.show.portfolios.filtered-products", { "organisation": layout?.currentParams?.organisation, "shop": layout?.currentParams?.shop, "customer": layout?.currentParams?.customer }))

        portfoliosList.value = response.data.data
        isLoadingFetch.value = false
    } catch (error) {
        isLoadingFetch.value = false
        notify({
            title: "Something went wrong.",
            text: "Error while get the products list.",
            type: "error"
        })
    }
}

// Method: Submit the selected item
const onSubmitAddItem = async (url: string, close: Function, idProduct: number) => {
    router.post(url, {
        product_id: idProduct
    }, {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            errorMessage.value = error
            notify({
                title: "Something went wrong.",
                text: error.product_id || undefined,
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

    
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-index-0="{ action }">
        <!-- {{ action }} -->
            <Popover class="relative">
                <template #button>
                    <Button
                        @click="() => (portfoliosList.length ? '' : getPortfoliosList())"
                        :type="action.style"
                        :tooltip="action.tooltip"
                        :label="action.label"
                    />
                </template>

                <template #content="{ close }">
                    <div class="w-[250px]">
                        <div class="text-xs">Select item:</div>
                        <div>
                            <PureMultiselect
                                ref="_multiselectRef"
                                v-model="selectedPortfolio"
                                @update:modelValue="() => errorMessage = null"
                                :canClear="false"
                                :canDeselect="false"
                                searchable
                                label="code"
                                valueProp="id"
                                placeholder="Select item.."
                                :options="portfoliosList"
                                :noResultsText="isLoadingFetch ? 'loading...' : 'No Result'">
                            </PureMultiselect>
                        </div>

                        <div v-if="errorMessage?.product_id" class="text-red-500 italic text-xs mt-1">
                            {{ errorMessage.product_id }}
                        </div>
                        
                        <div class="flex justify-end mt-4">
                            <Button
                                @click="() => action.route?.name ? onSubmitAddItem(route(action.route?.name, action.route?.parameters), close, selectedPortfolio) : false"
                                type="primary"
                                tooltip="Move pallet"
                                :loading="isLoadingSubmit"
                                full
                                label="save"
                                :size="'xs'"
                                :key="'buttonSubmitPortfolio' + selectedPortfolio"
                                :disabled="!selectedPortfolio"
                            />
                        </div>
                    </div>
                </template>
            </Popover>
        </template>
    </PageHeading>

    <TablePortfolios :data="data" />
</template>