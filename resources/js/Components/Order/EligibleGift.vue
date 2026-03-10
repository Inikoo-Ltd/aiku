<script setup lang="ts">
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { computed, ref } from 'vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from '../Image.vue'
import { Image as ImageTS } from '@/types/Image'
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
    // selectedGift: Gift | null
    giftOptions: Gift[]
}>()

// const selectedGift = ref(props.selectedGift ?? null)
const compSelectedGift = computed(() => {
    return props.giftOptions.find(x => x.selected)
})
const _popover = ref<InstanceType<typeof Popover> | null>(null)

// Section: Charge Priority Dispatch
const isLoadingChanged = ref<number|null>(null)
const onChangeGift = async (val: Gift) => {

    try {
        isLoadingChanged.value = val.id

        const response = await axios.patch(
            route(
                props.routeUpdate.name,
                props.routeUpdate.parameters
            ),
            { gift_id: val.id }
        )

        const targetGift = props.giftOptions.find(x => x.id === val.id)

        if (targetGift) {

            props.giftOptions.forEach(gift => {
                gift.selected = targetGift.id === gift.id ? !val.selected : false
            })

        }

        // console.log('Response axios:', response.data)

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
</script>

<template>
    <div class="flex gap-x-2">
        <div>{{ trans("You are eligible to receive a gift") }}:</div>
        <div v-if="!compSelectedGift" @click="_popover?.toggle" class="cursor-pointer text-blue-600 underline">
            {{ trans("Select gift") }}
        </div>
        <div v-else class="relative text-right">
            <div @click="_popover?.toggle" class="relative underline cursor-pointer inline-block">
                <span class="font-bold">
                    {{ compSelectedGift.code }}
                </span>
                {{ compSelectedGift.name }}
                <div v-if="isLoadingChanged" class="absolute w-full h-[1.4em] top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2">
                    <div class="skeleton w-full h-full">

                    </div>
                </div>
            </div>
            <span @click="_popover?.toggle" class="ml-2 cursor-pointer text-blue-500 underline">{{ trans("change") }}</span>
            <span v-if="isLoadingChanged" class="absolute top-1/2 -translate-y-1/2 w-4 h-4 xtext-blue-600">
                <LoadingIcon />
            </span>
        </div>

        <Popover ref="_popover">
            <div class="flex flex-col gap-2 w-96">
                <div
                    v-for="gift in giftOptions"
                    :key="gift.id"
                    class="flex items-center gap-2 cursor-pointer"
                    @click.prevent="() => ('_popover?.hide()', onChangeGift(gift))"
                >
                    <!-- <input
                        type="radio"
                        :id="gift.id.toString()"
                        :value="gift"
                        :modelValue="compSelectedGift?.id"
                        class=" text-[var(--theme-color-0)] cursor-pointer"
                        name="gift_input"
                    /> -->
                    <span>
                        <FontAwesomeIcon v-if="gift.id === compSelectedGift?.id" icon="fas fa-check-circle" class="" fixed-width aria-hidden="true" />
                        <LoadingIcon v-else-if="isLoadingChanged === gift.id" class="" />
                        <FontAwesomeIcon v-else icon="fal fa-circle" class="" fixed-width aria-hidden="true" />
                    </span>
                    <div class="w-14 aspect-square h-14 border border-gray-300">
                        <Image :src="gift.web_images_main?.thumbnail" :alt="gift.name" class="object-contain w-full h-full" />
                    </div>
                    <label :for="gift.id.toString()" class="cursor-pointer">
                        <span class="font-bold text-sm">{{ gift.code }}</span>
                        <br />
                        <span class="text-xs leading-4 inline-block opacity-80">{{ gift.name }}</span>
                    </label>
                </div>
            </div>
        </Popover>
    </div>

</template>