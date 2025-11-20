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

    library.add(faInfoCircle);

    const closeCreateEbayModal = inject("closeCreateEbayModal");
    const ebayName = inject("ebayName");
    const ebayId = inject("ebayId");

    const isVAT = ref(false);

    const returnProfiles = ref([]);
    const shippingProfiles = ref([]);
    const paymentProfiles = ref([]);

    const form = useForm({
        app: "Ebay",
        account: ebayName.value,
        inclusiveVATPercentage: 0
    });

    const submitForm = async () => {
        closeCreateEbayModal();
    }

    onMounted(async () => {
        const {data} = await axios.get(route('retina.dropshipping.customer_sales_channels.ebay_policies.index', {
            ebayUser: ebayId.value
        }));

        if(data?.fulfillment_policies?.total > 0) {
            shippingProfiles.value = data?.fulfillment_policies?.fulfillmentPolicies?.map((item) => {
                return {
                    name: item.name,
                    value: item.fulfillmentPolicyId
                };
            })
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
                    <label class="font-semibold">{{ trans("VAT Rate") }}</label>
                    <ToggleSwitch v-model="isVAT" />
                </div>

                <div v-if="isVAT" class="flex flex-col gap-2 w-full md:w-80 p-4">
                    <label class="font-semibold">{{ trans("VAT Percentage") }}</label>
                    <InputNumber v-model="form.inclusiveVATPercentage" inputId="minmax" :min="0" :max="100" fluid />
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
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select eBay profile')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
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
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select return accepted')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Return paid by") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select return paid by')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Return within") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select return within')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 w-full md:w-96 p-4">
                            <label class="font-semibold">{{ trans("Detailed return policy explanation") }}</label>
                            <Textarea v-model="value" rows="5" />
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
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select eBay profile')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
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
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select return accepted')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 w-full md:w-80 p-4">
                            <label class="font-semibold">{{ trans("Shipping price") }}</label>
                            <InputNumber v-model="form.inclusiveVATPercentage" inputId="integeronly" fluid />
                        </div>

                        <div class="flex flex-col gap-2 w-full md:w-80 p-4">
                            <label class="font-semibold">{{ trans("Shipping service additional cost") }}</label>
                            <InputNumber v-model="form.inclusiveVATPercentage" inputId="integeronly" fluid />
                        </div>

                        <div class="flex flex-col gap-2 p-4">
                            <label class="font-semibold">{{ trans("Max dispatch time") }}</label>
                            <div class="flex items-center gap-2 w-full md:w-80">
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select return accepted')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
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
                                <Select v-model="form.site" :options="sites" optionLabel="name" optionValue="value" class="w-full" />
                                <FontAwesomeIcon v-tooltip="trans('Select eBay profile')" icon="fal fa-info-circle" class="hidden md:block size-5 text-black" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="w-full border-t" />

        <div class="flex md:justify-end gap-4">
            <Button type="secondary" size="sm" @click="closeCreateEbayModal">{{ trans("Cancel") }}</Button>
            <Button size="sm" @click="submitForm">{{ trans("Next") }}</Button>
        </div>
    </form>
</template>
