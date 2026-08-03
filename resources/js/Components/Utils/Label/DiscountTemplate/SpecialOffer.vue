<script setup lang="ts">
import { computed, ref } from "vue"
import { Popover } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { ctrans } from "@/Composables/useTrans"

library.add(faInfoCircle)

const props = withDefaults(defineProps<{
    offer?: {
        label?: string
        percentage_off?: string | number
        duration_label?: string
    }
    use_duration?: boolean
}>(), {
    use_duration: true,
})

const _popoverInfoCircle = ref()

const label = computed<string>(() => props.offer?.label || ctrans("Special Offer"))

const maxDiscountLabel = computed(() => {
    const raw = props.offer?.max_percentage_discount
    if (!raw) return null

    const val = Number(raw)
    if (!val) return null

    return (val * 100).toFixed(2).replace(/\.00$/, "")
})
console.log('haloo',props)
</script>

<template>
    <section class="special-offer" aria-label="Special Offer Label">
        <div class="special-offer__title">
            {{ label }}
        </div>

        <div class="special-offer__content">
            <div class="special-offer__percentage">
                <span v-if="maxDiscountLabel">{{ maxDiscountLabel }}% {{ ctrans("OFF") }}</span>
            </div>

            <div v-if="offer?.duration_label" class="special-offer__status">
                {{  offer.duration_label  }}
            </div>
        </div>

        <span
            @click="(e) => _popoverInfoCircle?.toggle(e)"
            @mouseenter="_popoverInfoCircle?.show"
            @mouseleave="_popoverInfoCircle?.hide"
            class="special-offer__info-icon"
        >
            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
        </span>

        <Popover ref="_popoverInfoCircle" class="special-offer__popover">
            <div class="special-offer__popover-content">
                <p>{{ trans("Offer is valid for selected products only.") }}</p>
                <p>{{ trans("This offer can not be combined with other offers.") }}</p>
            </div>
        </Popover>
    </section>
</template>

<style scoped>
.special-offer {
    @apply relative inline-flex items-stretch w-fit overflow-hidden rounded-lg shadow-md text-white mb-2;
    background-color: #E87928;
}

.special-offer__title {
    @apply flex items-center px-3 py-1.5 text-[12px] font-bold uppercase leading-tight max-w-[120px];
}

.special-offer__content {
    @apply flex flex-col justify-center px-3 py-1.5 pr-7 border-l border-white/30;
}

.special-offer__percentage {
    @apply text-sm font-bold leading-tight whitespace-nowrap;
}

.special-offer__status {
    @apply text-[10px] leading-tight opacity-90 whitespace-nowrap;
}

.special-offer__info-icon {
    @apply absolute top-1 right-1.5 text-[10px] opacity-80 hover:opacity-100 cursor-pointer;
}

.special-offer__popover {
    @apply py-1 px-2;
}

.special-offer__popover-content {
    @apply text-xs text-justify leading-relaxed;
    width: 300px;
}
</style>
