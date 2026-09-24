<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import ConditionIcon from "@/Components/Utils/ConditionIcon.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { routeType } from "@/types/route"
import { Table as TableTS } from "@/types/Table"
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from '@/Composables/useTrans'
import { debounce, get, set } from "lodash-es"
import { inject, reactive, ref } from "vue"
import { useLayoutStore } from "@/Stores/retinaLayout"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import Discount from "@/Components/Utils/Label/Discount.vue"
import GridProducts from "@/Components/Product/GridProducts/GridProducts.vue"
import { pushGtmEvent, buildGtmProductPayload } from "@/Composables/useGtm"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faInfoCircle)

const props = defineProps<{
    data: any[] | TableTS
    tab?: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
}>()

const layout = inject("layout", {})
const locale = inject("locale", retinaLayoutStructure)

const outOfStockTooltip = (item: { held_quantity: number | string }) => ctrans(
    'This item went out of stock, so its quantity is set to 0 and it is not charged. If it is back in stock before you place the order, your :count is put back automatically.',
    { count: locale.number(Number(item.held_quantity)) }
)

const lowStockTooltip = (item: { quantity_ordered: number | string, available_quantity: number | string }) => ctrans(
    'Only :available showing in stock. We will do our best to send all :ordered. If we cannot, we will try to contact you first to offer a suitable replacement. Anything still missing is credited to your account balance.',
    { available: locale.number(Number(item.available_quantity)), ordered: locale.number(Number(item.quantity_ordered)) }
)


// Section: Quantity
const listState = ref({})
const isLoading = ref<string | boolean>(false)

const buildGtmBasketPayload = (item: any, quantity: number) => {
    return buildGtmProductPayload(
        {
            slug: item.product_slug,
            name: item.asset_name,
            price: item.price,
            currency_code: item.currency_code,
            family_code: item?.family_code,
        },
        { quantity }
    )
}

const pushBasketQuantityChange = (item: any, quantityDelta: number) => {
    if (!quantityDelta) {
        return
    }

    pushGtmEvent(
        quantityDelta > 0 ? "add_to_cart" : "remove_from_cart",
        buildGtmBasketPayload(item, Math.abs(quantityDelta))
    )
}

const pushRemoveFromBasket = (item: any) => {
    pushBasketQuantityChange(item, -(Number(item.quantity_ordered) || 0))
}

const refusedQuantityCount = reactive<Record<number, number>>({})

const onUpdateQuantity = (item: any, value: number) => {
    const routeUpdate: routeType = item.updateRoute
    const idTransaction = item.id
    const previousQuantity = Number(item.quantity_ordered) || 0
    const nextQuantity = Number(value) || 0

    router.patch(
        route(routeUpdate.name, routeUpdate.parameters),
        {
            quantity_ordered: Number(value)
        },
        {
            onError: (e: any) => {
                refusedQuantityCount[idTransaction] = (refusedQuantityCount[idTransaction] ?? 0) + 1
                notify({
                    title: ctrans("Something went wrong"),
                    text: e.message || e.quantity_ordered,
                    type: "error"
                })
            },
            onStart: () => {
                set(listState.value, [idTransaction, "quantity"], "loading"),
                    isLoading.value = "quantity" + idTransaction
            },
            onSuccess: () => {
                set(listState.value, [idTransaction, "quantity"], "success")
                pushBasketQuantityChange(item, nextQuantity - previousQuantity)
                layout.reload_handle()
            },
            onFinish: () => {
                isLoading.value = false,
                    setTimeout(() => {
                        set(listState.value, [idTransaction, "quantity"], null)
                    }, 3000)
            },
            only: ["transactions", "summary", "total_to_pay", "balance", "iris", "gr_gifts", "missed_offers"],
            preserveScroll: true
        }
    )
}

const debounceUpdateQuantity = debounce(
    (item: any, value: number) => {
        onUpdateQuantity(item, value)
    },
    500
)

const isOffersData = (offersData: any): boolean => {
    if (!offersData) return false
    const parsed = typeof offersData === 'string' ? JSON.parse(offersData) : offersData
    return Object.keys(parsed || {}).length > 0
}

</script>


