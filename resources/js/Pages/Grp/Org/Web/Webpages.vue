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
import { faShapes, faSortAmountDownAlt, faSortAmountDown, faHome, faSignInAlt, faBooks, faColumns, faInfoCircle, faNewspaper, faFolderDownload, faSkull } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeadingTypes } from '@/types/PageHeading'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import Popover from '@/Components/Popover.vue'
import { trans } from 'laravel-vue-i18n'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { get } from 'lodash-es'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { ref, watch } from 'vue'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { ulid } from 'ulid'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTriangleExclamation } from '@fortawesome/free-solid-svg-icons'
library.add(faShapes, faSortAmountDownAlt, faSortAmountDown, faHome, faSignInAlt, faBooks, faColumns, faInfoCircle, faNewspaper, faFolderDownload)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    routes_list: {
        fetch_products_without_webpage?: routeType
        bulk_offline?: routeType
        submit_product_webpage: routeType
        fetch_live_webpages: routeType
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

const selectedWebpages = ref<object[]>([])
const redirectUrl = ref<string|null>(null)
const key = ref(ulid())
const processingBulkDelete = ref(false)

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

        <template #button-bulk-offline>
            <ModalConfirmation
                v-if="selectedWebpages.length && routes_list.bulk_offline"
                :noLabel="ctrans('Cancel')"
                :body="{
                    webpages: selectedWebpages,
                    redirect_id: redirectUrl
                }"
                :modalClass="'text-pretty scrollbar-none'"
                :dialogClass="'w-full text-pretty container'"
                :disableIconWarn="true"
                :allowOverflow="true"
                :successMessage="ctrans('Successfully set selected webpages as offline')"
                :routeYes="routes_list.bulk_offline"
                @finishedProcess="() => {
                    key = ulid();
                }"
                @modalClosedAction="() => {
                    redirectUrl = null; 
                    processingBulkDelete = false
                }"
            >
                <template #title>
                    <div class="text-red-500 font-semibold text-lg">
                        <FontAwesomeIcon
                            :icon="faTriangleExclamation"
                        />
                        {{ ctrans('Set as Offline') }}
                    </div>

                </template>
                <template #description>
                    <div class="flex text-sm mt-4 font-semibold">
                        {{ ctrans("Are you sure you want to do this? This would set all of these pages as offline and redirect them.") }}    
                    </div>
                    <div class="grid text-sm max-h-[10rem] overflow-x-hidden mt-2 mb-3" style="scrollbar-width: thin;">
                        <div v-for="webpage in selectedWebpages" class="grid grid-cols-8">
                            <span class="flex" :class="webpage.title ? 'col-span-3' : 'col-span-8'">
                                <span>
                                    • {{ webpage.code }}
                                </span>
                                <span class="ml-auto mr-2" v-if="webpage.title">
                                    |
                                </span>
                            </span>
                            <span class="col-span-5" v-if="webpage.title">
                                {{ webpage.title }}
                            </span>
                        </div>
                    </div>
                    <div class="w-full">
                        <div class="w-full text-sm font-semibold mb-1">
                            <span class="text-red-500">*</span> {{ ctrans('Redirect to') }}:
                        </div>
                        <PureMultiselectInfiniteScroll
                            v-model="redirectUrl"
                            :fetchRoute="{
                                ...routes_list.fetch_live_webpages,
                                body: {
                                    excluded_list: selectedWebpages   
                                }
                            }"
                            :placeholder="trans('Select Redirect')"
                            :required="true"
                            valueProp="id"
                            labelProp="url"
                            :disabled="processingBulkDelete"
                        >
                            <template #singlelabel="{ value }">
                                <div
                                    class="w-full text-left pl-3 pr-2 text-sm whitespace-nowrap truncate">
                                    {{ value.slug }}
                                    <span v-if="value.code" class="text-sm text-gray-400">(/{{ value.href }})</span>
                                </div>
                            </template>
                            <template #option="{ option, isSelected, isPointed }">
                                <!-- <pre>{{ option }}</pre> -->
                                <div class="">{{ option.slug }} <span v-if="option.code" class="text-sm"
                                    :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">(/{{ option.href }})</span>
                                </div>
                            </template>
                        </PureMultiselectInfiniteScroll>
                    </div>
                </template>
                <template #default="{ changeModel }">
                    <Button
                        :style="'negative'"
                        :icon="faSkull"
                        :label="ctrans('Set as Offline')"
                        @click="changeModel"
                    />
                </template>
                <template #btn-yes="{ isLoadingdelete, clickYes }">
                    <Button
                        :loading="isLoadingdelete"
                        :disabled="!redirectUrl"
                        :style="'negative'"
                        :icon="faSkull"
                        :label="ctrans('Confirm')"
                        @click="() => {
                            processingBulkDelete = true;
                            clickYes();
                        }"
                    />
                </template>
            </ModalConfirmation>
            <span v-else></span>
        </template>
    </PageHeading>
    <TableWebpages 
        :data="data" 
        v-model:selectedWebpages="selectedWebpages"
        :key="key"
    />
</template>
