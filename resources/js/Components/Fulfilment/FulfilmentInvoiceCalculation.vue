<script setup lang='ts'>
import Table from '@/Components/Table/Table.vue'
import { useLocaleStore } from '@/Stores/locale'
import { Calculation, ProductTransaction } from '@/types/Invoices'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ctrans } from '@/Composables/useTrans'

import { faFilePdf } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { routeType } from '@/types/route'
library.add(faFilePdf)


const locale = useLocaleStore()

const props = defineProps<{
    dataCalculations: Calculation
    dataTable: ProductTransaction[]
    pdfRoute: routeType
}>()
</script>

<template>
    <!-- <pre>{{ dataCalculations }}</pre> -->
    <div class="px-4 py-4">
        <div class="px-4 sm:px-6 lg:px-8 py-6 border border-gray-300 rounded-lg bg-gray-50">
            <div class="sm:flex sm:items-center">
                <div class="mb-0 space-y-1 sm:flex-auto">
                    <div class="text-2xl font-bold text-gray-600 leading-none">
                        Calculation
                    </div>
                    <div class="flex gap-x-2">
                        <div class="text-sm text-gray-500">
                            Profit: {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.profit_amount || 0) }},
                        </div>
                        <div class="text-sm text-gray-500">
                            Margin: {{ dataCalculations.margin_percentage || 0 }}%
                        </div>
                    </div>
                </div>

                <a v-if="pdfRoute.name" :href="route(pdfRoute.name, pdfRoute.parameters)" target="_blank" class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none text-base" v-tooltip="ctrans('Download in')">
                    <Button label="PDF" icon="fas fa-file-pdf" type="tertiary" />
                </a>
            </div>
            <div class="-mx-4 mt-8 flow-root sm:mx-0">
                <table class="min-w-full text-gray-500 tabular-nums">
                    <colgroup>
                        <col class="w-full sm:w-1/2" />
                        <col class="sm:w-1/6" />
                        <col class="sm:w-1/6" />
                        <col class="sm:w-1/6" />
                    </colgroup>
                    
                    <thead class="border-b border-gray-300">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold sm:pl-0">
                                Product Name
                            </th>
                            <th scope="col" class="hidden px-3 py-3.5 text-right text-sm font-semibold sm:table-cell">
                                Description
                            </th>
                            <th scope="col" class="hidden px-3 py-3.5 text-right text-sm font-semibold sm:table-cell">
                                Quantity
                            </th>
                            <th scope="col" class="py-3.5 pl-3 pr-4 text-right text-sm font-semibold sm:pr-0">
                                Price
                            </th>
                        </tr>
                    </thead>

                    <!-- Table: Body -->
                    <tbody>
                        <tr v-for="(product, idxProduct) in dataTable" :key="product.code + idxProduct" class="last:border-b border-gray-200">
                            <td class="max-w-0 py-5 pl-4 pr-3 text-sm sm:pl-0">
                                <div class="font-semibold text-gray-700">{{ product.code }}</div>
                                <div class="mt-1 truncate text-xs">{{ product.name }}</div>
                            </td>
                            <td class="hidden px-3 py-5 text-right text-sm sm:table-cell">
                                {{ product.description || '-' }}
                            </td>
                            <td class="hidden px-3 py-5 text-right text-sm sm:table-cell">
                                {{ (parseInt(product.quantity)).toFixed(0) }}
                            </td>
                            <td class="hidden px-3 py-5 text-right text-sm sm:table-cell">
                                {{ locale.currencyFormat(dataCalculations.currency, parseInt(product.price) || 0) }}
                            </td>
                            <!-- <td class="py-5 pl-3 pr-4 text-right text-sm sm:pr-0">{{ projectdd.price }}</td> -->
                        </tr>
                    </tbody>

                    <tfoot>
                        <tr class="">
                            <th scope="row" colspan="3"
                                class="hidden pl-4 pr-3 py-2 text-right text-sm font-normal sm:table-cell sm:pl-0">
                                Items Gross
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal sm:hidden">
                                Items Gross
                            </th>
                            <td class="pl-3 pr-4 py-2 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.item_gross || 0) }}
                            </td>
                        </tr>
                        <tr class="">
                            <th scope="row" colspan="3"
                                class="hidden pl-4 pr-3 py-2 text-right text-sm font-normal sm:table-cell sm:pl-0">
                                Discounts
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal sm:hidden">
                                Discounts
                            </th>
                            <td class="pl-3 pr-4 py-2 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.discounts_total || 0) }}
                            </td>
                        </tr>
                        <tr class="">
                            <th scope="row" colspan="3"
                                class="hidden pl-4 pr-3 py-2 text-right text-sm font-normal sm:table-cell sm:pl-0">
                                Items net
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal sm:hidden">
                                Items net
                            </th>
                            <td class="pl-3 pr-4 py-2 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.items_net || 0) }}
                            </td>
                        </tr>
                        <tr class="">
                            <th scope="row" colspan="3"
                                class="hidden pl-4 pr-3 py-2 text-right text-sm font-normal sm:table-cell sm:pl-0">
                                Charges
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal sm:hidden">
                                Charges
                            </th>
                            <td class="pl-3 pr-4 py-2 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.charges || 0) }}
                            </td>
                        </tr>
                        <tr class="">
                            <th scope="row" colspan="3"
                                class="hidden pl-4 pr-3 py-2 text-right text-sm font-normal sm:table-cell sm:pl-0">
                                Shipping
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal sm:hidden">
                                Shipping
                            </th>
                            <td class="pl-3 pr-4 py-2 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.shipping || 0) }}
                            </td>
                        </tr>

                        
                        <tr>
                            <th scope="row" colspan="3"
                                class="hidden pl-4 pr-3 pt-2 text-right text-sm font-normal sm:table-cell sm:pl-0">
                                Total net
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal sm:hidden">
                                Total net
                            </th>
                            <td class="pl-3 pr-4 pt-2 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.net_amount || 0) }}
                            </td>
                        </tr>

                        <!-- Row: Tax Var -->
                        <tr>
                            <th scope="row" colspan="3" class="hidden pl-4 pr-3 pt-2 text-right text-sm font-normal sm:table-cell sm:pl-0">
                                Tax <span class="text-sm text-gray-400">(VAR {{ dataCalculations.tax_percentage || 0}}%)</span>
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-4 text-left text-sm font-normal sm:hidden">
                                Tax <span class="text-sm text-gray-400">(VAR {{ dataCalculations.tax_percentage || 0}}%)</span>
                            </th>
                            <td class="pl-3 pr-4 pt-2 text-right text-sm sm:pr-0">{{locale.currencyFormat(dataCalculations.currency, dataCalculations.tax_amount || 0) || 0}}</td>
                        </tr>

                        <!-- Row: Paid -->
                        <tr>
                            <th scope="row" colspan="3" class="hidden pl-4 pr-3 pt-2 text-right font-normal text-sm sm:table-cell sm:pl-0">
                                Paid
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-4 text-left text-sm sm:hidden">
                                Paid
                            </th>
                            <td class="pl-3 pr-4 pt-2 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.payment_amount || 0) }}
                            </td>
                        </tr>

                        <!-- Row: Total -->
                        <tr class="border-t border-gray-300 text-gray-700 font-bold">
                            <th scope="row" colspan="3"
                                class="hidden pl-4 pr-3 pt-4 text-right text-sm sm:table-cell sm:pl-0">
                                Total
                            </th>
                            <th scope="row" class="pl-4 pr-3 pt-4 text-left text-sm sm:hidden">
                                Total
                            </th>
                            <td class="pl-3 pr-4 pt-4 text-right text-sm sm:pr-0">
                                {{ locale.currencyFormat(dataCalculations.currency, dataCalculations.total_amount || 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>