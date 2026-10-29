<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { CustomerSalesChannel } from '@/types/customer-sales-channel'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import axios from 'axios'
import { ctrans } from '@/Composables/useTrans'
import { Message } from 'primevue'
import { ref } from "vue"
import CloseSalesChannel from "@/Components/Retina/Platform/CloseSalesChannel.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStoreAltSlash } from "@fal"
library.add(faStoreAltSlash)

const props = defineProps<{
    customer_sales_channel: CustomerSalesChannel
}>()

const reconnectError = ref<string | null>(null)

// Method: Platform reconnect
const onClickReconnect = async () => {
    reconnectError.value = null
    try {
        const response = await axios[props.customer_sales_channel.reconnect_route.method || 'get'](
            route(
                props.customer_sales_channel.reconnect_route.name,
                props.customer_sales_channel.reconnect_route.parameters
            )
        )
        if (response.status !== 200 || !response.data) {
            throw new Error('Something went wrong. Try again later.')
        }
        window.location.href = response.data
    } catch (error: any) {
        reconnectError.value = error.message || ctrans('Something went wrong, please try again later.')
    }
}
</script>

<template>
    <div>
        <Message severity="error" class="mt-8 ">
            <div class="ml-2 font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
                <div>
                    <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-xl" fixed-width aria-hidden="true"/>
                    <div class="inline items-center gap-x-2">
                        {{ ctrans("Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.") }}
                    </div>
                </div>
            </div>
        </Message>




        <div class="w-8/12 mx-auto mt-8">
            <img
                src="/art/shopify_install_instruction.webp"
            />

            <div class="w-full sm:w-fit h-fit mt-4 text-red-400">
                {{ ctrans('Make sure you click the button "Install" in the Shopify dashboard to finalize the connection.') }}
                <span @click="() => onClickReconnect()" class="font-bold hover:text-red-500 underline cursor-pointer">{{ ctrans("Click here to install") }}</span>
            </div>
            <p v-if="reconnectError" class="mt-1 text-xs text-red-600">{{ reconnectError }}</p>
        </div>



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



    </div>
</template>