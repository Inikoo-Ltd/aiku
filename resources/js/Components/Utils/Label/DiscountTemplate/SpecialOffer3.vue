<script setup lang="ts">
import { computed, ref } from "vue"
import Popover from "primevue/popover"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowDown } from "@far"

interface Offer {
    label?: string
    percentage_off?: string | number
    max_percentage_discount?: string | number
    duration_label?: string
}

const props = withDefaults(
    defineProps<{
        offer?: Offer
        use_duration?: boolean
    }>(),
    {
        use_duration: true,
    }
)

const infoPopover = ref()

const showInfo = (event: Event) => {
    infoPopover.value?.show(event)
}

const hideInfo = () => {
    infoPopover.value?.hide()
}

const label = computed(() => props.offer?.label || ctrans("Special Offer"))

const maxDiscountLabel = computed(() => {
    const raw = props.offer?.max_percentage_discount

    if (raw == null) return null

    const val = Number(raw)

    if (isNaN(val) || val <= 0) return null

    return (val * 100).toFixed(2).replace(/\.00$/, "")
})
</script>

<template>
    <div
        class="inline-flex items-center gap-1 rounded cursor-pointer transition-all duration-150"
        tabindex="0"
        aria-haspopup="true"
        @mouseenter="showInfo"
        @mouseleave="hideInfo"
    >
        <div
            class="flex items-center bg-[#E87928] gap-2 rounded px-1 md:py-[5px] py-[3px] xl:py-[3px] text-[8px] xl:text-[10px] 2xl:text-xs font-semibold leading-none text-white transition-all duration-150"
        >

        <FontAwesomeIcon :icon="faArrowDown"  class="text-[8px]"/>
            <span v-if="maxDiscountLabel">
                {{ maxDiscountLabel }}%
            </span>

            <span v-else>
                {{ label }}
            </span>
        </div>

        <Popover ref="infoPopover">
            <div class="max-w-[280px] space-y-3 text-sm">
                <div class="special-offer__content">
                    <div
                        v-if="maxDiscountLabel"
                        class="special-offer__percentage font-semibold"
                    >
                        {{ maxDiscountLabel }}% {{ ctrans("OFF") }}
                    </div>

                    <div
                        v-if="use_duration && offer?.duration_label"
                        class="special-offer__status"
                    >
                        {{ offer.duration_label }}
                    </div>
                </div>
            </div>
        </Popover>
    </div>
</template>