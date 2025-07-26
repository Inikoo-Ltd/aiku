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
import {faSyncAlt} from "@fas";
import {
    faBracketsCurly, faPawClaws,
    faFileExcel,
    faImage,
    faArrowLeft,
    faArrowRight,
    faUpload,
    faBox,
    faEllipsisV,
    faDownload
} from "@fal";
import axios from "axios"
import {Table as TableTS} from "@/types/Table"
import { CustomerSalesChannel } from "@/types/customer-sales-channel"
import RetinaTablePortfoliosPlatform from "@/Components/Tables/Retina/RetinaTablePortfoliosPlatform.vue"
import RetinaTablePortfoliosShopify from "@/Components/Tables/Retina/RetinaTablePortfoliosShopify.vue"

library.add(faFileExcel, faBracketsCurly, faSyncAlt, faPawClaws, faImage, faSyncAlt, faBox, faArrowLeft, faArrowRight, faUpload);


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    download_route: any
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
    routes: {
        syncAllRoute: routeType
        batch_sync: routeType
        duplicate: routeType
        addPortfolioRoute: routeType
        bulk_upload: routeType
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
    customer_sales_channel: {
        id: number
        slug: string
        name: string
    }
    manual_channels: object
    count_product_not_synced: number

   // inactive: {}
    product_count: number
}>();

const step = ref(props.step);
const isPlatformManual = computed(() => props.platform_data.type === 'manual');
const isOpenModalPortfolios = ref(false);


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


