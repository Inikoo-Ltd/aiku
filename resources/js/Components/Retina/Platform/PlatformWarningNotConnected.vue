<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { CustomerSalesChannel } from '@/types/customer-sales-channel'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { Message } from 'primevue'

const props = defineProps<{
    customer_sales_channel: CustomerSalesChannel
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
            window.open(response.data, '_blank');
        }
    } catch (error: any) {
        notify({
            title: 'Something went wrong',
            text: error.message || 'Please try again later.',
            type: 'error'
        })
    }
}
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
                    :label="trans('Connect')" zsize="xxs" type="secondary" full
                />
            </div>
        </div>
    </Message>
</template>