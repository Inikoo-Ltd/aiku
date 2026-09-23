<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBox, faBullhorn, faCameraRetro, faCube, faFolder,
    faMoneyBillWave, faProjectDiagram, faRoad, faShoppingCart,
    faStream, faUsers, faHeart, faMinus,
    faFolderTree, faBrowser, faLanguage,faFolders, faPaperclip,
    faFolderDownload,faQuoteLeft,
    faSearch,
    faBadgePercent,
    faTools,
} from '@fal'
import { ref, computed, inject } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from "@/Composables/capitalize"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import Breadcrumb from 'primevue/breadcrumb'
import { FontAwesomeIcon, FontAwesomeLayers } from '@fortawesome/vue-fontawesome'
import type { PageHeadingTypes } from '@/types/PageHeading'
import ModelDetails from "@/Components/ModelDetails.vue"
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
import TableMailshots from "@/Components/Tables/TableMailshots.vue"
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue"
import ProductShowcase from "@/Components/Showcases/Grp/ProductShowcase.vue"
import ProductService from "@/Components/Showcases/Grp/ProductService.vue"
import ProductRental from "@/Components/Showcases/Grp/ProductRental.vue"
import TableProductFavourites from "@/Components/Tables/Grp/Org/Catalogue/TableProductFavourites.vue"
import TableProductBackInStockReminders from "@/Components/Tables/Grp/Org/Catalogue/TableProductBackInStockReminders.vue"
import TableTradeUnits from '@/Components/Tables/Grp/Goods/TableTradeUnits.vue'
import TableOrgStocks from '@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import ProductTranslation from '@/Components/Showcases/Grp/ProductTranslation.vue'
import { routeType } from '@/types/route'
import TradeUnitImagesManagement from "@/Components/Goods/ImagesManagement.vue"
import AttachmentManagement from '@/Components/Goods/AttachmentManagement.vue'
import ProductCategoryTimeSeriesTable from "@/Components/Product/ProductCategoryTimeSeriesTable.vue";
import { trans } from "laravel-vue-i18n"
import ProductContent from '@/Components/Showcases/Grp/ProductContent.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Action from '@/Components/Forms/Fields/Action.vue'
import { faMagnifyingGlass } from '@fortawesome/free-solid-svg-icons'
import { faShapes, faStar, faExclamationTriangle } from '@fas'
import { faHatCowboy } from "@far"
import TableOffers from '@/Components/Shop/Offers/TableOffers.vue'
import TableReviews from "@/Components/Shop/Reviews/TableReviews.vue"
import Dialog from "primevue/dialog"
import FormReview from "@/Components/Retina/FormReview.vue"
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import ModalCreateGiftOffers from '@/Components/Offers/ModalCreateGiftOffers.vue'
import ModalCreateStepDiscountProduct from '@/Components/Offers/ModalCreateStepDiscountProduct.vue'
import StaffTaskPanel from "@/Components/Tasks/StaffTaskPanel.vue"

library.add(
    faFolder,
    faFolders,
    faCube,
    faStream,
    faMoneyBillWave,
    faShoppingCart,
    faUsers,
    faBullhorn,
    faProjectDiagram,
    faBox,
    faCameraRetro,
    faRoad,
    faHeart,
    faMinus,
    faBrowser,
    faLanguage,
    faPaperclip,
    faFolderTree,
    faFolderDownload,
    faQuoteLeft,
    faMagnifyingGlass,
    faBadgePercent,
    faTools
)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    staff_task?: { model_type: 'Product' | 'Customer' | 'Order' | 'DeliveryNote'; model_id: number }
    tabs: {
        current: string
        navigation: {}
    }
    rating_labels : any
    translation?: {}
    orders?: {}
    customers?: {}
    mailshots?: {}
    showcase?: {}
    content?: {}
    offers?: {}
    reviews?: {}
    service?: {}
    rental?: {}
    trade_units?: {}
    history?: {}
    stocks?: {}
    images?: {}
    attachments?: {}
    family_slug : string
    master : boolean
    mini_breadcrumbs? : any[]
    masterRoute?: routeType
    is_external_shop?: boolean
    product_state?: boolean
    is_dependent_trade_unit?: boolean
    variant?: {}
    is_variant_leader?: boolean
    webpage_canonical_url?: string
    retirement_decision?: {
        replacement: { code: string, name: string, price: string, route: routeType }
        retire_route: routeType
        keep_route: routeType
    } | null
    sales?: {}
    salesData?: object
    is_single_trade_unit?: boolean
    reminders?: {}
    trade_unit_slug?: string
    shop_data: {
        id: number
        slug: string
        organisation: string
        offercampaign: string
        currency_code: string
        default_dates: {
            start: string
            end: string
        }
    }
    product_id: number
    product_units?: number
    product_unit?: string
    not_follow_master_media?: boolean
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const isOpenDialog = ref(false)
const reviewPayload = ref(null)
const openDialog = () => {
    isOpenDialog.value = true
    reviewErrors.value = {}
}

