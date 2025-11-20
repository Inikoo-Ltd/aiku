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
    <Table :resource="data" :name="tab">
        <!-- Column: Image -->
        <template #cell(image)="{ item }">
            <div class="flex relative w-8 aspect-square overflow-hidden">
                <Image
                    :src="item.image?.thumbnail"
                    class="w-full h-full object-contain"
                />
            </div>
        </template>

        <!-- Column: Code -->
        <template #cell(asset_code)="{ item }">
            <a v-if="item.webpage_url" :href="item.webpage_url" class="primaryLink" >
                {{ item.asset_code }}
            </a>
            <span v-else>
                  {{ item.asset_code }}
            </span>
        </template>

        <!-- Column: Name -->
        <template #cell(asset_name)="{ item }">
            <div>
                <div><span v-if="Number(item.units) > 1" class="mr-1">{{ Number(item.units) }}x</span>{{ item.asset_name }}</div>
                <div v-if="!item.available_quantity">
                    <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
                </div>
                <div v-else class="text-gray-500 italic text-xs">
                    Stock: {{ locale.number(item.available_quantity || 0) }} available
                </div>
                
                <Discount v-if="Object.keys(item.offers_data || {})?.length" :offers_data="item.offers_data" />
            </div>
        </template>

        <!-- Column: Quantity -->
        <template #cell(quantity_ordered)="{ item }">
            <div class="px-2 relative text-right w-full">
                <!-- <pre>{{ item }}</pre> -->
                <div class="w-fit ml-auto">
                    <NumberWithButtonSave
                        v-model="item.quantity_ordered"
                        @update:modelValue="(value: number) => {
                            debounceUpdateQuantity(item.updateRoute, item.id, value)
                        }"
                        :routeSubmit="item.updateRoute"
                        key-submit="quantity_ordered"
                        xxsaveOnForm
                        noSaveButton
                        noUndoButton
                        :min="1"
                    />
                </div>

                <ConditionIcon
                    class="absolute ml-2 top-1/2 -translate-y-1/2 text-base"
                    :state="get(listState, [item.id, 'quantity'], null)"
                />

            </div>
        </template>

        <!-- Column: Action -->
        <template #cell(actions)="{ item }">
            <div class="flex gap-2 px-2">
                <Link
                    :href="item.deleteRoute?.name ? route(item.deleteRoute.name, item.deleteRoute.parameters) : '#'"
                    as="button"
                    :method="item.deleteRoute.method"
                    @start="() => isLoading = 'unselect' + item.id"
                    @finish="() => isLoading = false"
                    @success="()=>layout.reload_handle()"
                    v-tooltip="trans('Unselect this product')"
                    :preserveScroll="true"
                >
                    <Button icon="fal fa-times" type="negative" size="xs" :loading="isLoading === 'unselect' + item.id" />
                </Link>
            </div>
        </template>
    </Table>

</template>