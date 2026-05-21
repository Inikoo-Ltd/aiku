<script setup lang="ts">
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { computed, inject, ref } from 'vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from '../../Common/Components/Image.vue'
import { Image as ImageTS } from '@/types/Image'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
library.add(faCircle)

interface Gift {
    id: number
    name: string
    code: string
    selected: boolean
    web_images_main: {
        main: ImageTS
        thumbnail: ImageTS
    }
}

const props = defineProps<{
    routeUpdate: routeType
    giftOptions: Gift[]
    meter: number[]
    isOptedOut?: boolean
    routeOptOut?: routeType
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

const compSelectedGift = computed(() => {
    return props.giftOptions.find(x => x.selected)
})
const _popover = ref<InstanceType<typeof Popover> | null>(null)

const isLoadingChanged = ref<number|null>(null)
const onChangeGift = async (val: Gift) => {

    try {
        isLoadingChanged.value = val.id

        const response = await axios.patch(
            route(
                props.routeUpdate.name,
                props.routeUpdate.parameters
            ),
            { gift_id: val.selected ? 0 : val.id }
        )

        const targetGift = props.giftOptions.find(x => x.id === val.id)

        if (targetGift) {

            props.giftOptions.forEach(gift => {
                gift.selected = targetGift.id === gift.id ? !val.selected : false
            })

        }

    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    } finally {
        isLoadingChanged.value = null
    }
}

// Section: Opt-out from eligible gift
const isLoadingOptOut = ref(false)
const localOptedOut = ref(props.isOptedOut ?? false)
const onToggleOptOut = async (optOut: boolean) => {
    if (!props.routeOptOut) return

    try {
        isLoadingOptOut.value = true

        await axios.patch(
            route(props.routeOptOut.name, props.routeOptOut.parameters),
            { is_gift_opted_out: optOut }
        )

        localOptedOut.value = optOut

    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    } finally {
        isLoadingOptOut.value = false
    }
}

// Method: convert "15.26" to 15.26
const convertToFloat2 = (val: any) => {
    const num = parseFloat(val)
    if (isNaN(num)) return 0.00
    return parseFloat(num.toFixed(2))
}
</script>

<template>
    <div class="flex gap-x-2">

        <!-- Section: Opted out -->
        <template v-if="localOptedOut">
            <div class="flex items-center gap-x-2 text-gray-500 text-sm">
                <FontAwesomeIcon icon="fal fa-gift" class="opacity-40" fixed-width aria-hidden="true" />
                <span>{{ ctrans("You've opted out of the free gift") }}</span>
                <button
                    v-if="routeOptOut"
                    @click="onToggleOptOut(false)"
                    class="text-blue-500 underline text-xs"
                    :disabled="isLoadingOptOut"
                >
                    <LoadingIcon v-if="isLoadingOptOut" class="inline-block w-3 h-3 mr-1" />
                    {{ ctrans("Opt back in") }}
                </button>
            </div>
        </template>

        <!-- Section: Normal gift flow -->
        <template v-else>
            <div>{{ ctrans("You are eligible to receive a gift") }}:</div>

            <!-- Section: meter -->
            <div v-if="!(convertToFloat2(props.meter?.[0]) >= convertToFloat2(props.meter?.[1]))"
                v-tooltip="trans(`:xcurrent of :xtarget products amount. Add :amountLeft to get free gift`, {
                    xcurrent: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(props.meter?.[0])),
                    xtarget: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(props.meter?.[1])),
                    amountLeft: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(props.meter?.[1]) - convertToFloat2(props.meter?.[0]))
                })" class="w-64 flex items-center">
                <div class="w-full rounded-full h-2 bg-gray-200 relative overflow-hidden">
                    <div class="absolute  left-0   top-0 h-full w-3/4 transition-all duration-1000 ease-in-out"
                        :class="convertToFloat2(props.meter?.[0]) < convertToFloat2(props.meter?.[1]) ? 'shimmer bg-green-400' : 'bg-green-500'"
                        :style="{
                            width: convertToFloat2(props.meter?.[1]) ? convertToFloat2(props.meter?.[0])/convertToFloat2(props.meter?.[1]) * 100 + '%' : '100%'
                        }"
                    />
                </div>
            </div>

            <div v-else-if="!compSelectedGift" @click="_popover?.toggle" class="cursor-pointer text-blue-600 underline">
                {{ ctrans("Select gift") }}
            </div>
            <div v-else class="relative text-right border-b border-gray-600">
                <div @click="_popover?.toggle" class="relative cursor-pointer inline-block">
                    <span class="font-bold">
                        {{ compSelectedGift.code }}
                    </span>
                    {{ compSelectedGift.name }}
                    <div v-if="isLoadingChanged" class="absolute w-full h-full top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2">
                        <div class="skeleton w-full h-full">

                        </div>
                    </div>
                </div>
                <span @click="_popover?.toggle" class="ml-2 cursor-pointer text-blue-500">{{ trans("change") }}</span>
                <span v-if="isLoadingChanged" class="absolute top-1/2 -translate-y-1/2 w-4 h-4 xtext-blue-600">
                    <LoadingIcon />
                </span>
            </div>

            <!-- Opt-out link (shown when eligible) -->
            <button
                v-if="routeOptOut && convertToFloat2(props.meter?.[0]) >= convertToFloat2(props.meter?.[1])"
                @click="onToggleOptOut(true)"
                class="text-red-400 hover:text-red-600 text-xs border-b border-gray-600 ml-1"
                :disabled="isLoadingOptOut"
            >
                <LoadingIcon v-if="isLoadingOptOut" class="inline-block w-3 h-3 mr-1" />
                {{ ctrans("Opt-out from free gift") }}
            </button>

            <Popover ref="_popover">
                <div class="flex flex-col gap-2 w-96">
                    <div
                        v-for="gift in giftOptions"
                        :key="gift.id"
                        class="flex items-center gap-2 cursor-pointer"
                        @click.prevent="() => ('_popover?.hide()', onChangeGift(gift))"
                    >
                        <span>
                            <FontAwesomeIcon v-if="gift.id === compSelectedGift?.id" icon="fas fa-check-circle" class="" fixed-width aria-hidden="true" />
                            <LoadingIcon v-else-if="isLoadingChanged === gift.id" class="" />
                            <FontAwesomeIcon v-else icon="fal fa-circle" class="" fixed-width aria-hidden="true" />
                        </span>
                        
                        <div class="w-14 aspect-square h-14 border border-gray-300 flex items-center justify-center bg-gray-100">
                            <Image v-if="gift.web_images_main?.thumbnail" :src="gift.web_images_main?.thumbnail" :alt="gift.name" class="object-contain w-full h-full" />
                            <FontAwesomeIcon v-else icon="fal fa-image" class="text-xl opacity-50" fixed-width aria-hidden="true" />
                        </div>

                        <label :for="gift.id.toString()" class="cursor-pointer">
                            <span class="font-bold text-sm">{{ gift.code }}</span>
                            <br />
                            <span class="text-xs leading-4 inline-block opacity-80">{{ gift.name }}</span>
                        </label>
                    </div>
                </div>
            </Popover>
        </template>

    </div>

</template>