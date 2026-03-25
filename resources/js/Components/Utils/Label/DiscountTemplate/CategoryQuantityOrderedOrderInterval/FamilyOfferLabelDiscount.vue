<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faInfoCircle)

const props = defineProps<{
    offer: {}
}>()

const _popoverInfoCircle = ref<InstanceType<any>[] | null>(null)
</script>

<template>
<section class="volume-discount-label" aria-label="Volume Discount Offer Label">

    <div class="discount-percentage">
        <span v-if="offer?.max_percentage_discount" class="percentage-text">
            {{ Number(offer?.max_percentage_discount) * 100 }}% OFF
        </span>

        <span v-else>
            {{ offer.allowances?.[0]?.label }}
        </span>
    </div>

    <div class="discount-content">
        <div>
            <div class="discount-title">
                {{ trans("Volume Discount") }}
            </div>

            <div class="discount-triggers">
                {{ offer.triggers_labels?.join('/') }}
            </div>
        </div>

        <span
            @click="(e) => (_popoverInfoCircle?.toggle(e))"
            @mouseenter="_popoverInfoCircle?.show"
            @mouseleave="_popoverInfoCircle?.hide"
            class="info-icon"
        >
            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
        </span>
    </div>

    <Popover ref="_popoverInfoCircle" class="discount-popover">
        <div class="popover-content">
            <p class="popover-title">{{ trans("VOLUME DISCOUNT") }}</p>

            <p class="popover-paragraph">
                {{ trans("You don't need Gold Reward status to access the lower price") }}.
            </p>

            <p class="popover-paragraph">
                {{ trans("Order the listed volume and the member price applies automatically at checkout") }}.
                {{ trans("The volume can be made up from the whole product family, not just the same item") }}.
            </p>
        </div>
    </Popover>

</section>
</template>


<style scoped>

.volume-discount-label {
    @apply relative flex justify-between w-full md:w-fit overflow-hidden rounded-lg px-px py-px shadow-md mb-2;
    background-color: var(--theme-color-4);
}

.discount-percentage {
    @apply w-fit md:w-full relative flex items-center text-white font-bold px-2 md:px-7 text-lg md:text-4xl min-w-24 text-center;
}

.percentage-text {
    @apply mx-auto;
}

.discount-content {
    @apply bg-white w-full rounded-md px-2 py-1 flex items-center gap-x-4;
    border-color: var(--theme-color-4);
    @apply border;
}

.discount-title {
    @apply whitespace-nowrap capitalize;
}

.discount-triggers {
    @apply text-xs md:whitespace-nowrap opacity-70;
}

.info-icon {
    @apply opacity-60 hover:opacity-100 cursor-pointer;
}

.discount-popover {
    @apply py-1 px-2;
}

.popover-content {
    width: 300px;
    @apply text-xs;
}

.popover-title {
    @apply font-bold mb-4;
}

.popover-paragraph {
    @apply mb-4 text-justify inline-block;
}

</style>