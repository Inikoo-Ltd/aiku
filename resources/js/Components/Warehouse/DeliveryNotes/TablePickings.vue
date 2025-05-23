<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 13 September 2024 11:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Order } from "@/types/order"
import type { Links, Meta, Table as TableTS } from "@/types/Table"
import { routeType } from "@/types/route"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

// import { useFormatTime } from '@/Composables/useFormatTime'
import { useTruncate } from '@/Composables/useTruncate'
import Action from "@/Components/Forms/Fields/Action.vue"

const props = defineProps<{
    data: TableTS
    tab?: string
    routes: {
        pickers_list: routeType
        packers_list: routeType
    }
    state: string
}>()


function deliveryNoteRoute(deliveryNote: Order) {
    // console.log(route().current())
    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.delivery-notes.show":
            // return route(
            //     "grp.org.shops.show.discounts.campaigns.show",
            //     [route().params["organisation"], , route().params["shop"], route().params["customer"], deliveryNote.slug])
        default:
            return ''
    }
}


const isLoading = ref<{[key: string]: boolean}>({})
const onSubmitPickerPacker = (fetchRoute: routeType, selectedPicker: {}, rowIndex: number, scope: string) => {
    console.log('dd', selectedPicker)
    try {
        router.patch(route(fetchRoute.name, fetchRoute.parameters), {
            [`${scope}_id`]: selectedPicker.user_id
        }, {
            onStart: () => isLoading.value[rowIndex + scope + selectedPicker.user_id] = true,
            onFinish: () => isLoading.value[rowIndex + scope + selectedPicker.user_id] = false,
            preserveScroll: true
        })
    } catch (error) {
        
    }
}

const isModalPick = ref(null)
const isLoadingPick = ref(false)
const isErrorPicker = ref<string | null>(null)
const onClickPick = () => {
    if (!isModalPick.value?.picking_route?.name) {
        console.error("No route name found for picking")
        return
    }

    router.post(
        route(isModalPick.value.picking_route.name, isModalPick.value.picking_route.parameters),
        {

        }, 
        {
            onStart: () => {
                isLoadingPick.value = true
            },
            onError: (errors) => {
                isErrorPicker.value = errors.messages
                notify({
                    title: trans("Something went wrong"),
                    text: isErrorPicker.value,
                    type: "error",
                })
            },
            onSuccess: () => {
                isModalPick.value = null
            },
            onFinish: () => {
                isLoadingPick.value = false
            }
        }
    )
}
</script>

<template>
    <!-- <pre>{{ data.data[0] }}</pre> -->
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Reference -->
         {{ data }}
        <template #cell(org_stock_code)="{ item: deliveryNote }">
            <Link :href="deliveryNoteRoute(deliveryNote)" class="primaryLink">
                {{ deliveryNote.org_stock_code }}
            </Link>
        </template>
        <!-- Column: Date -->
        <template #cell(picking)="{ item: deliveryNote }">
            <Button
                @click="() => isModalPick = deliveryNote"
                type="secondary"
                :label="trans('Pick')"
                size="xs"
            />
        </template>
    </Table>
        <Modal
        :isOpen="!!isModalPick"
        @close="isModalPick = null, isErrorPicker = null"
        width="w-full max-w-lg"
    >
        <div class="sm:flex sm:items-start w-full">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <DialogTitle as="h3" class="text-base font-semibold">
                    {{ trans("Item Picking") }}
                </DialogTitle>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">
                        {{ trans("This action is under construction") }}
                    </p>
                </div>                
                

                <div class="mt-5 sm:flex sm:flex-row-reverse gap-x-2">
                    <Button
                        :loading="isLoadingPick"
                        @click="() => onClickPick()"
                        :label="trans('Under Construction')"
                        full
                        :disabled="true"
                    />

                    <Button
                        type="tertiary"
                        icccon="far fa-arrow-left"
                        :label="trans('cancel')"
                        
                        @click="() => (isModalPick = null)"
                    />
                </div>

                <p v-if="isErrorPicker" class="mt-2 text-xs text-red-500 italic">
                    *{{ isErrorPicker }}
                </p>
            </div>

        </div>
    </Modal>
</template>
