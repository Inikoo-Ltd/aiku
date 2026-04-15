<script setup lang="ts">
import { computed, ref } from "vue"
import Popover from "primevue/popover"
import { trans } from 'laravel-vue-i18n';
import { faClock } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
library.add(faClock)



const props = defineProps<{
    offer?: any
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
                <span>FOB</span>
                <span v-if="maxDiscountLabel" class="discount">
                    - {{ maxDiscountLabel }}% <strong>OFF</strong>
                </span>

                <span  @click="_popoverInfoCircle?.toggle"
                    @mouseenter="_popoverInfoCircle?.show" @mouseleave="_popoverInfoCircle?.hide" class="info-icon">
                    <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
                </span>
            </div>

            <Popover ref="_popoverInfoCircle" class="offer-popover">
                <div class="offer-popover-text">
                    <p>{{ trans("First order only. New customers eligible.") }}</p>
                    <p>{{ trans("Cannot be combined with other offers.") }}</p>
                </div>
            </Popover>
        </div>
    </div>
</template>

<style scoped>
.offer-wrapper {
    @apply flex flex-col sm:flex-row items-start sm:items-stretch gap-1 sm:gap-2;
}

.offer-valid-until {
    @apply flex items-center px-2 bg-gray-100 text-gray-700 text-[10px] sm:text-xs rounded-sm w-fit max-w-full;
}

.offer-valid-until span {
    @apply truncate sm:whitespace-nowrap;
}

.offer-max-discount {
    @apply bg-[#2a919e] border border-gray-900 text-gray-100 w-fit flex items-center rounded-sm px-1 py-0.5 text-[10px] sm:px-1.5 sm:py-1 sm:text-xxs md:px-2 md:py-1;
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
