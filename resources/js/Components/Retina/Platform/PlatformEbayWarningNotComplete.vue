<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { CustomerSalesChannel } from '@/types/customer-sales-channel'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import axios from 'axios'
import { ctrans } from '@/Composables/useTrans'
import { Message } from 'primevue'
import { checkVisible } from "@/Composables/Workshop"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { ref } from "vue";
import CloseSalesChannel from "@/Components/Retina/Platform/CloseSalesChannel.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStoreAltSlash } from "@fal"
library.add(faStoreAltSlash)

const props = defineProps<{
    platform: any
    customer_sales_channel: CustomerSalesChannel
}>()
</script>

<template>
    <Message severity="error" class="mt-8 ">
        <div class="ml-2 font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
            <div>
                <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-xl" fixed-width aria-hidden="true"/>
                <div class="inline items-center gap-x-2">
                    {{
                        ctrans("Your registration is not complete yet, you can continue here")
                    }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit">
                <ButtonWithLink
                    :routeTarget="{
                        name: 'retina.dropshipping.customer_sales_channels.create',
                        parameters: {
                            continueEbayRegistration: true
                        }
                    }" iconRight="fal fa-external-link"
                    :label="ctrans('Continue Registration')" zsize="xxs" type="secondary" full
                />
            </div>
        </div>
    </Message>

    <Message severity="error" class="mt-8 ">
        <div class="ml-2 font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
            <div>
                <div class="inline items-center gap-x-2">
                    {{
                        ctrans("Or delete the channel and try again")
                    }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit">

                <CloseSalesChannel :customerSalesChannel="customer_sales_channel">
                    <template #default="{ open }">
                        <Button :label="ctrans('Delete')" type="tertiary" @click="open">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-store-alt-slash" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </template>
                </CloseSalesChannel>
            </div>
        </div>
    </Message>
</template>
