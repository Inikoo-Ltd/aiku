

<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 02 Apr 2024 20:10:35 Central Indonesia Time, Sanur , Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from 'vue'
import { useCopyText } from '@/Composables/useCopyText'

import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink} from '@far'
import { faSync, faCalendarAlt, faEnvelope, faPhone } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone)

const props = defineProps<{
    data: {
        updateRoute: routeType
        webhook: {
            webhook_access_key: string | null
            domain: string
            route: routeType
        }
    },
    tab: string
}>()

// Section: Webhook
const isWebhookLoading = ref(false)
const webhookValue = ref(props.data.webhook?.webhook_access_key || '')
const onFetchWebhook = async () => {
    isWebhookLoading.value = true
    try {
        const response: { data: { webhook_access_key: string } } = await axios.get(route(props.data.webhook?.route.name, props.data.webhook.route.parameters))
        // console.log('response', response)
        webhookValue.value = response?.data?.webhook_access_key || ''
    } catch (error) {
        notify({
            title: trans("Something wrong"),
            text: trans("Failed to retrieve webhook. Please try again."),
            type: "error"
        })
    }

    isWebhookLoading.value = false
}

</script>

<template>
    <div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-2 gap-x-8 gap-y-3">
        <!-- Section: Webhook -->
        <div class="flex justify-center flex-col">
            <div class="whitespace-nowrap text-gray-500">The webhook: </div>
            <div v-if="webhookValue" class="bg-white border border-gray-300 flex items-center justify-between mx-auto rounded-md md:w-full md:max-w-2xl ">
                <a :href="data.webhook.domain + webhookValue + '?type=human'" target="_blank" class="truncate pl-4 md:pl-5 inline-block py-2 text-xxs md:text-base text-gray-400 w-full" v-tooltip="'Click to visit link'">
                    {{ data.webhook.domain + webhookValue + '?type=human' }}
                </a>

                <div @click="() => onFetchWebhook()" class="cursor-pointer h-full aspect-square flex justify-center items-center">
                    <FontAwesomeIcon icon='fal fa-sync' class='text-gray-400 hover:text-gray-600' :class="isWebhookLoading ? 'animate-spin' : ''" fixed-width aria-hidden='true' />
                </div>

                <Button :style="'tertiary'" icon='far fa-link' class="" size="l" @click="useCopyText(data.webhook.domain + webhookValue + '?type=human')" tooltip="Copy url to clipboard" />
            </div>

            <Button v-else label="Click to retrieve webhook" :loading="isWebhookLoading" @click="() => onFetchWebhook()" />
        </div>
    </div>
</template>

