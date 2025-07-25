<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref } from "vue"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import { faTrashAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Portfolio } from "@/types/portfolio"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"

library.add(faTrashAlt)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()

function itemRoute(portfolio: Portfolio) {
    return route(
        "grp.helpers.redirect_portfolio_item",
        [portfolio.id])


}

const isDeleteLoading = ref<boolean | string>(false)
const onDeletePortfolio = async (routeDelete: routeType, portfolioReference: string) => {
    isDeleteLoading.value = portfolioReference
    try {
        router[routeDelete.method || "delete"](route(routeDelete.name, routeDelete.parameters),
            {
                onStart: () => {
                    isDeleteLoading.value = portfolioReference
                },
                onFinish: () => {
                    isDeleteLoading.value = false
                },
                onSuccess: () => {
                    notify({
                        title: "Success",
                        text: `Portfolio ${portfolioReference} has been deleted`,
                        type: "success"
                    })
                }
            })

    } catch {
        notify({
            title: "Something went wrong.",
            type: "error"
        })
    }
}

const dummyImage = [
    {
        "id": "gid://shopify/ProductImage/63906764816711",
        "src": "https://cdn.shopify.com/s/files/1/0906/1842/9767/files/e7ca90da70759d0fc8e53eecfff56999.jpg?v=1752500955"
    }
]
</script>

<template>
    <!-- <pre>{{ data.data[0] }}</pre> -->
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(item_code)="{ item: portfolio }">
            <Link :href="itemRoute(portfolio)" class="primaryLink">
                {{ portfolio["item_code"] }}
            </Link>
        </template>


        <template #cell(location)="{ item: portfolio }">
            <AddressLocation :data="portfolio['location']" />
        </template>

        <template #cell(platform_status)="{ item: portfolio }">
            <FontAwesomeIcon v-if="portfolio.has_valid_platform_product_id" v-tooltip="trans('Has valid platform product id')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else v-tooltip="trans('Has valid platform product id')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-if="portfolio.exist_in_platform" v-tooltip="trans('Exist in platform')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else v-tooltip="trans('Exist in platform')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-if="portfolio.platform_status" v-tooltip="trans('Platform status')" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else v-tooltip="trans('Platform status')" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
        </template>

        <template #cell(created_at)="{ item: portfolio }">
            <div class="text-gray-500">{{ useFormatTime(portfolio["created_at"], {
                localeCode: locale.language.code,
                formatTime: "hm"
            }) }}
            </div>
        </template>

        <template #cell(action)="{ item: portfolio }">
            <Button @click="() => onDeletePortfolio(portfolio.routes.delete_route, portfolio.item_id)" :key="portfolio.item_id"
                    icon="fal fa-trash-alt" type="negative" :disabled="isDeleteLoading === portfolio.item_id"
                    :loading="isDeleteLoading === portfolio.item_id" />
        </template>

        <template #cell(matches)="{ item: portfolio }">
            <!-- <pre>{{ portfolio.platform_possible_matches }}</pre> -->
            <div v-if="!portfolio.platform_status && portfolio.platform_possible_matches?.raw_data?.length"  class="flex gap-x-2 items-center">
                <div class="h-9 min-w-9 w-auto max-w-9 shadow">
                    <img :src="portfolio.platform_possible_matches?.raw_data?.[0]?.images?.src" />
                </div>

                <div>
                    <span class="mr-1">{{ portfolio.platform_possible_matches?.matches_labels[0]}}</span>
                    <ButtonWithLink
                        v-tooltip="trans('Match to existing Shopify product')"
                        :routeTarget="{
                        method: 'post',
                            name: 'grp.models.portfolio.match_to_existing_shopify_product',
                            parameters: {
                                portfolio: portfolio.id,
                                shopify_product_id: portfolio.platform_possible_matches.raw_data?.[0]?.id
                            }
                        }"
                        :bindToLink="{
                            preserveScroll: true,
                        }"
                        icon=""
                        type="tertiary"
                        :label="trans('Match')"
                        size="xxs"
                    />
                </div>
            </div>
            <!-- <br />
            <br />

            <pre>{{ portfolio.id }}</pre> -->
        </template>

        
        <!-- Column: actions -->
        <template #cell(actions)="{ item: portfolio }">
            <div v-if="!portfolio.platform_status"  class="flex gap-x-2 items-center">
                <ButtonWithLink
                    v-tooltip="trans('Will create new product in Shopify')"
                    :routeTarget="{
                    method: 'post',
                        name: 'grp.models.portfolio.store_new_shopify_product',
                        parameters: {
                            portfolio: portfolio.id
                        },
                        body: {
                            shopify_product_id: portfolio.platform_possible_matches.raw_data?.[0]?.id
                        }
                    }"
                    isWithError
                    icon=""
                    :label="trans('Create new product')"
                    size="xxs"
                    type="tertiary"
                    :bindToLink="{
                        preserveScroll: true,
                    }"
                />
            </div>
        </template>
    </Table>
</template>
