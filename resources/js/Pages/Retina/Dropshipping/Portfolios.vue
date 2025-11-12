<script setup lang="ts">
import {Head, router} from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import {capitalize} from "@/Composables/capitalize";
import {computed, reactive, ref} from "vue";
import {PageHeading as PageHeadingTypes} from "@/types/PageHeading";
import {Tabs as TSTabs} from "@/types/Tabs";
import RetinaTablePortfoliosManual from "@/Components/Tables/Retina/RetinaTablePortfoliosManual.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import {notify} from "@kyvg/vue3-notification";
import {trans} from "laravel-vue-i18n";
import {routeType} from "@/types/route";
import {library} from "@fortawesome/fontawesome-svg-core";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import Modal from "@/Components/Utils/Modal.vue";
import AddPortfoliosWithUpload from "@/Components/Dropshipping/AddPortfoliosWithUpload.vue";
import AddPortfolios from "@/Components/Dropshipping/AddPortfolios.vue";
import {Message, Popover} from "primevue"
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome"
import {faSyncAlt, faHandPointer, faBan} from "@fas";
import { useFormatTime } from "@/Composables/useFormatTime";

import {
    faBracketsCurly, faPawClaws,
    faFileExcel,
    faImage,
    faArrowLeft,
    faArrowRight,
    faUpload,
    faBox,
    faEllipsisV,
    faDownload,
} from "@fal";
import axios from "axios"
import {Table as TableTS} from "@/types/Table"
import {CustomerSalesChannel} from "@/types/customer-sales-channel"
import RetinaTablePortfoliosPlatform from "@/Components/Tables/Retina/RetinaTablePortfoliosPlatform.vue"
import RetinaTablePortfoliosShopify from "@/Components/Tables/Retina/RetinaTablePortfoliosShopify.vue"
import {ulid} from "ulid";
import PlatformWarningNotConnected from "@/Components/Retina/Platform/PlatformWarningNotConnected.vue"
import PlatformWarningNotConnectedShopify from "@/Components/Retina/Platform/PlatformWarningNotConnectedShopify.vue"
import { ChannelLogo } from "@/Composables/Icon/ChannelLogoSvg"
import { useTruncate } from "@/Composables/useTruncate"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import TableRetinaPlatformPortfolioLogs from "@/Components/Tables/Retina/TableRetinaPlatformPortfolioLogs.vue";


library.add(faFileExcel, faBracketsCurly, faSyncAlt, faHandPointer, faPawClaws, faImage, faSyncAlt, faBox, faArrowLeft, faArrowRight, faUpload);


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    download_route: any
    grouped_portfolios: any
    is_closed: boolean
    content?: {
        portfolio_empty?: {
            title?: string,
            description?: string,
            separation?: string,
            sync_button?: string,
            add_button?: string
        }
    }
    products: TableTS
    logs: {}
    routes: {
        batch_upload: routeType
        batch_all: routeType
        match_match: routeType
        syncAllRoute: routeType
        batch_sync: routeType
        duplicate: routeType
        addPortfolioRoute: routeType
        bulk_upload: routeType
        bulk_unlink: routeType
        itemRoute: routeType
        updatePortfolioRoute: routeType
        batchDeletePortfolioRoute: routeType
        clonePortfolioRoute: routeType
    }
    platform_user_id: number
    step: {
        current: number
    }
    platform_data: {
        id: number
        code: string  // 'manual' | 'shopify' | 'ebay' | 'amazon'
        name: string
        type: string
    }
    is_platform_connected: boolean
    customer_sales_channel: CustomerSalesChannel
    channels: object
    count_product_not_synced: number

    // inactive: {}
    product_count: number
}>();

const step = ref(props.step);
const isPlatformManual = computed(() => props.platform_data.type === 'manual');
const isOpenModalPortfolios = ref(false);
const isOpenModalDownloadImages = ref(false);


