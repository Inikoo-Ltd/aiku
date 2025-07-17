<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { computed, reactive, ref } from "vue";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { Tabs as TSTabs } from "@/types/Tabs";
import RetinaTablePortfolios from "@/Components/Tables/Retina/RetinaTablePortfolios.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";
import { routeType } from "@/types/route";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Popover } from "primevue"
import { faSyncAlt } from "@fas";
import {
    faBracketsCurly,
    faFileExcel,
    faImage,
    faArrowLeft,
    faArrowRight,
    faUpload,
    faBox,
    faEllipsisV,
    faDownload
} from "@fal";
import { Table as TableTS } from "@/types/Table"

library.add(faFileExcel, faBracketsCurly, faImage, faSyncAlt, faBox, faArrowLeft, faArrowRight, faUpload);


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

const isPlatformManual = computed(() => props.platform_data.type === 'manual');
const isOpenModalPortfolios = ref(false);


const isLoadingClone = ref(false);
const selectedData = reactive({
    products: [] as number[]
});

const downloadUrl = (type: string) => {
    if (props.download_route?.[type]?.name) {
        return route(props.download_route[type].name, props.download_route[type].parameters);
    } else {
        return ''
    }
};

const _popover = ref()
const _clone_popover = ref()









</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template v-if="props.product_count" #other>
            <div class="rounded-md ">
                <a :href="downloadUrl('csv') as string" target="_blank" rel="noopener">
                    <Button :icon="faDownload" label="CSV" type="tertiary" class="rounded-r-none" />
                </a>


                <!-- Section: Download button -->
                <Button @click="(e) => _popover?.toggle(e)" v-tooltip="trans('Open another options')"
                    :icon="faEllipsisV" xloading="!!isLoadingSpecificChannel.length"
                    class="!px-2 border-l-0 rounded-l-none h-full" type="tertiary" key="" />

                <Popover ref="_popover">
                    <div class="w-64 relative">
                        <div class="text-sm mb-2">
                            {{ trans("Select another download file type") }}:
                        </div>

                        <div class="flex flex-col gap-y-2">
                            <a :href="downloadUrl('xlsx') as string" target="_blank" rel="noopener">
                                <Button :icon="faFileExcel" label="Excel" full :style="'tertiary'" />
                            </a>
                            <a :href="downloadUrl('json') as string" target="_blank" rel="noopener">
                                <Button :icon="faBracketsCurly" label="JSON" full :style="'tertiary'" />
                            </a>
                            <a :href="downloadUrl('images') as string" target="_blank" rel="noopener">
                                <Button :icon="faImage" :label="trans('Images')" full :style="'tertiary'" />
                            </a>
                        </div>

                    </div>
                </Popover>
            </div>

            <Button @click="() => (isOpenModalPortfolios = true)" :label="trans('Add products')"
                :icon="'fas fa-plus'" />

            <div class="rounded-md" v-if="manual_channels?.data?.length">
                <!-- Section: Download button -->
                <Button @click="(e) => _clone_popover?.toggle(e)" v-tooltip="trans('Open another options')"
                    :icon="faEllipsisV" xloading="!!isLoadingSpecificChannel.length" class="!px-2 h-full"
                    type="tertiary" key="" />

                <Popover ref="_clone_popover">
                    <div class="w-64 relative">
                        <div class="text-sm mb-2">
                            {{ trans("Clone portfolio from channel:") }}
                        </div>

                        <div class="flex flex-col gap-y-2" v-for="manual_channel in manual_channels?.data">
                            <Button :loading="isLoadingClone" @click="() => onCloneManualPortfolio(manual_channel.id)"
                                :label="manual_channel.name + ' (' + manual_channel.number_portfolios + ')'" full
                                :style="'tertiary'" />
                        </div>

                    </div>
                </Popover>
            </div>
        </template>
    </PageHeading>

    <RetinaTablePortfolios :data="props.products" :tab="'products'" :selectedData :platform_data :platform_user_id
        :is_platform_connected :progressToUploadToShopify :isPlatformManual />
    </div>
</template>
