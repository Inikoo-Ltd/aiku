<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, computed } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrash as falTrash, faShoppingBasket, faEdit, faExternalLink, faStickyNote } from "@fal"
import { faCircle, faPlay, faTrash, faPlus } from "@fas"
import { faExclamationTriangle } from "@fad"
import StocksManagement from "@/Components/Warehouse/Inventory/StocksManagement/StocksManagement.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { StocksManagementTS } from "@/types/Inventory/StocksManagement"
import Image from "@common/Components/Image.vue"
library.add(faExclamationTriangle, faCircle, faTrash, falTrash, faShoppingBasket, faEdit, faExternalLink, faStickyNote, faPlay, faPlus)

const props = defineProps<{
    data: {
        trade_units: {
            brand: {}
            brand_routes: {
                index_brand: routeType
                store_brand: routeType
                update_brand: routeType
                delete_brand: routeType
                attach_brand: routeType
                detach_brand: routeType
            }
            tag_routes: {
                index_tag: routeType
                store_tag: routeType
                update_tag: routeType
                delete_tag: routeType
                attach_tag: routeType
                detach_tag: routeType
            }
            tags: {}[]
            tags_selected_id: number[]
        }[]
        stocks_management: StocksManagementTS
        currency_code: string
        is_quantity_excess: boolean
    }
}>()

const layout = inject('layout', layoutStructure)
const locale = inject("locale", aikuLocaleStructure)
const selectedImage = ref(0)
const showAllStats = ref(false)

const displayedStats = computed(() => {
    if (!props.data.stats) return []
    const filtered = props.data.stats.filter(item => !item.name.toLowerCase().includes("all"))
    return showAllStats.value ? filtered : filtered.slice(0, 6)
})

// watch(images, (newVal) => {
//     if (!newVal?.length || selectedImage.value > newVal.length - 1) {
//         selectedImage.value = 0
//     }
// }, { immediate: true })


console.log(props)

</script>


<template>
    <div class="grid md:grid-cols-4 gap-x-1 gap-y-4 p-6">
        <!-- Section: Trade Units -->
        <div class="md:col-span-2 pr-6">
            <div class="border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Image</th>
                            <th class="px-3 py-2 text-left">Name</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr v-for="tradeUnit in data.trade_units" :key="tradeUnit.id" class="hover:bg-gray-50">
                            <td class="px-3 py-2">
                                <Image v-if="tradeUnit.images?.[0]?.images" :src="tradeUnit.images[0].images"
                                    class="w-16 h-16 object-cover rounded" />
                            </td>
                            <td class="px-3 py-2">
                                {{ tradeUnit.name }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Card: Stock Summary -->
        <div class="border rounded mt-4 p-4 bg-white">
            <!-- Out of Stock -->
            <!-- <div class="border-b pb-2 mb-2">
                <p class="font-semibold text-gray-600">Out of stock</p>
            </div> -->

            <!-- Stock Value Section -->
            <div class="space-y-2 pr-10">
                <div class="grid grid-cols-5 gap-x-3 items-center">

                    <div class="col-span-2 xtext-right">
                        {{ ctrans("Future delivered cost") }}
                    </div>
                    <div class="col-span-1 text-right">
                        {{ ctrans("SKU value") }}
                    </div>

                    <div class="col-span-2 text-right">
                        {{ ctrans("Total stock value") }}
                    </div>
                </div>

                <div class="grid grid-cols-5 gap-x-3 items-center">

                    <div class="col-span-2 xtext-right text-2xl font-semibold">
                        {{ locale.currencyFormat(data.currency_code, data.stocks_management?.stock_cost?.current_supplier_sku_cost || 0) }}
                    </div>
                    <div class="col-span-1 text-right text-2xl font-semibold">
                        {{ locale.currencyFormat(data.currency_code, data.stocks_management?.stock_cost?.sku_value || 0) }}
                    </div>

                    <div class="col-span-2 text-right text-2xl font-semibold">
                        {{ locale.currencyFormat(data.currency_code, data.stocks_management?.stock_cost?.total_stock_value || 0) }}
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Section: Stocks Management -->
        <div class="md:col-span-2">
            <StocksManagement
                v-if="data.stocks_management"
                :data
                :stocks_management="data.stocks_management"
                :trade_units="data.trade_units"
            />
        </div>
    </div>
</template>
