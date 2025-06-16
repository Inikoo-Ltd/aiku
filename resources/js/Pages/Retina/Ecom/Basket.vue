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
import { Head, Link } from "@inertiajs/vue3"
import { ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { routeType } from "@/types/route"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTag } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import Textarea from "primevue/textarea"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { debounce } from 'lodash-es';
//import { debounce } from 'lodash-es'; // WOWOWOW
library.add(faTag)

const props = defineProps<{
    order: {
        public_notes: string | null
        voucher_code: string | null
    }
    transactions: {}
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
    }
    balance: string
    total_to_pay: string
    routes: {
        update_route: routeType
        submit_route: routeType
    }
}>()

const debSubmitForm = debounce((save: Function) => {
    save()
}, 500)

const isLoading = ref<string | boolean>(false)


const noteToSubmit = ref(props.order?.public_notes)
const recentlySuccessNote = ref(false)
const recentlyErrorNote = ref(false)
const isLoadingNote = ref(false)
const onSubmitNote = async () => {
    try {
        isLoadingNote.value = true
        await axios.patch(route(props.routes.update_route.name, props.routes.update_route.parameters), {
            public_notes: noteToSubmit.value
        })


        isLoadingNote.value = false
        recentlySuccessNote.value = true
        setTimeout(() => {
            recentlySuccessNote.value = false
        }, 3000)
    } catch  {
        recentlyErrorNote.value = true
        setTimeout(() => {
            recentlyErrorNote.value = false
        }, 3000)

        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to update the note, try again."),
            type: "error",
        })
    }
}
const debounceSubmitNote = debounce(onSubmitNote, 800)
</script>

<template>
    <Head :title="trans('Basket')" />
    
    <div class="mt-5 ml-6">
        <ButtonWithLink
            :icon="faArrowLeft"
            label="Continue shopping"
            url="/"
            type="tertiary"
            fullLoading
        />
    </div>

    <div v-if="!transactions" class="text-center text-gray-500 text-2xl pt-6">
        {{ trans("Your basket is empty") }}
    </div>

    <div v-else class="w-full px-4 mt-8">
        <div class="px-4 text-xl">
            <span class="text-gray-500">{{ trans("Order number") }}</span> <span class="font-bold">#{{ order.reference }}</span>
        </div>
        
        <CheckoutSummary
            :summary
            :balance
        />

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
            
                <!-- Column: Code -->
                <Column
                    xxsortable="columnHeader.sortable"
                    xxsortField="`columns.${colSlug}.${intervals.value}.raw_value`"
                    field="asset_code"
                    class="w-28"
                    sortable
                >
                    <template #header>
                        <div class="px-2 text-xs md:text-base flex items-center w-full gap-x-2 font-semibold text-gray-600">
                            Code
                        </div>
                    </template>
                </Column>
                
                <!-- Column: Product name -->
                <Column
                    field="asset_name"
                    sortable
                >
                    <template #header>
                        <div class="px-2 text-xs md:text-base flex items-center w-full gap-x-2 font-semibold text-gray-600">
                            {{ trans("Product name") }}
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

                <!-- Column: Amount net -->
                <Column
                    field="net_amount"
                    class="w-36"
                    sortable
                >
                    <template #header>
                        <div class="text-right px-2 text-xs md:text-base w-full gap-x-2 font-semibold text-gray-600">
                            {{ trans("Amount net") }}
                        </div>
                    </template>

                    <template #body="{ data: dataBody }">
                        <div class="px-2 relative text-right">
                            {{ new Intl.NumberFormat('en', { style: "currency", currency: dataBody.currency_code, }).format(dataBody.net_amount) }}
                        </div>
                    </template>
                </Column>

                <!-- Column: Actions -->
                <Column
                    class="w-36"
                >
                    <template #header>
                        <div class="px-2 text-xs md:text-base w-full gap-x-2 font-semibold text-gray-600">
                            {{ trans("Actions") }}
                        </div>
                    </template>

                    <template #body="{ data: dataBody }">
                        <div class="flex gap-2 px-2">
                            <Link
                                :href="dataBody.deleteRoute?.name ? route(dataBody.deleteRoute.name, dataBody.deleteRoute.parameters) : '#'"
                                as="button"
                                :method="dataBody.deleteRoute.method"
                                @start="() => isLoading = 'unselect' + dataBody.id"
                                @finish="() => isLoading = false"
                                v-tooltip="trans('Unselect this product')"
                                :preserveScroll="true"
                            >
                                <Button icon="fal fa-times" type="negative" size="xs" :loading="isLoading === 'unselect' + dataBody.id" />
                            </Link>
                        </div>
                    </template>
                </Column>
        
            <!-- Row: Total (footer) -->
            <ColumnGroup type="footer">
                <Row>
                    <Column :colspan="4">
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
                    <Column :colspan="4">
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

        <div class="flex justify-between gap-x-4 mt-8 px-4">
            <div>
                
            </div>

            <div class="w-72">
                <!-- Section: Voucher code -->
                <IconField v-tooltip="trans('Voucher code (to apply the discount)')" class="mb-1.5">
                    <InputIcon>
                        <FontAwesomeIcon icon="fas fa-tag" class="" fixed-width aria-hidden="true" />
                    </InputIcon>
                    <InputText v-model="order.voucher_code" :placeholder="trans('Input voucher code')" fluid />
                </IconField>

                <!-- Section: Special instructions -->
                <div v-tooltip="trans('Special instructions')" class="relative">
                    <Textarea v-model="noteToSubmit" @update:modelValue="() => debounceSubmitNote()" rows="4" fluid :placeholder="trans('Special instructions if needed')" class="mb-2" style="resize: none" />
                    <div class="absolute top-2 right-2 flex items-center justify-center">
                        <LoadingIcon v-if="isLoadingNote" class="h-5 w-5 text-gray-500" />
                        <template v-else>
                            <FontAwesomeIcon v-if="recentlyErrorNote" icon="fas fa-exclamation-circle" class="h-5 w-5 text-red-500" aria-hidden="true" />
                            <FontAwesomeIcon v-if="recentlySuccessNote" icon="fas fa-check-circle" class="h-5 w-5 text-green-500" aria-hidden="true" />
                        </template>
                    </div>
                </div>
                
                <ButtonWithLink
                    :iconRight="faArrowRight"
                    label="Go to Checkout"
                    :routeTarget="{
                        name: 'retina.ecom.checkout.show'
                    }"
                    full
                />
                <div v-if="balance > total_to_pay" class="text-xs text-gray-500 italic tracking-wide">
                    {{ trans("You can pay totally with your current balance") }}
                </div>
                <div v-else-if="balance > 0" class="text-xs text-gray-500 italic tracking-wide">
                    {{ trans("you can pay partly with your balance now") }}
                </div>
            </div>
            

        </div>

    </div>
</template>