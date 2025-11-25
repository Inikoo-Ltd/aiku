<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Tue, 18 Nov 2025 08:57:12 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { inject, onMounted, ref } from "vue";
    import { faInfoCircle } from "@fal";
    import Select from "primevue/select";
    import { trans } from "laravel-vue-i18n";
    import Textarea from 'primevue/textarea';
    import { useForm } from "@inertiajs/vue3";
    import InputText from 'primevue/inputtext';
    import InputNumber from 'primevue/inputnumber';
    import ToggleSwitch from 'primevue/toggleswitch';
    import PureInput from "@/Components/Pure/PureInput.vue";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import Button from "@/Components/Elements/Buttons/Button.vue";
    import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
    import axios from "axios";
import { notify } from "@kyvg/vue3-notification";

    library.add(faInfoCircle);

    const closeCreateEbayModal = inject("closeCreateEbayModal");
    const ebayName = inject("ebayName");
    const ebayId = inject("ebayId");
    const customerSalesChannelId = inject("customerSalesChannelId");

    const isVAT = ref(false);

    const returnProfiles = ref([]);
    const shippingProfiles = ref([]);
    const paymentProfiles = ref([]);
    const shippingServices = ref([]);
    const taxCategories = ref([]);
    const returnAcceptedOptions = ref([
        {name: trans("Returns Accepted"), value: true},
        {name: trans("Returns Not Accepted"), value: false}
    ]);
    const returnPayers = ref([
        {name: trans("Seller"), value: "SELLER"},
        {name: trans("Buyer"), value: "BUYER"}
    ]);
    const returnWithinOptions = ref([
        {name: trans("14 Days"), value: 14},
        {name: trans("30 Days"), value: 30},
        {name: trans("60 Days"), value: 60}
    ]);

    const isLoadingStep = ref(false)
    const errors = ref({})

    const form = useForm({
            app: "Ebay",
            account: ebayName.value,
            return_policy_id: null,
            payment_policy_id: null,
            fulfillment_policy_id: null,
            tax_category_id: null,
            is_vat_adjustment: false,
            shipping_max_dispatch_time: 0,
            shipping_service: null,
            shipping_price: 0,
            return_accepted: null,
            return_payer: null,
            return_within: 1,
            return_description: ""
    });

    const submitForm = async () => {
        isLoadingStep.value = true
        try {
            const response = await axios.patch(route('retina.models.customer_sales_channel.ebay_update', {
                customerSalesChannel: customerSalesChannelId.value
            }), form.data());
            window.location.href = route('retina.dropshipping.customer_sales_channels.redirect', customerSalesChannelId.value)
            isLoadingStep.value = false
        } catch (err) {
            isLoadingStep.value = false;
            errors.value = err.response?.data?.errors;
        }
    }

    onMounted(async () => {
        const {data} = await axios.get(route('retina.dropshipping.customer_sales_channels.ebay_policies.index', {
            ebayUser: ebayId.value
        }));

        if(data?.fulfillment_policies?.total > 0) {
            let shippingPolicies = data?.fulfillment_policies?.fulfillmentPolicies?.map((shipping) => {
                return {
                    name: shipping.name,
                    value: shipping.fulfillmentPolicyId
                };
            })

            shippingProfiles.value = shippingPolicies;
        }
        if(data?.return_policies?.total > 0) {
            let returnPolicies = data?.return_policies?.returnPolicies?.map((returns) => {
                return {
                    name: returns.name,
                    value: returns.returnPolicyId
                };
            })
            returnProfiles.value = returnPolicies;
        }
        if(data?.payment_policies?.total > 0) {
            let paymentPolicies = data?.payment_policies?.paymentPolicies?.map((payment) => {
                return {
                    name: payment.name,
                    value: payment.paymentPolicyId
                };
            })

            paymentProfiles.value = paymentPolicies;
        }
        if(data?.shipping_services?.length > 0) {
            let shippingServicesData = data?.shipping_services;
            shippingServices.value = shippingServicesData;
        }
        if(data?.tax_categories?.length > 0) {
            taxCategories.value = data?.tax_categories;
        }
    })
