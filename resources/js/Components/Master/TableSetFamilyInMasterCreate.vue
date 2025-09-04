<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import InformationIcon from "../Utils/InformationIcon.vue"


// interface ProductItem {
//     id: number
//     name: string
//     code?: string
//     shop_id: number
//     shop_name: string
//     shop_currency: string
//     price: number | string
//     update_route: {
//         name: string
//         parameters: Record<string, any>
//     }
//     stock?: number
//     create_webpage?: boolean
//     currency?: string
// }

// interface ProductData {
//     id: number
//     name: string
//     image?: {
//         source: string
//     }
//     data: ProductItem[]
// }

// 👇 defineModel makes `v-model:data` available
const modelValue = defineModel<{}>()

const props = defineProps<{
    currency: string
    master_price: number
}>();

</script>

<template>
    <div class="bg-white border rounded-md shadow-sm p-3">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                Shops ({{ modelValue.data.length }})
            </h3>
        </div>

        <div v-if="modelValue.data.length" class="overflow-x-auto">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 text-left font-medium text-gray-600 border-b border-gray-200">
                        <th class="px-2 py-1">Code</th>
                        <th class="px-2 py-1">Name</th>
                        <th class="px-2 py-1 text-center">
                            Create Webpage?
                            <InformationIcon :information="trans('If checked, will create the family webpage')" />
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in modelValue.data" :key="item.id" class="transition-colors">
                        <td class="px-2 py-2 border-b border-gray-100 text-gray-600">
                            {{ item.code || "-" }}
                        </td>
                        <td class="px-2 py-2 border-b border-gray-100 font-medium">
                            {{ item.name }}
                        </td>
                        <td class="px-2 py-2 border-b border-gray-100">
                            <div class="flex justify-center items-center">
                                <input type="checkbox" v-model="item.create_webpage"
                                    v-tooltip="item.create_webpage ? trans('Will create the webpage as well') : trans('family webpage will not created')" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="text-xs text-gray-500 italic p-4 text-center bg-gray-50 rounded">
            {{ trans("No data available") }}
        </div>
    </div>
</template>
