<script setup lang="ts">
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Row from "primevue/row"
import ColumnGroup from "primevue/columngroup"
import { trans } from 'laravel-vue-i18n'
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import Toggle from "@/Components/Pure/Toggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faArrowLeft, faArrowRight } from "@fal"
import CheckoutSummary from "@/Components/Retina/Ecom/CheckoutSummary.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Image from "@/Components/Image.vue"
import { debounce } from "lodash"

defineProps<{
    transactions: {}
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
    }
}>()


const debSubmitForm = debounce((save: Function) => {
    save()
}, 500)

</script>

<template>
    <div v-if="!transactions" class="text-center text-gray-500 text-2xl pt-6">
        {{ trans("Your basket is empty") }}
    </div>

    <div v-else class="w-full px-4 mt-8">
        <div class="px-4 text-xl">
            <span class="text-gray-500">Order number</span> <span class="font-bold">#GB550706</span>
        </div>
        
        <CheckoutSummary :summary></CheckoutSummary>

        <DataTable :value="transactions.data" removableSort scrollable class="border-t border-gray-300 mt-8">
            <template #empty>
                <div class="flex items-center justify-center h-full text-center">
                    {{ trans("No data available.") }}
                </div>
            </template>
                <Column
                    field="image"
                    class="w-24"
                >

                    <template #body="{ data: dataBody }">
                        <div class="px-2 flex relative">
                            <Image
                                :src="dataBody.image"
                            />
                        </div>
                    </template>
                </Column>
            
                <Column
                    xxsortable="columnHeader.sortable"
                    xxsortField="`columns.${colSlug}.${intervals.value}.raw_value`"
                    field="asset_code"
                    class="w-28"
                >
                    <template #header>
                        <div class="px-2 text-xs md:text-base flex items-center w-full gap-x-2 font-semibold text-gray-600">
                            Code
                        </div>
                    </template>
                </Column>
                
                <Column
                    field="asset_name"
                >
                    <template #header>
                        <div class="px-2 text-xs md:text-base flex items-center w-full gap-x-2 font-semibold text-gray-600">
                            Product name
                        </div>
                    </template>
                </Column>

                <Column
                    field="quantity_ordered"
                    class="w-40"
                >
                    <template #header>
                        <div class="px-2 text-xs md:text-base text-right w-full gap-x-2 font-semibold text-gray-600">
                            Qty
                        </div>
                    </template>

                    <template #body="{ data: dataBody }">
                        <!-- <pre>{{ dataBody.updateRoute }}</pre> -->
                        <div class="px-2 relative text-right">
                            <NumberWithButtonSave
                                v-model="dataBody.quantity_ordered"
                                @update:modelValue="(value, save: Function) => {
                                    debSubmitForm(save)
                                }"
                                :routeSubmit="dataBody.updateRoute"
                                key-submit="quantity_ordered"
                                xxsaveOnForm
                                noSaveButton
                                noUndoButton
                                :min="0"
                            />
                        </div>
                    </template>
                </Column>

                <Column
                    field="net_amount"
                    class="w-36"
                >
                    <template #header>
                        <div class="text-right px-2 text-xs md:text-base w-full gap-x-2 font-semibold text-gray-600">
                            Amount net
                        </div>
                    </template>

                    <template #body="{ data: dataBody }">
                        <div class="px-2 relative text-right">
                            {{ new Intl.NumberFormat('en', { style: "currency", currency: dataBody.currency_code, }).format(dataBody.net_amount) }}
                        </div>
                    </template>
                </Column>
        
            <!-- Row: Total (footer) -->
            <ColumnGroup type="footer">
                <Row>
                    <Column :colspan="3">
                        <template #footer>
                            <div class="px-2 flex justify-end relative">
                                For the same day dispatch of your order before 12pm (£7.50)
                            </div>
                        </template>
                    </Column>
                    <Column>
                        <template #footer>
                            <div class="px-2 flex justify-end relative">
                                <Toggle :modelValue="true" />
                            </div>
                        </template>
                    </Column>
                    <Column />
                </Row>
                
                <Row>
                    <Column :colspan="3">
                        <template #footer>
                            <div class="px-2 flex justify-end relative">
                                Glass & ceramics insurance (£2.75)
                            </div>
                        </template>
                    </Column>
                    <Column>
                        <template #footer>
                            <div class="px-2 flex justify-end relative">
                                <Toggle :modelValue="true" />
                            </div>
                        </template>
                    </Column>
                    <Column />
                </Row>

                
            </ColumnGroup>
        </DataTable>

        <div class="flex justify-end gap-x-4 mt-4 px-4">
            
            <Button
                type="tertiary"
                :icon="faArrowLeft"
                label="Continue shopping"
            />

            <ButtonWithLink
                :iconRight="faArrowRight"
                label="Go to Checkout"
                :routeTarget="{
                    name: 'retina.ecom.checkout.show'
                }"
            />
        </div>

    </div>
</template>