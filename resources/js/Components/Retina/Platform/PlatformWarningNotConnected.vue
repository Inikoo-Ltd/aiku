<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { CustomerSalesChannel } from '@/types/customer-sales-channel'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { Message } from 'primevue'
import { checkVisible } from "@/Composables/Workshop"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { ref } from "vue";

const props = defineProps<{
    customer_sales_channel: CustomerSalesChannel
    error_captcha: any
}>()

// Method: Platform reconnect
const onClickReconnect = async () => {
    console.log('customerSalesChannel', props.customer_sales_channel)
    try {
        const response = await axios[props.customer_sales_channel.reconnect_route.method || 'get'](
            route(
                props.customer_sales_channel.reconnect_route.name,
                props.customer_sales_channel.reconnect_route.parameters
            )
        )
        console.log('1111 response', response)
        if (response.status !== 200) {
            throw new Error('Something went wrong. Try again later.')
        } else {
            if(! response.data.id) {
                window.open(response.data, '_blank');
            } else {
                window.location.href = ''
            }
        }
    } catch (error: any) {
        notify({
            title: 'Something went wrong',
            text: error.message || 'Please try again later.',
            type: 'error'
        })
    }
}

const errorCaptcha = ref(null)

// Method: Platform test connect
const onClickTestConnection = async () => {
    try {
        const response = await axios[props.customer_sales_channel.test_route.method || 'get'](
            route(
                props.customer_sales_channel.test_route.name,
                props.customer_sales_channel.test_route.parameters
            )
        )

        if (response.status !== 200) {
            throw new Error('Something went wrong. Try again later.')
        } else {
            errorCaptcha.value = response.data?.data?.error_data
        }
    } catch (error: any) {
        notify({
            title: 'Something went wrong',
            text: error.message || 'Please try again later.',
            type: 'error'
        })
    }
}

const ipAddresses = [
    import.meta.env.VITE_IP_ADDRESS_1,
    import.meta.env.VITE_IP_ADDRESS_2,
    import.meta.env.VITE_IP_ADDRESS_3,
]

</script>

<template>
    <Message severity="error" class="mt-8 ">
        <div class="ml-2 font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
            <div>
                <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-xl" fixed-width aria-hidden="true"/>
                <div class="inline items-center gap-x-2">
                    {{
                        trans("Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.")
                    }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit">
                <Button v-if="customer_sales_channel?.reconnect_route?.name"
                    @click="() => onClickReconnect()" iconRight="fal fa-external-link"
                    :label="trans('Try to reconnect')" zsize="xxs" type="secondary" full
                />
            </div>
        </div>
    </Message>

    <Message severity="warning" class="mt-8" v-if="customer_sales_channel.type === 'woocommerce'">
        <div class="ml-2 font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
            <div>
                <div class="inline items-center gap-x-2">
                    {{
                        trans("If still facing trouble to connect, check the connection here")
                    }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit">

                <Button
                    :label="trans('Test Connection')"
                    type="secondary"
                    @click="() => onClickTestConnection()"
                />
            </div>
        </div>
        <div class="ml-2">
            <div>
                <small>{{ trans('Please add this IP Address to whitelist:')}}</small>
            </div>
            <div v-for="(ip, i) in ipAddresses" :key="i">
                <blockquote>{{ ip }}</blockquote>
            </div>
        </div>
        <div v-if="errorCaptcha" class="ml-2">
            <small class="text-red-500">{{errorCaptcha}}</small>
        </div>
    </Message>

    <Message severity="error" class="mt-8 ">
        <div class="ml-2 font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
            <div>
                <div class="inline items-center gap-x-2">
                    {{
                        trans("Or delete the channel and try again")
                    }}
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit">

                <ButtonWithLink
                    :label="trans('Delete')"
                    type="delete"
                    :routeTarget="customer_sales_channel?.delete_route"
                />
            </div>
        </div>
    </Message>


</template>
