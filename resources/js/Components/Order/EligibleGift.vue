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
library.add(faCircle)

interface Gift {
    id: number
    name: string
    selected: boolean
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
const isLoadingChanged = ref(false)
const onChangeGift = async (val: Gift) => {

    try {
        isLoadingChanged.value = true

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
        isLoadingChanged.value = false
    }
}
</script>

<template>
    <div class="flex gap-x-2">
        <div>{{ trans("You are eligible to receive a gift") }}:</div>
        <div v-if="!compSelectedGift" @click="_popover?.toggle" class="cursor-pointer text-blue-600 underline">
            {{ trans("Select gift") }}
        </div>
        <div v-else class="relative">
            <span class="underline">
                <span class="font-bold">
                    {{ compSelectedGift.code }}
                </span>
                {{ compSelectedGift.name }}
            </span>
            <span @click="_popover?.toggle" class="ml-2 cursor-pointer text-blue-500 underline">{{ trans("change") }}</span>
            <span v-if="isLoadingChanged" class="absolute top-1/2 -translate-y-1/2 w-4 h-4 xtext-blue-600">
                <LoadingIcon />
            </span>
        </div>

        <Popover ref="_popover">
            <div class="flex flex-col gap-2">
                <div
                    v-for="gift in giftOptions"
                    :key="gift.id"
                    class="flex items-center gap-2 cursor-pointer"
                    @click.prevent="() => (_popover?.hide(), onChangeGift(gift))"
                >
                    <!-- <input
                        type="radio"
                        :id="gift.id.toString()"
                        :value="gift"
                        :modelValue="compSelectedGift?.id"
                        class=" text-[var(--theme-color-0)] cursor-pointer"
                        name="gift_input"
                    /> -->
                    <FontAwesomeIcon v-if="gift.id === compSelectedGift?.id" icon="fas fa-check-circle" class="" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else icon="fal fa-circle" class="" fixed-width aria-hidden="true" />
                    <label :for="gift.id.toString()" class="cursor-pointer">
                        <span class="font-bold">{{ gift.code }}</span>
                        {{ gift.name }}
                    </label>
                </div>
            </div>
        </Popover>
    </div>

</template>