<template>
    <Table :resource="data" :name="tab" class="hidden lg:block">
        <!-- Column: Image -->
        <template #cell(image)="{ item }">
            <div class="flex relative w-20 aspect-square overflow-hidden">
                <Image :src="item.image?.source" class="w-full h-full object-contain" />
            </div>
        </template>

        <!-- Column: Net Amount -->
        <template #cell(net_amount)="{ item }">
            <div class="text-right">
                <p class="" :class="item.gross_amount != item.net_amount ? 'text-green-500' : ''">
                    <span v-if="item.gross_amount != item.net_amount"
                        class="text-gray-500 line-through mr-1 opacity-70">{{ locale.currencyFormat(item.currency_code,
                        item.gross_amount) }}</span>
                    <span>{{ locale.currencyFormat(item.currency_code || '', item.net_amount) }}</span>
                </p>
            </div>
        </template>

        <!-- Column: Name -->
        <template #cell(asset_name)="{ item }">
          <!--  <pre> {{ item }}</pre> -->
            <div>
                <a v-if="item.webpage_url" :href="item.webpage_url" class="primaryLink -ml-1 italic text-xs">
                    {{ item.asset_code }}
                </a>
                <span v-else>
                    {{ item.asset_code }}
                </span>
                <div class="text-base"><span v-if="Number(item.units) > 1" class="mr-1">{{ Number(item.units)
                        }}x</span>{{ item.asset_name }}</div>
                <div v-if="!item.available_quantity">
                    <Tag :label="ctrans('Out of stock')" no-hover-color :theme="7" size="xxs" />
                    <span v-if="Number(item.held_quantity) > 0" v-tooltip="outOfStockTooltip(item)" class="text-xs text-gray-500 italic ml-1">{{ ctrans(':count kept, restored when back in stock', { count: locale.number(Number(item.held_quantity)) }) }}</span>
                </div>
                <div v-else-if="Number(item.quantity_ordered) > Number(item.available_quantity)" v-tooltip="lowStockTooltip(item)">
                    <Tag :label="ctrans('Only :count in stock', { count: locale.number(item.available_quantity) })" no-hover-color :theme="8" size="xxs" />
                    <FontAwesomeIcon icon="fal fa-info-circle" class="text-amber-500 ml-1 text-xs" fixed-width aria-hidden="true" />
                </div>
                <div v-else class="text-gray-400 italic text-xs">
                    {{ ctrans('Stock') }}  {{ locale.number(item.available_quantity || 0) }} {{ ctrans('available') }}
                </div>

                <Discount v-if="isOffersData(item.offers_data)" :offers_data="item.offers_data" />
            </div>
        </template>

        <!-- Column: Quantity -->
        <template #cell(quantity_ordered)="{ item }">
            <div class="px-2 relative text-right w-full">
                <div class="w-fit ml-auto">
                    <NumberWithButtonSave
                        :key="`${item.id}-${refusedQuantityCount[item.id] ?? 0}`"
                        :modelValue="item.quantity_ordered"
                        @update:modelValue="(value: number) => {
                            item.quantity_ordered != value ? debounceUpdateQuantity(item, value) : null
                        }"
                        :routeSubmit="item.updateRoute"
                        key-submit="quantity_ordered"
                        isWithRefreshModel
                        noSaveButton
                        noUndoButton
                        :min="0"
                        :denominator="item.quantity_ordered % 1 !== 0 ? Number(item.units) : undefined"
                        :disableInput="item.quantity_ordered % 1 !== 0"
                    />
                </div>

                <ConditionIcon class="absolute ml-2 top-1/2 -translate-y-1/2 text-base"
                    :state="get(listState, [item.id, 'quantity'], null)" />
            </div>
        </template>

        <!-- Column: Action -->
        <template #cell(actions)="{ item }">
            <div class="flex gap-2 px-2">
                <Link :href="item.deleteRoute?.name ? route(item.deleteRoute.name, item.deleteRoute.parameters) : '#'"
                    as="button" :method="item.deleteRoute.method" @start="() => isLoading = 'unselect' + item.id"
                    @finish="() => isLoading = false"
                    @success="() => { pushRemoveFromBasket(item); layout.reload_handle() }"
                    v-tooltip="ctrans('Unselect this product')" :preserveScroll="true">
                    <Button icon="fal fa-times" type="negative" size="xs"
                        :loading="isLoading === 'unselect' + item.id" />
                </Link>
            </div>
        </template>
    </Table>


    <GridProducts :resource="data" :showHeader="false" :preserve-scroll="true" class="mt-5 block lg:hidden"
        gridClass="lg:grid-cols-1 xl:grid-cols-1 grid grid-cols-1">

        <template #card="{ item }">
            <li class="flex py-1 relative border-b">
                <div v-if="item?.isLoadingRemove" class="inset-0 bg-gray-500/20 absolute z-10" />

                <div class="relative group">
                    <div :href="item.canonical_url"
                        class="flex justify-center items-center font-medium hover:underline min-w-14 min-h-14 size-14 shrink-0 overflow-hidden rounded-md border border-gray-200">
                        <Image :src="item.image?.source"
                            class="size-14 flex justify-center items-center group-hover:scale-110 transition-all" />
                    </div>
                </div>

                <div class="ml-4 flex justify-between gap-x-4 w-full text-xs">
                    <div class="flex flex-1 flex-col">
                        <Discount v-if="isOffersData(item.offers_data)" :offers_data="item.offers_data"
                            class="text-xxs" />

                        <div class="flex justify-between font-medium">
                            <div v-tooltip="item.asset_code" class="">
                                <a :href="item.webpage_url" class="font-medium hover:underline block line-clamp-2">
                                    <span v-if="item.units > 1" class="mr-1">
                                        {{ Number(item.units) }}x
                                    </span>
                                    {{ item.asset_name }}
                                </a>
                                <div class="text-xxs text-gray-400">{{ item.asset_code }}</div>
                                <div v-if="!item.available_quantity">
                                    <Tag :label="ctrans('Out of stock')" no-hover-color :theme="7" size="xxs" />
                    <span v-if="Number(item.held_quantity) > 0" v-tooltip="outOfStockTooltip(item)" class="text-xs text-gray-500 italic ml-1">{{ ctrans(':count kept, restored when back in stock', { count: locale.number(Number(item.held_quantity)) }) }}</span>
                                </div>
                                <div v-else-if="Number(item.quantity_ordered) > Number(item.available_quantity)" v-tooltip="lowStockTooltip(item)">
                                    <Tag :label="ctrans('Only :count in stock', { count: locale.number(item.available_quantity) })" no-hover-color :theme="8" size="xxs" />
                                    <FontAwesomeIcon icon="fal fa-info-circle" class="text-amber-500 ml-1 text-xs" fixed-width aria-hidden="true" />
                                </div>
                                <div v-else class="text-gray-400 italic text-xs">
                                     {{ ctrans('Stock') }}  {{ locale.number(item.available_quantity || 0) }} {{ ctrans('available') }}
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col justify-between pt-3">
                            <div class="flex gap-x-2 h-fit items-center">
                                <div>
                                    <div class="w-fit ml-auto">
                                        <NumberWithButtonSave :key="`${item.id}-${refusedQuantityCount[item.id] ?? 0}`" :modelValue="item.quantity_ordered" @update:modelValue="(value: number) => {
                                            item.quantity_ordered != value ? debounceUpdateQuantity(item, value) : null
                                        }" :routeSubmit="item.updateRoute" key-submit="quantity_ordered"
                                            isWithRefreshModel noSaveButton noUndoButton :min="1"
                                                                :denominator="item.quantity_ordered % 1 !== 0 ? Number(item.units) : undefined"
                                            :disableInput="item.quantity_ordered % 1 !== 0" />
                                    </div>

                                    <ConditionIcon class="absolute ml-1 top-[65%] right-[40%] -translate-y-1/2 text-base"
                                        :state="get(listState, [item.id, 'quantity'], null)" />
                                </div>

                                <div class="">
                                    <Link
                                        :href="item.deleteRoute?.name ? route(item.deleteRoute.name, item.deleteRoute.parameters) : '#'"
                                        as="button" :method="item.deleteRoute.method"
                                        @start="() => isLoading = 'unselect' + item.id"
                                        @finish="() => isLoading = false"
                                        @success="() => { pushRemoveFromBasket(item); layout.reload_handle() }"
                                        v-tooltip="ctrans('Unselect this product')" :preserveScroll="true">
                                        <Button icon="fal fa-times" type="negative" size="xs"
                                            :loading="isLoading === 'unselect' + item.id" />
                                    </Link>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="" :class="item.gross_amount != item.net_amount ? 'text-green-500' : ''">
                                    <span v-if="item.gross_amount != item.net_amount"
                                        class="text-gray-500 line-through mr-1 opacity-70">{{
                                            locale.currencyFormat(item.currency_code, item.gross_amount) }}</span>
                                    <span>{{ locale.currencyFormat(item.currency_code || '', item.net_amount) }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </template>
    </GridProducts>

</template>