</script>

<template>
    <form @submit.prevent="submitForm" class="flex flex-col gap-6">
        <hr class="w-full border-t" />

        <div class="flex flex-col w-full border rounded-xl">
            <div class="w-full px-4 py-2 bg-gray-100">
                <span class="font-semibold">{{ trans("Basic settings") }}</span>
            </div>
            <div class="grid lg:grid-cols-2">
                <div class="flex flex-col gap-2 w-full md:w-80 p-4">
                    <label class="font-semibold">{{ trans("App") }}</label>
                    <PureInput
                        type="text"
                        v-model="form.app"
                        readonly
                    />
                </div>

                <div class="flex flex-col gap-2 w-full md:w-80 p-4">
                    <label class="font-semibold">{{ trans("Account") }}</label>
                    <PureInput
                        type="text"
                        v-model="form.account"
                        readonly
                    />
                </div>

                <div class="flex flex-col gap-2 p-4 w-full md:w-80">
                    <label class="font-semibold">{{ trans("VAT Rates") }}</label>
                    <ToggleSwitch v-model="form.is_vat_adjustment" />
                </div>

                <div v-if="form.is_vat_adjustment" class="flex flex-col gap-2 w-full md:w-80 p-4">
                    <label class="font-semibold">{{ trans("VAT") }}</label>
                    <Select v-model="form.tax_category_id" :options="taxCategories" optionLabel="label" optionValue="value" class="w-full" />
                </div>
            </div>
        </div>

        <hr class="w-full border-t" />

        <div class="flex flex-col w-full border rounded-xl">
            <div class="bg-gray-100 px-4 py-2">
                <span class="font-semibold">{{ trans("Returns") }}</span>
            </div>

            <div class="flex flex-col gap-4 p-4">
                <div class="flex flex-col w-full border rounded-xl">
                    <div class="bg-gray-100 px-4 py-2">
                        <span class="font-semibold">{{ trans("Return Business Policy") }}</span>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex flex-col gap-2 pt-4 px-4">
                            <span>{{ trans("Please note that selecting Returns profile will override any returns details set below.") }}</span>
                        </div>
                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Profile") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.return_policy_id" :options="returnProfiles"
                                        @update:model-value="errors.return_policy_id = null "
                                        optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select eBay return policy')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                            <p v-if="errors.return_policy_id" class="text-sm text-red-600 mt-1">{{ errors.return_policy_id?.[0] }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col w-full border rounded-xl">
                    <div class="bg-gray-100 px-4 py-2">
                        <span class="font-semibold">{{ trans("Return Settings") }}</span>
                    </div>
                    <div class="grid lg:grid-cols-2">
                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Return accepted") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.return_accepted" :options="returnAcceptedOptions"
                                        @update:model-value="errors.return_accepted = null "
                                        optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select returns accepted')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                            <p v-if="errors.return_accepted" class="text-sm text-red-600 mt-1">{{ errors.return_accepted?.[0] }}</p>
                        </div>

                        <div class="flex flex-col gap-2 p-4" v-if="form.return_accepted">
                            <label class="font-semibold">{{ trans("Return paid by") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.return_payer" :options="returnPayers"
                                        @update:model-value="errors.return_payer = null "
                                        optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select return paid by')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                            <p v-if="errors.return_payer" class="text-sm text-red-600 mt-1">{{ errors.return_payer?.[0] }}</p>
                        </div>

                        <div class="flex flex-col gap-2 p-4" v-if="form.return_accepted">
                            <label class="font-semibold">{{ trans("Return within (day)") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.return_within" @update:model-value="errors.return_within = null " :options="returnWithinOptions" optionLabel="name" optionValue="value" class="w-full" />
                            </div>
                            <p v-if="errors.return_within" class="text-sm text-red-600 mt-1">{{ errors.return_within?.[0] }}</p>
                        </div>

                        <div class="flex flex-col gap-2 w-full md:w-96 p-4" v-if="form.return_accepted">
                            <label class="font-semibold">{{ trans("Detailed return policy explanation") }}</label>
                            <Textarea v-model="form.return_description" rows="5" @update:model-value="errors.return_description = null " />
                            <p v-if="errors.return_description" class="text-sm text-red-600 mt-1">{{ errors.return_description?.[0] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="w-full border-t" />

        <div class="flex flex-col w-full border rounded-xl">
            <div class="bg-gray-100 px-4 py-2">
                <span class="font-semibold">{{ trans("Shipping") }}</span>
            </div>

            <div class="flex flex-col gap-4 p-4">
                <div class="flex flex-col w-full border rounded-xl">
                    <div class="bg-gray-100 px-4 py-2">
                        <span class="font-semibold">{{ trans("Postage Business Policy") }}</span>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex flex-col gap-2 pt-4 px-4">
                            <span>{{ trans("Please note that selecting Postage profile will override any shipping details set below.") }}</span>
                        </div>
                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Profile") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.fulfillment_policy_id"
                                        @update:model-value="errors.fulfillment_policy_id = null "
                                        :options="shippingProfiles" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select ebay shipping policy')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                            <p v-if="errors.fulfillment_policy_id" class="text-sm text-red-600 mt-1">{{ errors.fulfillment_policy_id?.[0] }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col w-full border rounded-xl">
                    <div class="bg-gray-100 px-4 py-2">
                        <span class="font-semibold">{{ trans("Shipping Settings") }}</span>
                    </div>
                    <div class="grid lg:grid-cols-2">
                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Shipping service") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.shipping_service"
                                        @update:model-value="errors.shipping_service = null"
                                        :options="shippingServices" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select shipping services')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                            <p v-if="errors.shipping_service" class="text-sm text-red-600 mt-1">{{ errors.shipping_service?.[0] }}</p>
                        </div>

                        <div class="flex flex-col gap-2 w-full md:w-80 p-4">
                            <label class="font-semibold">{{ trans("Shipping price") }}</label>
                            <InputNumber v-model="form.shipping_price" @update:model-value="errors.shipping_price = null" inputId="integeronly" fluid />
                            <p v-if="errors.shipping_price" class="text-sm text-red-600 mt-1">{{ errors.shipping_price?.[0] }}</p>
                        </div>

                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Max dispatch time (day)") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <InputNumber v-model="form.shipping_max_dispatch_time" @update:model-value="errors.shipping_max_dispatch_time = null" inputId="integeronly" fluid />
                                <p v-if="errors.shipping_max_dispatch_time" class="text-sm text-red-600 mt-1">{{ errors.shipping_max_dispatch_time?.[0] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="w-full border-t" />

        <div class="flex flex-col w-full border rounded-xl">
            <div class="bg-gray-100 px-4 py-2">
                <span class="font-semibold">{{ trans("Payments") }}</span>
            </div>

            <div class="flex flex-col gap-4 p-4">
                <div class="flex flex-col w-full border rounded-xl">
                    <div class="bg-gray-100 px-4 py-2">
                        <span class="font-semibold">{{ trans("Payment Business Policy") }}</span>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex flex-col gap-2 pt-4 px-4">
                            <span>{{ trans("Please note if your account has been enabled for eBay managed payments, you need to select eBay payments as your payment profile.") }}</span>
                        </div>
                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Profile") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.payment_policy_id"
                                        @update:model-value="errors.payment_policy_id = null"
                                        :options="paymentProfiles" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select eBay payment policy')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                            <p v-if="errors.payment_policy_id" class="text-sm text-red-600 mt-1">{{ errors.payment_policy_id?.[0] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="w-full border-t" />

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" :loading="isLoadingStep" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
