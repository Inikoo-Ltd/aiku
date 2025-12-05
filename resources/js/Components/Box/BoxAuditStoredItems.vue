<script setup lang='ts'>
import JsBarcode from 'jsbarcode'
import { inject, onMounted } from 'vue'
import { trans } from 'laravel-vue-i18n'
import BoxStatPallet from '@/Components/Pallet/BoxStatPallet.vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faNarwhal, faPallet, faQuestionCircle, faEnvelope, faPhone, faIdCardAlt, faPlus, faMinus, faCheck, faLink, faLayerPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Icon from '../Icon.vue'

library.add(faNarwhal, faPallet, faQuestionCircle, faIdCardAlt, faEnvelope, faPhone, faPlus, faMinus,faCheck, faLink, faLayerPlus)

const props = defineProps<{
    auditData: {
        reference: string
        state_icon: {
            icon: string
            class: string
            tooltip: string
        }
        number_audited_pallets: number
        number_audited_stored_items: number
        number_audited_stored_items_with_additions: number
        number_audited_stored_items_with_with_subtractions: number
        number_audited_stored_items_with_with_stock_checked: number
        number_associated_stored_items: number
        number_created_stored_items: number
    }
    boxStats: {
        customer: {
            reference: string
            contact_name: string
            company_name: string
            email: string
            phone: string
        }
        fulfilment: {
            slug: string
        }
        slug: string
        number_pallets: number
    }
    isHidePalletCount?: boolean
}>()

const locale = inject('locale', aikuLocaleStructure)


onMounted(() => {
    JsBarcode('#palletDeliveryBarcode', route().routeParams.storedItemAudit, {
        lineColor: "rgb(41 37 36)",
        width: 2,
        height: 50,
        displayValue: true
    })
})


</script>