const component = computed(() => {
    const components: Record<string, any> = {
        showcase: ProductShowcase,
        mailshots: TableMailshots,
        customers: TableCustomers,
        orders: TableOrders,
        details: ModelDetails,
        service: ProductService,
        rental: ProductRental,
        history: TableHistories,
        favourites: TableProductFavourites,
        reminders: TableProductBackInStockReminders,
        trade_units: TableTradeUnits,
        stocks: TableOrgStocks,
        images: TradeUnitImagesManagement,
        translation: ProductTranslation,
        attachments: AttachmentManagement,
        sales: ProductCategoryTimeSeriesTable,
        content: ProductContent,
        offers: TableOffers,
        reviews: TableReviews,
    }
    return components[currentTab.value]
})


const routeVariant = () => {
    return route(
        'grp.org.shops.show.catalogue.families.show.variants.show',
        {
            organisation: (route().params as RouteParams).organisation,
            shop: (route().params as RouteParams).shop,
            family: props.family_slug,
            variant: props.variant.slug,
        }
    )
}

const goToEdit = () => {
    let editBtn = props.pageHead.actions.find((item) => item.label === 'Edit');

    if (editBtn?.route) {
        router.visit(route(editBtn.route?.name, {
            ...editBtn.route?.parameters,
            section: 5
        }));
    }
}

const retirementLoading = ref<string | null>(null)
const submitRetirementDecision = (decisionRoute: routeType, key: string) => {
    router.patch(route(decisionRoute.name, decisionRoute.parameters), {}, {
        preserveScroll: true,
        onStart: () => retirementLoading.value = key,
        onFinish: () => retirementLoading.value = null,
        onError: (errors) => notify({ title: trans('Something went wrong'), text: Object.values(errors)[0] as string, type: 'error' }),
    })
}

const loadingSave = ref(false)
const reviewErrors = ref<Record<string, string[] | string>>({})