const isLoadingUpload = ref(false);
const isLoadingClone = ref(false);
const selectedData = reactive({
    products: [] as number[]
});
const onUploadToShopify = () => {
    if (!props.routes.bulk_upload?.name) {
        notify({
            title: trans("No route defined"),
            type: "error"
        });
        return;
    }

    router.post(route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters), {
        portfolios: selectedData.products
    }, {
        preserveScroll: true,
        onBefore: () => isLoadingUpload.value = true,
        onError: (error) => {
            notify({
                title: trans("Something went wrong"),
                text: "",
                type: "error"
            });
        },
        onSuccess: () => {
            selectedData.products = [];
            router.reload({only: ["pageHead", "products"]});
            notify({
                title: trans("Success!"),
                text: `Portfolios successfully uploaded to ${props.platform_data.name}`,
                type: "success"
            });
            props.step.current = 1;
        },
        onFinish: () => {
            isLoadingUpload.value = false;
        }
    });
};


const downloadUrl = (type: string, addParams: string = '') => {
    if (props.download_route?.[type]?.name) {
        return route(props.download_route[type].name, {...props.download_route[type].parameters, ...{ids: addParams}});
    } else {
        return ''
    }
};

const _popover = ref()
const _clone_popover = ref()


// Method: Platform reconnect
const onClickReconnect = async (customerSalesChannel: CustomerSalesChannel) => {
    console.log('customerSalesChannel', customerSalesChannel)
    try {
        const response = await axios[customerSalesChannel.reconnect_route.method || 'get'](
            route(
                customerSalesChannel.reconnect_route.name,
                customerSalesChannel.reconnect_route.parameters
            )
        )
        console.log('1111 response', response)
        if (response.status !== 200) {
            throw new Error('Something went wrong. Try again later.')
        } else {
            window.open(response.data, '_blank');
        }
    } catch (error: any) {
        notify({
            title: 'Something went wrong',
            text: error.message || 'Please try again later.',
            type: 'error'
        })
    }
}

// Method: Clone manual portfolios
const onCloneManualPortfolio = async (sourceCustomerSalesChannelId: string | number) => {
    router.post(route(
            props.routes.clonePortfolioRoute.name,
            {
                ...props.routes.clonePortfolioRoute.parameters,
                customerSalesChannel: sourceCustomerSalesChannelId
            }
        ), {}, {
            onBefore: () => isLoadingClone.value = true,
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: "",
                    type: "error"
                });
            },
            onSuccess: () => {
                selectedData.products = [];
                router.reload({only: ["pageHead", "products"]});
                notify({
                    title: trans("Success!"),
                    text: trans(`Portfolios been cloned in the background.`),
                    type: "success"
                });
                props.step.current = 1;
                isLoadingClone.value = false;
            },
            onFinish: () => {
                isLoadingClone.value = false;
            }
        }
    )
}

// Section: Bulk upload to Shopify/Ebay/etc
const isLoadingBulkDeleteUpload = ref(false)
// const selectedPortfoliosToSync = ref([])
const bulkUpload = () => {
    router[props.routes.bulk_upload.method || 'post'](
        route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters),
        {
            portfolios: null,
        },
        {
            preserveScroll: true,
            // onBefore: () => isLoadingUpload.value = true,
            onStart: () => {
                isLoadingBulkDeleteUpload.value = true
            },
            onSuccess: () => {
                // set(progressToUploadToShopify.value, [product.id], 'loading')
            },
            onFinish: () => {
                isLoadingBulkDeleteUpload.value = false
            },
            onError: (error) => {
                console.log('Error during bulk upload:', error)
                notify({
                    title: trans("Something went wrong"),
                    text: error.message || trans("An error occurred while uploading portfolios"),
                    type: "error",
                })
            }
        }
    )
}

const progressToUploadToShopify = ref<{ [key: number]: string }>({})
const selectedProducts = ref<number[]>([])

const loadingAction = ref([])
const modalAddproductBluk = ref(false)
const modalMatchproductBluk = ref(false)


const progessbar = ref({
    data: {
        number_success: 0,
        number_fails: 0
    },
    done: true,
    total: selectedProducts.value.length,
})

const debReloadPage = () => {
    router.reload({
        except: ['auth', 'breadcrumbs', 'flash', 'layout', 'localeData', 'pageHead', 'ziggy']
    })
}


const onSuccessEditCheckmark = (key) => {

    if (key == 'Match With Existing Product') modalMatchproductBluk.value = true
    else modalAddproductBluk.value = true
    selectedProducts.value = []

    progessbar.value = {...progessbar.value, done: false, total: selectedProducts.value.length}
}

const onFailedEditCheckmark = (error: any) => {
    notify({
        title: "Something went wrong.",
        text: error?.response?.data?.products || "An error occurred.",
        type: "error",
    })
}

