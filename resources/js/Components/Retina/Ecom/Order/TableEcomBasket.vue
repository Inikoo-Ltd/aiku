<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import ConditionIcon from "@/Components/Utils/ConditionIcon.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { routeType } from "@/types/route"
import { Table as TableTS } from "@/types/Table"
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { debounce, get, set } from "lodash-es"
import { inject, ref } from "vue"
import { useLayoutStore } from "@/Stores/retinaLayout"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import Discount from "@/Components/Utils/Label/Discount.vue"
import GridProducts from "@/Components/Product/GridProducts/GridProducts.vue"

const props = defineProps<{
    data: any[] | TableTS
    tab?: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
}>()

const layout = inject("layout", {})
const locale = inject("locale", retinaLayoutStructure)


// Section: Quantity
const listState = ref({})
const isLoading = ref<string | boolean>(false)
const onUpdateQuantity = (routeUpdate: routeType, idTransaction: number, value: number) => {
    router.patch(
        route(routeUpdate.name, routeUpdate.parameters),
        {
            quantity_ordered: Number(value)
        },
        {
            onError: (e: any) => {
                notify({
                    title: trans("Something went wrong"),
                    text: e.message,
                    type: "error"
                })
            },
            onStart: () => {
                set(listState.value, [idTransaction, "quantity"], "loading"),
                    isLoading.value = "quantity" + idTransaction
            },
            onSuccess: () => {
                set(listState.value, [idTransaction, "quantity"], "success")
                layout.reload_handle()
            },
            onFinish: () => {
                isLoading.value = false,
                    setTimeout(() => {
                        set(listState.value, [idTransaction, "quantity"], null)
                    }, 3000)
            },
            only: ["transactions", "summary", "total_to_pay", "balance", "iris"],
            preserveScroll: true
        }
    )
}

const debounceUpdateQuantity = debounce(
    (routeUpdate: routeType, idTransaction: number, value: number) => {
        onUpdateQuantity(routeUpdate, idTransaction, value)
    },
    500
)

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
                    <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
                </div>
                <div v-else class="text-gray-400 italic text-xs">
                    {{ trans('Stock :xquantityx available', { xquantityx: locale.number(item.available_quantity || 0) })
                    }}
                </div>

                <Discount v-if="Object.keys(item.offers_data || {})?.length" :offers_data="item.offers_data" />
            </div>
        </template>

        <!-- Column: Quantity -->
        <template #cell(quantity_ordered)="{ item }">
            <div class="px-2 relative text-right w-full">
                <div class="w-fit ml-auto">
                    <NumberWithButtonSave :modelValue="item.quantity_ordered" @update:modelValue="(value: number) => {
                        console.log('item.quantity_ordered', item.quantity_ordered, value)
                        item.quantity_ordered != value ? debounceUpdateQuantity(item.updateRoute, item.id, value) : null
                    }" :routeSubmit="item.updateRoute" key-submit="quantity_ordered" isWithRefreshModel
                        noSaveButton noUndoButton :min="1" :max="item.available_quantity" />
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
                    @finish="() => isLoading = false" @success="() => layout.reload_handle()"
                    v-tooltip="trans('Unselect this product')" :preserveScroll="true">
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
                        <Discount v-if="Object.keys(item.offers_data || {})?.length" :offers_data="item.offers_data"
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
                                    <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
                                </div>
                                <div v-else class="text-gray-400 italic text-xs">
                                    {{ trans('Stock :xquantityx available', {
                                        xquantityx:
                                            locale.number(item.available_quantity || 0) })
                                    }}
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col justify-between pt-3">
                            <div class="flex gap-x-2 h-fit items-center">
                                <div>
                                    <div class="w-fit ml-auto">
                                        <NumberWithButtonSave :modelValue="item.quantity_ordered" @update:modelValue="(value: number) => {
                                            console.log('item.quantity_ordered', item.quantity_ordered, value)
                                            item.quantity_ordered != value ? debounceUpdateQuantity(item.updateRoute, item.id, value) : null
                                        }" :routeSubmit="item.updateRoute" key-submit="quantity_ordered"
                                            isWithRefreshModel noSaveButton noUndoButton :min="1"
                                            :max="item.available_quantity" />
                                    </div>

                                    <ConditionIcon class="absolute ml-1 top-[65%] right-[40%] -translate-y-1/2 text-base"
                                        :state="get(listState, [item.id, 'quantity'], null)" />
                                </div>

                                <div class="">
                                    <Link
                                        :href="item.deleteRoute?.name ? route(item.deleteRoute.name, item.deleteRoute.parameters) : '#'"
                                        as="button" :method="item.deleteRoute.method"
                                        @start="() => isLoading = 'unselect' + item.id"
                                        @finish="() => isLoading = false" @success="() => layout.reload_handle()"
                                        v-tooltip="trans('Unselect this product')" :preserveScroll="true">
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