const saveProductReview = async () => {
    const routeName = "grp.models.review.store"
    const formData = new FormData()

    const payload = {
       ...reviewPayload.value,
       reviewable_type : 'product_reviews',
       reviewable_id : props.product_id
    }

    Object.entries(payload || {}).forEach(([key, value]) => {
        if (key === "images" && Array.isArray(value)) {
            value.forEach((file: File) => {
                formData.append("images[]", file)
            })
        } else {
            formData.append(key, value as any)
        }
    })

    try {
        loadingSave.value = true
        reviewErrors.value = {}

        await axios({
            method: "post",
            url: route(routeName,{}),
            data: formData,
            headers: {
                "Content-Type": "multipart/form-data",
            },
        })

        isOpenDialog.value = false

        router.reload({only: ['pageHead', 'reviews']})
        notify({
            title: "Success",
            text: "Review submitted successfully",
            type: "success",
        })
    } catch (error: any) {
        reviewErrors.value = error?.response?.data?.errors || {}

        notify({
            title: "Error",
            text: error?.response?.data?.message || "Failed to submit review",
            type: "error",
        })
    } finally {
        loadingSave.value = false
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" >
        <template #afterTitle>
             <Link v-if="master" :href="route(masterRoute.name, masterRoute.parameters)"  v-tooltip="trans('Go to Master')">
                <FontAwesomeIcon
                    icon="fab fa-octopus-deploy"
                    color="#4B0082" fixed-width
                />
            </Link>
            <Link v-if="is_single_trade_unit && trade_unit_slug" :href="route('grp.trade_units.units.show', [trade_unit_slug])" v-tooltip="trans('Go to Trade Unit')">
                <FontAwesomeIcon
                    icon="fal fa-atom" fixed-width
                />
            </Link>

            <FontAwesomeIcon
                v-if="is_dependent_trade_unit"
                v-tooltip="trans('This product have independent Trade Unit setting')"
                @click="goToEdit"
                :icon="faHatCowboy"
                class="text-red-500 cursor-pointer" fixed-width
            />

            <FontAwesomeLayers
                v-if="not_follow_master_media"
                v-tooltip="ctrans('Product has independent media settings')"
                class="flex items-center justify-center w-[2rem]"
            >
                <FontAwesomeIcon 
                    :icon="faHatCowboy"
                    :class="'text-red-500 text-[17px] !top-[-93%] !right-[-50%] !rotate-[17deg]'" fixed-width
                />
                <FontAwesomeIcon 
                    :icon="faCameraRetro"
                    :class="'text-red-500'" fixed-width
                />
            </FontAwesomeLayers>

            <Link  v-if="variant"  :href="routeVariant()" v-tooltip="trans('Go to Variant')">
                <FontAwesomeIcon :icon="is_variant_leader ? faStar : faShapes" class="text-yellow-500 cursor-pointer" fixed-width />
            </Link>


        </template>

        <template #button-create-review="{ action }">
            <div v-if="currentTab != 'reviews'"></div>
            <div v-else>
                <Button :style="action.style" :label="action.label" @click="openDialog" />
            </div>
        </template>        
        <template #other>
            <StaffTaskPanel v-if="staff_task" :model-type="staff_task.model_type" :model-id="staff_task.model_id" class="mr-2" />
        </template>
        <template #otherBefore>
            <Action
                v-if="currentTab === 'images'"
                :action="{
                    key: 'repair-images',
                    type: 'button',
                    style: 'edit',
                    icon: 'fal fa-tools',
                    label: trans('Repair Images'),
                    tooltip: trans('Sync Product Images from Trade Units'),
                    route: {
                        name: 'grp.models.product.repair_product_images',
                        method: 'patch',
                        parameters: { product: product_id },
                    },
                }"
            />

            <template v-if="currentTab === 'offers'">
                <ModalCreateGiftOffers
                    v-tooltip="'Create New Offer'"
                    :shop_data="props.shop_data"
                    :product_id="props.product_id"
                    />

                <div                    
                    class="relative inline-flex"
                >
                    <ModalCreateStepDiscountProduct
                        v-tooltip="'Create New Offer'"
                        :shop_data="props.shop_data"
                        :product_id="props.product_id"
                        :product_units="props.product_units"
                        :product_unit="props.product_unit"
                    />
                </div>
            </template>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    
    <div v-if="mini_breadcrumbs?.length" class="bg-white  px-4 py-2  w-full  border-gray-200 border-b overflow-x-auto">
        <Breadcrumb :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <component :is="item.to ? Link : 'span'" v-bind="item.to ? { href: route(item.to.name, item.to.parameters) } : {}" v-tooltip="item.tooltip"
                        :title="item.label" class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" fixed-width />
                        <span>{{ item.label || '-' }}</span> <span v-if="item.post_label" class="text-gray-400">{{ item.post_label }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>
    
    <div v-if="retirement_decision" class="m-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm">
        <div class="flex items-center gap-2 text-base font-semibold text-amber-800">
            <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
            {{ trans('This product needs a decision') }}
        </div>
        <p class="mt-2 text-gray-700">
            {{ trans('At the Aurora cutover this product was merged into') }}
            <Link :href="route(retirement_decision.replacement.route.name, retirement_decision.replacement.route.parameters)" class="font-semibold underline">{{ retirement_decision.replacement.code }}</Link>
            {{ trans('which took over its webpage and sells the same goods with quantity discounts. It was later put back on sale without a webpage of its own, so its link on the website opens the other product.') }}
        </p>

        <div class="mt-4 font-semibold text-gray-800">{{ trans('Choose one option') }}:</div>
        <div class="mt-2 flex flex-col gap-3 md:flex-row md:items-stretch">
            <div class="flex flex-1 flex-col rounded-md border border-red-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-red-600">{{ trans('Option 1') }}</div>
                <div class="mt-1 font-semibold text-gray-900">{{ trans('Sell it only through :code', { code: retirement_decision.replacement.code }) }}</div>
                <p class="mt-2 flex-1 text-gray-600">{{ trans('This product is taken off sale and discontinued. Customers buy the other product and get the quantity discount. If it sits in an order being processed it is hidden now and discontinued later.') }}</p>
                <Button class="mt-3 self-start" type="negative" :label="trans('Retire this product')" :loading="retirementLoading === 'retire'" :disabled="!!retirementLoading" @click="submitRetirementDecision(retirement_decision.retire_route, 'retire')" />
            </div>

            <div class="flex items-center justify-center text-xs font-semibold uppercase text-gray-400">{{ trans('or') }}</div>

            <div class="flex flex-1 flex-col rounded-md border border-green-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-green-600">{{ trans('Option 2') }}</div>
                <div class="mt-1 font-semibold text-gray-900">{{ trans('Keep it as its own product') }}</div>
                <p class="mt-2 flex-1 text-gray-600">{{ trans('This product gets its webpage back and stays on sale. :code goes back to its own webpage, which may need content. Review the quantity discount on :code afterwards so both do not undercut each other.', { code: retirement_decision.replacement.code }) }}</p>
                <Button class="mt-3 self-start" type="save" :label="trans('Keep as separate product')" :loading="retirementLoading === 'keep'" :disabled="!!retirementLoading" @click="submitRetirementDecision(retirement_decision.keep_route, 'keep')" />
            </div>
        </div>
    </div>

    <component :is="component" :data="props[currentTab]" :tab="currentTab" :handleTabUpdate :salesData="salesData" />


     <Dialog v-model:visible="isOpenDialog" modal header="Product Review" :style="{ width: '60rem' }" :breakpoints="{
            '1200px': '70vw',
            '992px': '85vw',
            '576px': '95vw'
        }" :content-style="{ overflow: 'auto' }">
        <FormReview v-model="reviewPayload" :schema="props.rating_labels" :use_customer="true" :errors="reviewErrors"/>
        <template #footer>
            <div class="flex justify-end gap-5">
                <Button label="Close" type="secondary" @click="isOpenDialog = false" />
                <Button label="Save" type="save" @click="saveProductReview"/>
            </div>
        </template>
    </Dialog>
</template>


<style scoped>
/* Remove default breadcrumb styles */
:deep(.p-breadcrumb) {
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
}

:deep(.p-breadcrumb-list > li.p-breadcrumb-separator:first-child) {
    display: none !important;
}
</style>
