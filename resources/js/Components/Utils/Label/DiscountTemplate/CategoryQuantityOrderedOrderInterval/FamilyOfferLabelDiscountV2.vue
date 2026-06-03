<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { computed, ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faInfoCircle)

const props = defineProps<{
    offer: {}
    offer_allowances?: {}
}>()

const _popoverInfoCircle = ref<InstanceType<any>[] | null>(null)

const percentageOff = computed<number | null>(() => {
    const value = props.offer?.max_percentage_discount ?? props.offer_allowances?.[0]?.data?.percentage_off

    if (!value) {
        return null
    }

    return Number(value) * 100
})

const triggersLabel = computed<string>(() => props.offer?.triggers_labels?.join('/') ?? '')
</script>

<template>
    <section class="vd-label" aria-label="Volume Discount Offer Label">
        <div class="vd-title">
            <span>{{ trans('Volume') }}</span>
            <span>{{ trans('Discount') }}</span>
        </div>

        <div class="vd-content">
            <div class="vd-percentage">
                <span v-if="percentageOff !== null">{{ percentageOff }}% {{ trans('OFF') }}</span>
                <span v-else>{{ offer?.allowances?.[0]?.label }}</span>
            </div>

            <div v-if="triggersLabel" class="vd-triggers">
                {{ triggersLabel }}
            </div>
        </div>

        <span
            @click="(e) => _popoverInfoCircle?.toggle(e)"
            @mouseenter="_popoverInfoCircle?.show"
            @mouseleave="_popoverInfoCircle?.hide"
            class="vd-info-icon"
        >
            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
        </span>

        <Popover ref="_popoverInfoCircle" class="vd-popover">
            <div class="vd-popover-content">
                <p class="vd-popover-title">{{ trans("VOLUME DISCOUNT") }}</p>

                <p class="vd-popover-text">
                    {{ trans("You don't need Gold Reward status to access the lower price") }}.
                </p>

                <p class="vd-popover-text">
                    {{ trans("Order the listed volume and the member price applies automatically at checkout") }}.
                    {{ trans("The volume can be made up from the whole product family, not just the same item") }}.
                </p>
            </div>
        </Popover>
    </section>
</template>

<style scoped>
.vd-label {
    @apply relative inline-flex items-stretch w-fit overflow-visible rounded-lg text-white mb-2;
}

.vd-title {
    @apply z-10 flex flex-col justify-center min-w-[6rem] px-3 text-[12px] font-bold uppercase leading-tight rounded-lg shadow-md;
    background-color: var(--theme-color-4);
}

.vd-content {
    @apply flex flex-col justify-center -ml-4 pl-7 pr-3 my-0.5 mr-0.5 rounded-md bg-gray-900 shadow-sm;
}

.vd-percentage {
    @apply text-sm font-bold leading-tight whitespace-nowrap;
}

.vd-triggers {
    @apply max-w-[12rem] truncate text-[10px] leading-tight opacity-80;
}

.vd-info-icon {
    @apply absolute top-1.5 right-1.5 text-[11px] opacity-70 hover:opacity-100 cursor-pointer;
}

.vd-popover {
    @apply py-1 px-2;
}

.vd-popover-content {
    @apply text-xs;
    width: 300px;
}

.vd-popover-title {
    @apply font-bold mb-4;
}

.vd-popover-text {
    @apply inline-block mb-4 text-justify;
}
</style>
