<script setup lang="ts">
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { ref } from 'vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    routeUpdate: routeType
    selectedGift: {
        label: string
        value: string
    } | null
    giftOptions: {
        label: string
        value: string
    }[]
}>()

const selectedGift = ref(props.selectedGift ?? {})

const _popover = ref<InstanceType<typeof Popover> | null>(null)

// Section: Charge Priority Dispatch
const isLoadingChanged = ref(false)
const onChangeGift = async (val: {}) => {
    try {
        isLoadingChanged.value = true
        const response = await axios.patch(
            route(
                props.routeUpdate.name,
                props.routeUpdate.parameters
            ),
            { gift: val.value }
        )
        if (response.status !== 200) {
            
        }
        selectedGift.value = val
        console.log('Response axios:', response.data)
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    } finally {
        isLoadingChanged.value = false
    }
}
</script>

<template>
    <div>
        <div class="flex gap-x-2">
            <div>You are eligible to receive a gift:</div>
            <div v-if="!selectedGift" @click="_popover?.toggle" class="cursor-pointer text-blue-600 underline">
                Select gift
            </div>
            <div v-else class="relative">
                <span class="font-bold">{{ selectedGift.label }}</span>
                <span @click="_popover?.toggle" class="ml-2 cursor-pointer text-blue-500 underline">change</span>
                <span v-if="isLoadingChanged" class="absolute top-1/2 -translate-y-1/2 w-4 h-4 xtext-blue-600">
                    <LoadingIcon />
                </span>
            </div>
        </div>

        

        <Popover ref="_popover">
            <div class="flex flex-col gap-2">
                <div
                    v-for="gift in giftOptions"
                    :key="gift.value"
                    class="flex items-center gap-2 cursor-pointer"
                    @click.prevent="() => (_popover?.hide(), onChangeGift(gift))"
                >
                    <input
                        type="radio"
                        :id="gift.value"
                        :value="gift"
                        v-model="selectedGift"
                        class=" text-[var(--theme-color-0)] cursor-pointer"
                        name="gift_input"
                    />
                    <label :for="gift.value" class="cursor-pointer">{{ gift.label }}</label>
                </div>
            </div>
        </Popover>
    </div>
</template>