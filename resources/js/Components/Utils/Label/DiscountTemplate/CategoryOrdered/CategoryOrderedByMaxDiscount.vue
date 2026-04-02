<script setup lang="ts">
import { computed, ref } from "vue"
import Popover from "primevue/popover"
import { trans } from 'laravel-vue-i18n';
import { faClock } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faClock)
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
interface Offer {
    max_percentage_discount?: number | string | null
}

const props = defineProps<{
    offer?: Offer
}>()

const _popoverInfoCircle = ref()

const maxDiscountLabel = computed(() => {
    const raw = props.offer?.max_percentage_discount
    if (!raw) return null

    const val = Number(raw)
    if (!val) return null

    return (val * 100).toFixed(2).replace(/\.00$/, "")
})
console.log('maxDiscountLabel', props.offer)
</script>

<template>
     <div class="offer-wrapper gap-2">
    <div class="offer-max-discount bg-white">
        <div class="offer-label">
            <span v-if="maxDiscountLabel" class="discount">
                - {{ maxDiscountLabel }}% <strong>OFF</strong>
            </span>

            <span class="label-text">
                {{ props.offer?.label || trans("Special Offers") }}
            </span>

            <span v-if="!layout?.user?.gr_data?.customer_is_gr" @click="_popoverInfoCircle?.toggle"
                @mouseenter="_popoverInfoCircle?.show" @mouseleave="_popoverInfoCircle?.hide" class="info-icon">
                <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
            </span>
        </div>

        <Popover ref="_popoverInfoCircle" class="offer-popover">
            <div class="offer-popover-text">
                <p>{{ trans("Offer is valid for selected products only.") }}</p>
                <p>{{ trans("This offer can not be combined with other offers.") }}</p>
                <!-- <p v-if="offer.duration != 'permanent'">{{ trans("Offer is valid until 27.07.2026 (midnight).") }}</p> -->
            </div>

        </Popover>
    </div>
    <div class="offer-valid-until">
            <span class="clock"><FontAwesomeIcon
							
							icon="fal fa-clock"
							class="mr-1 text-blue-500" /></span>
            <span>
                {{ trans("Valid until") }}
                <strong>{{ props.offer.end_date }}</strong>
            </span>
        </div>
         </div>
</template>

<style scoped>
.offer-wrapper {
    @apply inline-flex flex-row;
}
.offer-valid-until {
    @apply flex items-center px-2 
           bg-gray-100 text-gray-700 
           text-[10px] sm:text-xs 
           rounded-sm;
}
.offer-max-discount {
    @apply bg-[#A80000] border border-red-900 text-gray-100 w-fit flex items-center rounded-sm px-1 py-0.5 text-[10px] sm:px-1.5 sm:py-1 sm:text-xxs md:px-2 md:py-1;
}


.offer-popover {
    @apply py-2 px-3;
    width: 350px;
}

.offer-popover-text {
    @apply text-xs text-justify leading-relaxed;
}

.offer-label {
    @apply flex items-center gap-1;
}

.discount {
    @apply flex items-center gap-0.5;
}

.label-text {
    @apply leading-none;
}

.info-icon {
    @apply flex items-center ml-1 opacity-80 hover:opacity-100 cursor-pointer;
}
</style>