const submitPortfolioAction = async (action: any) => {
    loadingAction.value.push(action.label)
    try {
        const method = action?.method?.toLowerCase() || "get"
        const url = route(action.name, action?.parameters)
        const data = {portfolios: selectedProducts.value}

        const response = await axios({
            method,
            url,
            data: method === "get" ? undefined : data,
            params: method === "get" ? data : undefined
        })

        debReloadPage()
        onSuccessEditCheckmark(action.label)
    } catch (error: any) {
        onFailedEditCheckmark(error)
    } finally {
        loadingAction.value = []
    }
}

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const key = ulid()

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">

        <template #button-upload-to-shopify="{ action }">
            <Button @click="onUploadToShopify()" :style="action.style" :label="action.label" :loading="isLoadingUpload"
                    :disabled="!selectedData.products.length"
                    v-tooltip="!selectedData.products.length ? trans('Select at least one product to upload') : ''"/>
        </template>

        <template v-if="props.product_count && !props.is_closed" #other>
            <div class="rounded-md ">
                <a :href="downloadUrl('csv') as string" target="_blank" rel="noopener">
                    <Button :icon="faDownload" label="CSV" type="tertiary" class="rounded-r-none"/>
                </a>

                <a v-if="props.product_count <= 200" :href="downloadUrl('images') as string" target="_blank" rel="noopener">
                    <Button :icon="faImage" label="Images" type="tertiary" class="border-l-0  rounded-l-none"/>
                </a>
                <Button v-else @click="isOpenModalDownloadImages = true" :icon="faImage" label="Images" type="tertiary" class="border-l-0  rounded-l-none"/>
            </div>

            <Button @click="() => (isOpenModalPortfolios = true)" :label="trans('Add products')" :icon="'fas fa-plus'" v-if="!customer_sales_channel.ban_stock_update_until"/>

            <div class="rounded-md" v-if="channels?.data?.length">
                <!-- Section: Download button -->
                <Button @click="(e) => _clone_popover?.toggle(e)" v-tooltip="trans('Open another options')"
                        :icon="faEllipsisV" xloading="!!isLoadingSpecificChannel.length" class="!px-2 h-full"
                        type="tertiary" key="" v-if="!customer_sales_channel.ban_stock_update_until" />

                <Popover ref="_clone_popover" >
                    <div class="w-64 relative">
                        <div class="text-sm mb-2">
                            {{ trans("Clone portfolio from channel:") }}
                        </div>

                        <div v-for="(manual_channel, index) in channels?.data" :key="index" class="flex flex-col gap-y-2 mb-1.5">
                            <Button :loading="isLoadingClone" @click="() => onCloneManualPortfolio(manual_channel.id)"
                                :label="(manual_channel.name || manual_channel.slug) + ' ('+manual_channel.number_portfolios+')'" full
                                :style="'tertiary'"
                            >
                                <template #default="{ loading }">
                                    <div class="flex gap-x-2 justify-start items-center w-full">
                                        <LoadingIcon v-if="loading" class="h-5"/>
                                        <span v-else v-tooltip="manual_channel.platform_name" v-html="ChannelLogo(manual_channel.platform_code)" class="h-5"></span>
                                        <div>
                                            {{ useTruncate(manual_channel.name || manual_channel.slug, 20) + ' ('+manual_channel.number_portfolios+')' }}
                                        </div>
                                    </div>
                                </template>
                            </Button>
                        </div>

                    </div>
                </Popover>
            </div>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <!-- Section: Alert if platform not connected yet -->
    <Message v-if="customer_sales_channel.ban_stock_update_until" severity="error" class="m-4 flex items-center gap-2">
            <div :class="'flex gap-3 items-center'">
                <FontAwesomeIcon :icon="faBan" class="text-red-500 text-lg" />
                <div>
                    {{trans("Sorry, your account is temporarily restricted until")}} 
                    <span class="font-semibold">
                        {{ useFormatTime(customer_sales_channel.ban_stock_update_until, { formatTime: 'MMM dd, yyyy' }) }}
                    </span>
                </div>
            </div>
    </Message>


    <div v-if="!is_platform_connected && !isPlatformManual" class="mb-10">
        <div v-if="platform_data.type === 'shopify'" class="px-2 md:px-6">
            <PlatformWarningNotConnectedShopify
                :customer_sales_channel="customer_sales_channel"
            />
        </div>

        <PlatformWarningNotConnected
            v-else
            :customer_sales_channel="customer_sales_channel"
        />
    </div>

    <!-- Section: Alert if there is product not synced -->
    <Message v-if="is_platform_connected && count_product_not_synced > 0 && !isPlatformManual && currentTab === 'products'" severity="warn"
             class="m-4 ">
        <div class="ml-2 font-normal flex flex-col items-center sm:flex-row justify-between w-full">
            <div>
                <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-xl" fixed-width aria-hidden="true"/>
                <div class="inline items-center gap-x-2">
                    {{ trans("You have :products products not synced yet", {products: `${count_product_not_synced}`}) }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit space-x-2 flex justify-end">
                <ButtonWithLink v-if="routes.duplicate?.name" :routeTarget="routes.duplicate"
                                v-tooltip="trans('This will only create new products to the :platform that not exist in :platform', { platform: props.platform_data.name })"
                                aclick="() => onClickReconnect(customer_sales_channel)" icon="far fa-plus"
                                :label="trans('Create new')" type="tertiary"/>

                <ButtonWithLink v-if="routes.batch_sync?.name" :routeTarget="routes.batch_sync"
                                v-tooltip="trans('This will only sync existing products to the :platform (will not create new)', { platform: props.platform_data.name })"
                                icon="fas fa-sync-alt" :label="trans('Use existing')" type="tertiary"/>


                <div v-if="selectedProducts.length > 0" class="space-x-2 border-r border-gray-400 pr-2">
                    <!--   <Button v-if="selectedProducts.length > 0" type="green" icon="fas fa-hand-pointer"
                          :label="trans('Match With Existing Product (:count)', { count: selectedProducts?.length })"
                          :loading="loadingAction.includes('bulk-match')" @click="() => submitPortfolioAction({
                              label : 'bulk-match',
                              name : 'retina.models.dropshipping.shopify.batch_match',
                              parameters: { customerSalesChannel: customer_sales_channel.id },
                              method: 'post',
                          })" size="xs" /> -->

                    <Button
                        v-if="selectedProducts.length > 0"
                        v-tooltip="trans('Unlink & Delete Product :platform', { platform: props.platform_data?.name })"
                        :type="'delete'"
                        :label="trans('Unlink & Delete Product (:count)', { count: selectedProducts?.length })"
                        :loading="loadingAction.includes('bulk-unlink')"
                        @click="() => submitPortfolioAction({
                            label : 'bulk-unlink',
                            name : props.routes.bulk_unlink.name,
                            parameters: { customerSalesChannel: customer_sales_channel.id },
                            method: 'post',
                        })"
                        size="xs"
                    />

                    <Button
                        v-if="selectedProducts.length > 0"
                        v-tooltip="trans('Upload as new product to the :platform', { platform: props.platform_data?.name })"
                        :type="'create'"
                        :label="trans('Create New Product (:count)', { count: selectedProducts?.length })"
                        :loading="loadingAction.includes('bulk-create')"
                        @click="() => submitPortfolioAction({
                            label : 'bulk-create',
                            name : props.routes.bulk_upload.name,
                            parameters: { customerSalesChannel: customer_sales_channel.id },
                            method: 'post',
                        })"
                        size="xs"
                    />
                </div>

                <div>
                    <ButtonWithLink
                        v-if="customer_sales_channel.type !== 'ebay'"
                        label="Upload all as new product"
                        size="xs"
                        :routeTarget="{
                            name: props.routes.batch_all.name,
                            parameters: { customerSalesChannel: customer_sales_channel.id },
                            method: 'post'
                        }"
                        @success="() => {progessbar = {...progessbar , done : false, total : count_product_not_synced}, selectedProducts = []}"
                    />
                </div>
            </div>
        </div>
    </Message>

    <!-- retina.models.dropshipping.ebay.batch_upload -->
    <div v-if="(is_platform_connected || isPlatformManual) && currentTab === 'products'">
        <div v-if="props.product_count < 1"
            class="relative mx-auto flex max-w-3xl flex-col items-center px-6 text-center pt-20 lg:px-0">
            <h1 class="text-4xl font-bold tracking-tight lg:text-6xl">
                {{ content?.portfolio_empty?.title || trans(`You don't have a single portfolios`) }}
            </h1>
            <p class="mt-4 text-xl">
                {{
                    content?.portfolio_empty?.description || trans("To get started, add products to your shop. You can sync from your inventory or create a new one.")
                }}
            </p>
            <div class="mt-6 space-y-4">
                <ButtonWithLink v-if="routes?.syncAllRoute" :routeTarget="routes?.syncAllRoute" isWithError
                                :label="content?.portfolio_empty?.sync_button" icon="fas fa-sync-alt" xtype="tertiary"
                                size="xl"/>
                <div v-if="routes?.syncAllRoute && routes?.addPortfolioRoute" class="text-gray-500">
                    {{ content?.portfolio_empty?.separation || trans("or") }}
                </div>
                <Button v-if="routes?.addPortfolioRoute" @click="isOpenModalPortfolios = true"
                        :label="content?.portfolio_empty?.add_button || trans('Add products')" icon="fas fa-plus"
                        size="xl"/>
            </div>
        </div>
        <div v-else class="overflow-x-auto">
            <RetinaTablePortfoliosManual v-if="isPlatformManual" :data="props.products" :tab="'products'" :selectedData
                :platform_data :platform_user_id :is_platform_connected :progressToUploadToShopify :disabled="!customer_sales_channel.ban_stock_update_until"
                :isPlatformManual
                :useCheckBox="is_platform_connected && count_product_not_synced > 0 && !isPlatformManual"/>
            <RetinaTablePortfoliosShopify v-else-if="platform_data.type === 'shopify'" :data="props.products"
                :tab="'products'" :selectedData :platform_data :platform_user_id
                :is_platform_connected :disabled="!customer_sales_channel.ban_stock_update_until"
                :progressToUploadToShopifyAll="progessbar" :progressToUploadToShopify
                :customerSalesChannel="customer_sales_channel"
                v-model:selectedProducts="selectedProducts" :key="key"
                :count_product_not_synced="count_product_not_synced"/>
            <RetinaTablePortfoliosPlatform v-else :data="props.products" :tab="'products'" :selectedData :platform_data
                :platform_user_id :is_platform_connected :progressToUploadToShopify :disabled="!customer_sales_channel.ban_stock_update_until"
                :customerSalesChannel="customer_sales_channel" :progressToUploadToEcom="progessbar"
                v-model:selectedProducts="selectedProducts" :key="key + 'table-products'"
                :routes="props.routes" :count_product_not_synced="count_product_not_synced"/>
        </div>
    </div>
    <div v-else-if="currentTab === 'logs'">
        <TableRetinaPlatformPortfolioLogs :data="logs" :tab="currentTab" />
    </div>

    <Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false"
           width="w-full max-w-7xl max-h-[600px] md:max-h-[85vh] overflow-y-auto">
        <AddPortfolios v-if="platform_data?.type === 'manual'" :step="step" :routes="props.routes" :platform_data
                       @onDone="isOpenModalPortfolios = false" :platform_user_id/>

        <AddPortfoliosWithUpload v-else :step="step" :routes="props.routes" :platform_data

                                 @onDone="()=>{isOpenModalPortfolios = false, key = ulid()}" :platform_user_id
                                 :is_platform_connected
                                 :customerSalesChannel="customer_sales_channel" :onClickReconnect/>
    </Modal>

    <Modal :isOpen="isOpenModalDownloadImages" @onClose="isOpenModalDownloadImages = false"
           width="w-[70%] max-w-[420px] max-h-[600px] md:max-h-[85vh] overflow-y-auto">
        <div class="mb-8">
            <h3 class="text-center font-semibold">{{ trans('Images grouped by first letter from product code')}}</h3>
        </div>
        <div class="flex flex-col gap-2">
            <div v-for="grouped in grouped_portfolios" class="flex justify-between gap-2">
                <div class="my-auto">
                    <span><b>{{grouped.char}}</b>: ({{grouped.count}}) images</span>
                </div>
                <a v-if="grouped.count > 0" :href="downloadUrl('images', grouped.ids) as string" rel="noopener">
                    <Button :icon="faImage" label="Download" type="tertiary" class="rounded"/>
                </a>
            </div>
        </div>
    </Modal>
</template>
