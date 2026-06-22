<script setup lang="ts">
import { routeType } from '@/types/route';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faInfoCircle } from "@fas";
import Message from "primevue/message";
import { Link, router } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faAlbumCollection, faStarfighter } from "@fal";
import ReviewContent from '@/Components/ReviewContent.vue';
import ProductCategoryCard from '@/Components/ProductCategoryCard.vue';
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue';
import ProductCategoryStats from '@/Components/Product/ProductCategoryStats.vue';
import { trans } from 'laravel-vue-i18n';
import { faExternalLink } from '@far';
import FamilyOfferLabelDiscount from '@/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/FamilyOfferLabelDiscount.vue'

library.add(faAlbumCollection, faStarfighter);

const props = defineProps<{
    data: {
        translation_box: {
            title: string
            save_route: routeType
        }
        family: {
            data: any,
        },
        routeList: {
            collectionRoute: routeType
        },
        routes: {
            detach_family: routeType
        }
        is_shop_gr_active?: boolean
        gr_offer_data?: any
        follow_master_gr?: boolean
        tags: Array<any>
        show_gr_vol?: boolean
        webpage_url?: string
    },
    salesData?: object
    actions?: any
}>();

const navigateTo = () => {
    const routeParams = route().params;

    switch (route().current()) {
        case "grp.org.shops.show.catalogue.families.show":
            router.visit(route("grp.org.shops.show.catalogue.families.edit", { ...routeParams, section: 1 }));
            break;

        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show":
            router.visit(route("grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.edit", { ...routeParams, section: 1 }));
            break;

        default:
            router.visit(route("grp.org.shops.show.catalogue.departments.show.families.edit", {
                ...routeParams,
                section: 1
            }));
            break;
    }
}

function offerRoute(offer: {}) {
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.families.show":
            return route(
                "grp.org.shops.show.discounts.offers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    offer.slug])
        default:
            return ""
    }
}
</script>

<template>
   
    <div v-if="data.webpage_url"
		class="w-full bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 px-4 py-3 mb-3 shadow-sm">
		<div class="flex items-center gap-2 text-blue-700 text-sm">
			<FontAwesomeIcon :icon="faExternalLink" class="text-blue-500" />
			<a :href="data.webpage_url" target="_blank" rel="noopener noreferrer"
				class="font-medium break-all hover:underline hover:text-blue-800 transition-colors duration-200">
				{{ data.webpage_url }}
			</a>
		</div>
	</div>
    <div class="pb-8 m-5">
        <div class="space-y-4">
            <Message
                v-if="!data.family?.data.description || !data.family?.data.description_title || !data.family?.data.description_extra && actions"
                severity="error" closable>
                <template #icon>
                    <FontAwesomeIcon :icon="faInfoCircle" />
                </template>
                <div class="ml-2">
                    <div class="flex gap-2 flex-wrap box-border">
                        <span v-if="!data.family?.data.description_title">{{ trans("Description Title is missing")
                            }}.</span>
                        <span v-if="!data.family?.data.description">{{ trans("Description is missing") }}.</span>
                        <span v-if="!data.family?.data.description_extra">{{ trans("Extra description is missing")
                            }}.</span>
                    </div>
                    {{ trans("Please") }}
                    <Link
                        @click="navigateTo"
                        class="underline font-bold">
                    {{ trans("add missing description fields") }}
                    </Link>.
                </div>
            </Message>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-4 mt-4">
            <div class="col-span-1 md:col-span-1 lg:col-span-2">
                <dd v-if="data.tags && data.tags.length > 0" class="font-medium flex flex-wrap gap-1 pb-3">
                    <span v-for="tag in data.tags" :key="tag.id" v-tooltip="'tag'"
                        class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
                        {{ tag.name }}
                    </span>
                </dd>
                <ProductCategoryCard :data="data.family?.data"  />
            </div>

            <div class="col-span-1 md:col-span-2 lg:col-span-4 offer">
                <template v-if="data.show_gr_vol">
                    <div class="mb-1">
                        {{ trans("Active Gold Reward offer") }}:
                        <Link :href="offerRoute(data.gr_offer_data)" class="secondaryLink">
                            {{ data.gr_offer_data?.label }}
                        </Link>
                    </div>
                    <div class="flex items-center gap-x-2">
                        <FamilyOfferLabelDiscount :offer="data.gr_offer_data" :not-follow-master="data.follow_master_gr === false" />
                        <FontAwesomeIcon
                            v-if="data.follow_master_gr === false"
                            :icon="faStarfighter"
                            v-tooltip="trans('Not following master GR')"
                            class="text-xl text-red-500"
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>
                </template>
            </div>

            <div class="col-span-1 md:col-span-3 lg:col-span-2 space-y-4">
                <!-- Sales Analytics Compact -->
                <SalesAnalyticsCompact v-if="salesData" :salesData="salesData" />

                <!-- Product State Stats -->
                <ProductCategoryStats v-if="data.family?.data.stats" :stats="data.family?.data.stats" />

                <!-- Review Content -->
                <ReviewContent :data="data.family?.data"  />
            </div>
        </div>
    </div>
</template>

<style scoped>
.offer :deep(.background-primary) {
    background-color: #ff862f;
}

.offer :deep(.text-primary) {
    color:#ff862f;
}
</style>