<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faInfoCircle, faSave, faExclamationTriangle } from "@fas";
import Message from "primevue/message";
import { Link, router, useForm } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faAlbumCollection, faEdit } from "@fal";
import { faPlus } from "@far";
import ProductCategoryCard from '@/Components/ProductCategoryCard.vue';
import SalesAnalyticsCompact from '@/Components/Product/SalesAnalyticsCompact.vue';
import ProductCategoryStats from '@/Components/Product/ProductCategoryStats.vue';
import { trans } from 'laravel-vue-i18n';
import Dialog from 'primevue/dialog';
import { ref } from 'vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import InputVolDiscount from '@/Components/Forms/Fields/InputVolDiscount.vue';

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        family: {
            data: any,
        },
        tags?: Array<any>
    },
    master_vol_gr_reward?: {
        show_gr_vol: boolean
        gr_vol_discount_quantity: number
        gr_vol_discount_percentage: number
        missing_gr_children_count?: number
        missing_gr_route?: { name: string, parameters: Record<string, any> }
    }
    salesData?: object
    actions?: any
}>();

const navigateTo = () => {
    const routeParams = route().params;

    switch (route().current()) {
        case "grp.masters.master_shops.show.master_sub_departments.master_families.show":
            router.visit(route("grp.masters.master_shops.show.master_sub_departments.master_families.edit", {
                ...routeParams,
                section: 1
            }));
            break;

        case "grp.masters.master_shops.show.master_families.show":
            router.visit(route("grp.masters.master_shops.show.master_families.edit", { ...routeParams, section: 1 }));
            break;

        default:
            router.visit(route("grp.masters.master_shops.show.master_departments.show.master_families.edit", {
                ...routeParams,
                section: 1
            }));
            break;
    }
}

const isOpenModalMasterGROffer = ref(false);

const grOfferForm = useForm({
    vol_gr_offer: {
        item_quantity: 0,
        percentage_off: 0,
    }
})

const openModalMasterGROffer = () => {
    grOfferForm.vol_gr_offer = {
        item_quantity: props.master_vol_gr_reward?.gr_vol_discount_quantity ?? 0,
        percentage_off: props.master_vol_gr_reward?.gr_vol_discount_percentage ?? 0,
    }
    isOpenModalMasterGROffer.value = true
}

const saveGROffer = () => {
    grOfferForm.patch(
        route('grp.models.master_product_category.update', props.data.family?.data?.id),
        {
            onSuccess: () => {
                isOpenModalMasterGROffer.value = false
            }
        }
    )
}
</script>

<template>
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
                <template v-if="master_vol_gr_reward?.show_gr_vol">
                    <div class="mb-1 font-bold">
                        {{ trans("Active Gold Reward offer") }}:
                    </div>
                    <div
                        v-if="props.master_vol_gr_reward?.gr_vol_discount_percentage"
                        @click="openModalMasterGROffer"
                        class="mb-1 w-fit py-2 px-4 border border-amber-400 rounded-md font-semibold flex cursor-pointer"
                    >
                        <div class="grid w-72">
                            <div class="flex">
                                {{ trans('Trigger Quantity') }}
                                <span class="ml-auto w-24">
                                    : {{ props.master_vol_gr_reward?.gr_vol_discount_quantity }} Qty
                                </span>
                            </div>
                            <div class="flex">
                                {{ trans('Discount Percentage') }}
                                <span class="ml-auto w-24">
                                    : {{ parseFloat(props.master_vol_gr_reward?.gr_vol_discount_percentage as any) }} %
                                </span>
                            </div>
                        </div>
                        <FontAwesomeIcon :icon="faEdit" class="ml-auto my-auto text-amber-500"/>
                    </div>
                    <div
                        v-else
                        class="mb-1 w-fit py-2 px-4 border border-amber-400 rounded-md font-semibold text-white bg-gradient-to-br from-amber-300 to-amber-500 cursor-pointer"
                        @click="openModalMasterGROffer"
                    >
                        <FontAwesomeIcon :icon="faPlus" />
                        {{ trans('Add Master GR Offer') }}
                    </div>
                    <Link
                        v-if="master_vol_gr_reward?.missing_gr_children_count && master_vol_gr_reward?.missing_gr_route"
                        :href="route(master_vol_gr_reward.missing_gr_route.name, master_vol_gr_reward.missing_gr_route.parameters)"
                        class="mt-1 text-sm text-yellow-600 flex items-center gap-1 hover:text-yellow-700"
                    >
                        <FontAwesomeIcon :icon="faExclamationTriangle" />
                        {{ master_vol_gr_reward.missing_gr_children_count }} {{ trans("shop family missing Gold Reward offer") }}
                    </Link>
                    <Dialog v-model:visible="isOpenModalMasterGROffer" modal header="Gold Reward Offer" :style="{ width: '50rem' }" closable :draggable="false" dismissableMask closeOnEscape>
                        <InputVolDiscount
                            :form="grOfferForm"
                            fieldName="vol_gr_offer"
                            :fieldData="{ initial_value: { item_quantity: 0, percentage_off: 0 } }"
                        />
                        <div class="flex">
                            <Button
                                :icon="faSave"
                                :type="'save'"
                                :class="'ml-auto'"
                                :loading="grOfferForm.processing"
                                @click="saveGROffer"
                            />
                        </div>
                    </Dialog>
                </template>
            </div>

            <div class="col-span-1 md:col-span-3 lg:col-span-2 space-y-4">
                <!-- Sales Analytics Compact -->
                <SalesAnalyticsCompact v-if="salesData" :salesData="salesData" />

                <!-- Product State Stats -->
                <ProductCategoryStats v-if="data.family?.data.stats" :stats="data.family?.data.stats" />
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