<template>
    <div class="h-min grid grid-cols-2 sm:grid-cols-4 border-t border-b border-gray-200 divide-x divide-gray-300">
        <!-- Box: Customer -->
        <BoxStatPallet class="py-1 sm:py-2 px-3" :label="boxStats.customer.contact_name" icon="fal fa-user">
            <!-- Field: Reference -->
            <Link as="a" v-if="boxStats?.customer?.reference"
                :href="route('grp.org.fulfilments.show.crm.customers.show', [route().params.organisation, boxStats.fulfilment.slug, boxStats.slug])"
                class="flex items-center w-fit flex-none gap-x-2 cursor-pointer secondaryLink">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Reference</span>
                    <FontAwesomeIcon icon='fal fa-id-card-alt' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ boxStats.customer.reference }}</dd>
            </Link>

            <!-- Field: Contact name -->
            <div v-if="boxStats.customer.contact_name"
                class="flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Contact name'" class="flex-none">
                    <span class="sr-only">Contact name</span>
                    <FontAwesomeIcon icon='fal fa-user' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ boxStats.customer.contact_name }}</dd>
            </div>


            <!-- Field: Company name -->
            <div v-if="boxStats.customer.company_name"
                class="flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Company name</span>
                    <FontAwesomeIcon icon='fal fa-building' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ boxStats.customer.company_name }}</dd>
            </div>

            <!-- Field: Email -->
            <div v-if="boxStats?.customer.email" class="flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Email'" class="flex-none">
                    <span class="sr-only">Email</span>
                    <FontAwesomeIcon icon='fal fa-envelope' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <a :href="`mailto:${boxStats?.customer.email}`" class="text-base text-gray-500 hover:underline white w-full truncate">{{ boxStats?.customer?.email }}</a>
            </div>

            <!-- Field: Phone -->
            <div v-if="boxStats?.customer.phone" class="flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Phone'" class="flex-none">
                    <span class="sr-only">Phone</span>
                    <FontAwesomeIcon icon='fal fa-phone' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <a :href="`tel:${boxStats?.customer.phone}`" class="text-base text-gray-500 hover:underline white w-full truncate">{{ boxStats?.customer?.phone }}</a>
            </div>
        </BoxStatPallet>


        <!-- Box: Status -->
        <BoxStatPallet class="py-1 sm:py-2 px-3" :label="auditData.reference" icon="fal fa-truck-couch">
            

            <div class="flex items-center w-full flex-none gap-x-2 mb-1">
                <dt class="flex-none">
                    <span class="sr-only">{{ auditData.state_icon.tooltip }}</span>
                    <Icon :data="auditData.state_icon" />
                </dt>
                <dd class="text-base" :class='auditData.state_icon.class'>
                    {{ auditData.state_icon.tooltip }}
                </dd>
            </div>
            
            <div class="mb-4 h-full w-full py-1 px-2 flex flex-col border-t border-gray-300 items-center">
                <svg id="palletDeliveryBarcode" class="w-full h-full" ></svg>
            </div>
        </BoxStatPallet>


        <!-- Box: Order summary -->
        <BoxStatPallet class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
            <dl class="flex flex-col gap-y-2 text-gray-500 rounded-lg px-4 py-2">
                <div class="pt-2 first:pt-0 pr-2 flex flex-col gap-y-1.5 ">
                    <div v-if="!isHidePalletCount" class="grid grid-cols-7 gap-x-4 items-center justify-between">
                        <dt class="col-span-5 flex items-center gap-x-1.5">
                            <FontAwesomeIcon v-tooltip="trans('Pallet')" icon='fal fa-pallet' class='text-gray-400 text-sm' fixed-width aria-hidden='true' />
                            <span>{{ trans("Audited") }}</span>
                        </dt>
                        
                        <div class="relative col-span-2 justify-self-end font-medium overflow-hidden tabular-nums">
                            <Transition name="spin-to-right">
                                <div :key="props.auditData.number_audited_pallets">{{ props.auditData.number_audited_pallets }}</div>
                            </Transition>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-x-4 items-center justify-between">
                        <dt class="col-span-5 flex items-center gap-x-1.5">
                            <FontAwesomeIcon v-tooltip="trans(`Customer's SKU`)" icon='fal fa-narwhal' class='text-gray-400 text-sm' fixed-width aria-hidden='true' />
                            <span>{{ trans("Audited") }}</span>
                        </dt>
                        
                        <div class="relative col-span-2 justify-self-end font-medium overflow-hidden tabular-nums">
                            <Transition name="spin-to-right">
                                <div :key="props.auditData.number_audited_stored_items">{{ props.auditData.number_audited_stored_items }}</div>
                            </Transition>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-x-4 items-center justify-between">
                        <dt class="col-span-5 flex items-center gap-x-1.5">
                            <FontAwesomeIcon v-tooltip="trans(`Customer's SKU`)" icon='fal fa-plus' class='text-gray-400 text-sm' fixed-width aria-hidden='true' />
                            <span>{{ trans("Audited (with additions)") }}</span>
                        </dt>
                        
                        <div class="relative col-span-2 justify-self-end font-medium overflow-hidden tabular-nums">
                            <Transition name="spin-to-right">
                                <div :key="props.auditData.number_audited_stored_items_with_additions">{{ props.auditData.number_audited_stored_items_with_additions }}</div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Audited (with subtractions) -->
                    <div class="grid grid-cols-7 gap-x-4 items-center justify-between">
                        <dt class="col-span-5 flex items-center gap-x-1.5">
                            <FontAwesomeIcon v-tooltip="trans(`Customer's SKU`)" icon='fal fa-minus' class='text-gray-400 text-sm' fixed-width aria-hidden='true' />
                            <span>{{ trans("Audited (with subtractions)") }}</span>
                        </dt>
                        
                        <div class="relative col-span-2 justify-self-end font-medium overflow-hidden tabular-nums">
                            <Transition name="spin-to-right">
                                <div :key="props.auditData.number_audited_stored_items_with_with_subtractions">{{ props.auditData.number_audited_stored_items_with_with_subtractions }}</div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Audit with stock checked -->
                    <div class="grid grid-cols-7 gap-x-4 items-center justify-between">
                        <dt class="col-span-5 flex items-center gap-x-1.5">
                            <FontAwesomeIcon v-tooltip="trans(`Customer's SKU`)" icon='fal fa-check' class='text-gray-400 text-sm' fixed-width aria-hidden='true' />
                            <span>{{ trans("Audited (with stock checked)") }}</span>
                        </dt>
                        
                        <div class="relative col-span-2 justify-self-end font-medium overflow-hidden tabular-nums">
                            <Transition name="spin-to-right">
                                <div :key="props.auditData.number_audited_stored_items_with_with_stock_checked">{{ props.auditData.number_audited_stored_items_with_with_stock_checked }}</div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Associated -->
                    <div class="grid grid-cols-7 gap-x-4 items-center justify-between">
                        <dt class="col-span-5 flex items-center gap-x-1.5">
                            <FontAwesomeIcon v-tooltip="trans(`Customer's SKU`)" icon='fal fa-link' class='text-gray-400 text-sm' fixed-width aria-hidden='true' />
                            <span>{{ trans("Associated") }}</span>
                        </dt>
                        
                        <div class="relative col-span-2 justify-self-end font-medium overflow-hidden tabular-nums">
                            <Transition name="spin-to-right">
                                <div :key="props.auditData.number_associated_stored_items">{{ props.auditData.number_associated_stored_items }}</div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Created -->
                    <div class="grid grid-cols-7 gap-x-4 items-center justify-between">
                        <dt class="col-span-5 flex items-center gap-x-1.5">
                            <FontAwesomeIcon v-tooltip="trans(`Customer's SKU`)" icon='fal fa-layer-plus' class='text-gray-400 text-sm' fixed-width aria-hidden='true' />
                            <span>{{ trans("Created") }}</span>
                        </dt>
                        
                        <div class="relative col-span-2 justify-self-end font-medium overflow-hidden tabular-nums">
                            <Transition name="spin-to-right">
                                <div :key="props.auditData.number_created_stored_items">{{ props.auditData.number_created_stored_items }}</div>
                            </Transition>
                        </div>
                    </div>
                </div>

            </dl>
        </BoxStatPallet>
    </div>
</template>