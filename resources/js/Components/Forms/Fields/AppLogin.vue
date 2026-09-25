<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from "vue"
import QrcodeVue from 'qrcode.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSync } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useTimeCountdown } from '@/Composables/useFormatTime'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { trans } from "laravel-vue-i18n"
import { routeType } from '@/types/route'
library.add(faSync)

const props = defineProps<{
    route: routeType
}>()

const isQrCode = ref(false)
const isLoading = ref(false)
const isRegenerating = ref(false)
const timeCountdown = ref('')
const intervalCountdown = ref()
const qrValue = ref('')

// Method: Fetch value for QR
const fetchQrCode = async () => {
    try {
        const response = await axios.get(route(props.route.name, props.route.parameters),)
        // console.log(response.data)
        qrValue.value = response.data.code.toString()
        setCountdown(120)
    } catch (error: any) {
        notify({
            title: 'Error',
            text: error,
            type: 'error'
        })
    }
}

// Method: Generate first QR
const onGenerateQr = async () => {
    isLoading.value = true

    await fetchQrCode()
    isQrCode.value = true
    isLoading.value = false

}

// Method: Regenerate the QR
const onRegenerateQr = async () => {
    isRegenerating.value = true

    await fetchQrCode()
    isRegenerating.value = false
}

// Set countdown for QR
const setCountdown = (duration: number) => {
    let date = (new Date()).setSeconds((new Date()).getSeconds() + duration)
    clearInterval(intervalCountdown.value)
    setTimeout(() => {
        // To handle issue (stepped 2 seconds at early countdown)
        timeCountdown.value = useTimeCountdown(date, { human: true })
    }, 50)

    intervalCountdown.value = setInterval(() => {
        timeCountdown.value = useTimeCountdown(date, { human: true })
        if(!timeCountdown.value) {
            clearInterval(intervalCountdown.value)
            isQrCode.value = false
        }
    }, 1000)
}

</script>

<template>
    <div class="relative flex justify-center">
        <div v-if="!isQrCode" class="mt-10">
            <Button label="Show QR code" :style="isLoading ? 'disabled' : 'rainbow'" :key="isLoading.toString()" @click="onGenerateQr" :loading="isLoading" size="xl" />
        </div>
        <div v-else class="">
            <template v-if="!isRegenerating">
                <div class="relative w-fit mx-auto flex items-center justify-center gap-x-3">
                    <QrcodeVue :value="qrValue" :size="200" level="L" render-as="svg" foreground="#334155" />
                    <div @click="onRegenerateQr()" v-tooltip="'Regenerate QR Code'" class="absolute -right-8 cursor-pointer p-0.5 text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon='fal fa-sync' :class="isRegenerating ? 'animate-spin' : ''" class='h-5' fixed-width aria-hidden='true' />
                    </div>
                </div>
                <p v-if="timeCountdown" class="mt-4 text-sm text-gray-500 tabular-nums">{{trans('This QR Code valid for')}} {{ timeCountdown }}.</p>
            </template>
            <div v-else class="h-[200px] aspect-square skeleton" />
        </div>
    </div>
</template>