const downloadUrl = (type: string) => {
    if (props.download_route?.[type]?.name) {
        return route(props.download_route[type].name, props.download_route[type].parameters);
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
                    text: `Portfolios successfully cloned.`,
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



</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">


        <template #button-upload-to-shopify="{ action }">
            <Button
                @click="onUploadToShopify()"
                :style="action.style"
                :label="action.label"
                :loading="isLoadingUpload"
                :disabled="!selectedData.products.length"
                v-tooltip="!selectedData.products.length ? trans('Select at least one product to upload') : ''"
            />
        </template>

        <template v-if="props.product_count" #other>
            <div class="rounded-md ">
                <a :href="downloadUrl('csv') as string" target="_blank" rel="noopener">
                    <Button
                        :icon="faDownload"
                        label="CSV"
                        type="tertiary"
                        class="rounded-r-none"
                    />
                </a>

                <a :href="downloadUrl('images') as string" target="_blank" rel="noopener">
                    <Button
                        :icon="faImage"
                        label="Images"
                        type="tertiary"
                        class="border-l-0 border-r-0 rounded-none"
                    />
                </a>

                <!-- Section: Download button -->
                <Button
                    @click="(e) => _popover?.toggle(e)"
                    v-tooltip="trans('Open another options')"
                    :icon="faEllipsisV"
                    xloading="!!isLoadingSpecificChannel.length"
                    class="!px-2 border-l-0 rounded-l-none h-full"
                    type="tertiary"
                    key=""
                />

                <Popover ref="_popover">
                    <div class="w-64 relative">
                        <div class="text-sm mb-2">
                            {{ trans("Select another download file type") }}:
                        </div>

                        <div class="flex flex-col gap-y-2">
                            <a :href="downloadUrl('xlsx') as string" target="_blank" rel="noopener">
                                <Button
                                    :icon="faFileExcel"
                                    label="Excel"
                                    full
                                    :style="'tertiary'"/>
                            </a>
                            <a :href="downloadUrl('json') as string" target="_blank" rel="noopener">
                                <Button
                                    :icon="faBracketsCurly"
                                    label="JSON"
                                    full
                                    :style="'tertiary'"
                                />
                            </a>
                          <a :href="downloadUrl('images') as string" target="_blank" rel="noopener">
                                <Button
                                    :icon="faImage"
                                    :label="trans('Images')"
                                    full
                                    :style="'tertiary'" />
                            </a>
                        </div>

                    </div>
                </Popover>
            </div>

            <Button
                @click="() => (isOpenModalPortfolios = true)"
                :label="trans('Add products')"
                :icon="'fas fa-plus'"
            />

            <div class="rounded-md" v-if="manual_channels?.data?.length">
                <!-- Section: Download button -->
                <Button
                    @click="(e) => _clone_popover?.toggle(e)"
                    v-tooltip="trans('Open another options')"
                    :icon="faEllipsisV"
                    xloading="!!isLoadingSpecificChannel.length"
                    class="!px-2 h-full"
                    type="tertiary"
                    key=""
                />

                <Popover ref="_clone_popover">
                    <div class="w-64 relative">
                        <div class="text-sm mb-2">
                            {{ trans("Clone portfolio from channel:") }}
                        </div>

                        <div class="flex flex-col gap-y-2" v-for="manual_channel in manual_channels?.data">
                            <Button
                                :loading="isLoadingClone"
                                @click="() => onCloneManualPortfolio(manual_channel.id)"
                                :label="manual_channel.name + ' ('+manual_channel.number_portfolios+')'"
                                full
                                :style="'tertiary'"/>
                        </div>

                    </div>
                </Popover>
            </div>
        </template>
    </PageHeading>

    <!-- Section: Alert if Platform not connected yet -->
    <Message v-if="!is_platform_connected && !isPlatformManual" severity="error" class="m-4 ">
        <div class="ml-2 font-normal flex flex-col items-center sm:flex-row justify-between w-full">
            <div>
                <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-xl" fixed-width aria-hidden="true"/>
                <div class="inline items-center gap-x-2">
                    {{
                        trans("Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.")
                    }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit">
                <Button
                    v-if="customer_sales_channel?.reconnect_route?.name"
                    @click="() => onClickReconnect(customer_sales_channel)"
                    iconRight="fal fa-external-link"
                    :label="trans('Connect')"
                    zsize="xxs"
                    type="secondary"
                    full
                />
            </div>
        </div>
    </Message>

    <!-- Section: Alert if there is product not synced -->
    <Message v-if="is_platform_connected && count_product_not_synced > 0 && !isPlatformManual" severity="warn" class="m-4 ">
        <div class="ml-2 font-normal flex flex-col items-center sm:flex-row justify-between w-full">
            <div>
                <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-xl" fixed-width aria-hidden="true"/>
                <div class="inline items-center gap-x-2">
                    {{ trans("You have :products products not synced yet", {products: `${count_product_not_synced}`}) }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit space-x-2">
                <ButtonWithLink
                    v-if="routes.duplicate?.name"
                    :routeTarget="routes.duplicate"
                    v-tooltip="trans('This will only create new products to the :platform that not exist in :platform', { platform: props.platform_data.name })"
                    aclick="() => onClickReconnect(customer_sales_channel)"
                    icon="far fa-plus"
                    :label="trans('Create new')"
                    type="tertiary"
                />

                <ButtonWithLink
                    v-if="routes.batch_sync?.name"
                    :routeTarget="routes.batch_sync"
                    v-tooltip="trans('This will only sync existing products to the :platform (will not create new)', { platform: props.platform_data.name })"
                    icon="fas fa-sync-alt"
                    :label="trans('Use existing')"
                    type="tertiary"
                />

<!--                <Button-->
<!--                    @click="() => bulkUpload()"-->
<!--                    v-tooltip="trans('This will create new products (if not exist in :platform) or will sync the products if exist in :platform', { platform: props.platform_data.name })"-->
<!--                    icon="fal fa-paw-claws"-->
<!--                    :label="trans('Brave mode')"-->
<!--                    type="tertiary"-->
<!--                    :loading="isLoadingBulkDeleteUpload"-->
<!--                />-->
            </div>
        </div>
    </Message>

    <!-- retina.models.dropshipping.ebay.batch_upload -->
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
            <ButtonWithLink
                v-if="routes?.syncAllRoute"
                :routeTarget="routes?.syncAllRoute"
                isWithError
                :label="content?.portfolio_empty?.sync_button"
                icon="fas fa-sync-alt"
                xtype="tertiary"
                size="xl"
            />
            <div v-if="routes?.syncAllRoute && routes?.addPortfolioRoute" class="text-gray-500">
                {{ content?.portfolio_empty?.separation || trans("or") }}
            </div>
            <Button v-if="routes?.addPortfolioRoute" @click="isOpenModalPortfolios = true"
                    :label="content?.portfolio_empty?.add_button || trans('Add products')" icon="fas fa-plus"
                    size="xl"/>
        </div>
    </div>

    <div v-else class="overflow-x-auto">
        <RetinaTablePortfoliosManual
            v-if="isPlatformManual"
            :data="props.products"
            :tab="'products'"
            :selectedData
            :platform_data
            :platform_user_id
            :is_platform_connected
            :progressToUploadToShopify
            :isPlatformManual
        />

        <RetinaTablePortfoliosShopify
            v-else-if="platform_data.type === 'shopify'"
            :data="props.products"
            :tab="'products'"
            :selectedData
            :platform_data
            :platform_user_id
            :is_platform_connected
            :progressToUploadToShopify
            :customerSalesChannel="customer_sales_channel"
        />

        <RetinaTablePortfoliosPlatform
            v-else
            :data="props.products"
            :tab="'products'"
            :selectedData
            :platform_data
            :platform_user_id
            :is_platform_connected
            :progressToUploadToShopify
        />
    </div>

    <Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false"
           width="w-full max-w-7xl max-h-[600px] md:max-h-[85vh] overflow-y-auto">
        <AddPortfolios
            v-if="platform_data?.type === 'manual'"
            :step="step"
            :routes="props.routes"
            :platform_data
            @onDone="isOpenModalPortfolios = false"
            :platform_user_id
        />

        <AddPortfoliosWithUpload
            v-else
            :step="step"
            :routes="props.routes"
            :platform_data
            @onDone="isOpenModalPortfolios = false"
            :platform_user_id
            :is_platform_connected
            :customerSalesChannel="customer_sales_channel"
            :onClickReconnect
        />
    </Modal>
</template>
