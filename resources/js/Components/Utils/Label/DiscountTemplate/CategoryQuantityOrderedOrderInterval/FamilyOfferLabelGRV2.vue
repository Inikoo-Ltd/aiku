<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { computed, inject, ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
import { ctrans } from '@/Composables/useTrans'

library.add(faInfoCircle)

const props = defineProps<{
    offer: {}
    use_duration?: boolean
}>()

const layout = inject('layout', retinaLayoutStructure)
const _popoverInfoCircle = ref<InstanceType<any>[] | null>(null)

const isActive = computed<boolean>(
    () => Boolean(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr)
)

const percentageOff = computed<number | null>(() => {
    if (!props.offer?.max_percentage_discount) {
        return null
    }

    return Number(props.offer.max_percentage_discount) * 100
})

const amnestyUntil = computed<string>(
    () => useFormatTime(layout?.user?.gr_data?.amnesty_until, { formatTime: 'dd/MM/yyyy' })
)
</script>

<template>
    <section
        class="gr-label"
        :class="isActive ? 'gr-label--active' : 'gr-label--inactive'"
        aria-label="Gold Reward Offer Label"
    >
        <div class="gr-title">
            <span>{{ ctrans('Gold') }}</span>
            <span>{{ ctrans('Reward') }}</span>

            <img
                v-if="isActive"
                src="/assets/promo/gr-aw.png"
                alt="Gold Reward Logo"
                class="gr-seal"
            />
        </div>

        <div class="gr-content">
            <div class="gr-percentage">
                <span v-if="percentageOff !== null">{{ percentageOff }}% {{ ctrans('OFF') }}</span>
            </div>

            <div class="gr-status">
                <template v-if="layout?.user?.gr_data?.amnesty">
                    {{ ctrans('Until :amnestyUntil', { amnestyUntil }) }}
                </template>

                <template v-else-if="layout?.user?.gr_data?.customer_is_gr">
                    {{ ctrans('Gold Reward Active') }}
                </template>

                <template v-else>
                    {{ ctrans('Inactive') }}
                </template>
            </div>
        </div>

        <span
            @click="(e) => _popoverInfoCircle?.toggle(e)"
            @mouseenter="_popoverInfoCircle?.show"
            @mouseleave="_popoverInfoCircle?.hide"
            class="gr-info-icon"
        >
            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
        </span>

        <Popover ref="_popoverInfoCircle" class="gr-popover">
            <div class="gr-popover-content">
                <p class="gr-popover-title">
                    {{ trans("Gold Reward Membership") }}
                </p>

                <p class="gr-popover-text">
                    {{ trans("Place an order within 30 days of your last invoice and Gold Reward status applies automatically. This unlocks the best pricing across eligible ranges, without needing to bulk up every order") }}.
                </p>
            </div>
        </Popover>
    </section>
</template>

<style scoped>
.gr-label {
    @apply relative inline-flex items-stretch w-fit overflow-hidden rounded-lg shadow-md text-white mb-2;
}

.gr-title {
    @apply relative flex flex-col justify-center px-3 text-[12px] font-bold uppercase leading-tight whitespace-nowrap;
    background-color: var(--theme-color-4, #f97316);
}

.gr-seal {
    @apply absolute right-0 top-1/2 z-20 h-10 -translate-y-1/2 translate-x-1/2 drop-shadow;
}

.gr-content {
    @apply flex flex-col justify-center px-3 py-1 bg-gray-900;
}

.gr-percentage {
    @apply text-sm font-bold leading-tight whitespace-nowrap;
}

.gr-status {
    @apply text-[10px] leading-tight opacity-80 whitespace-nowrap;
}

.gr-info-icon {
    @apply absolute top-1 right-1.5 text-[10px] opacity-70 hover:opacity-100 cursor-pointer;
}

.gr-label--active {
    @apply overflow-visible;
}

.gr-label--active .gr-title {
    @apply py-2 pr-6 rounded-l-lg bg-orange-500;
}

.gr-label--active .gr-content {
    @apply py-2 pl-7 rounded-r-lg min-w-[7rem];
}

.gr-label--inactive {
    @apply overflow-visible shadow-none;
}

.gr-label--inactive .gr-title {
    @apply z-10 min-w-[6rem] bg-gray-400 text-white rounded-lg pl-3 pr-5 py-2 shadow-md;
}

.gr-label--inactive .gr-content {
    @apply -ml-4 pl-7 my-0.5 mr-0.5 min-w-[7rem] rounded-md bg-gray-300 text-white shadow-sm;
}

.gr-popover {
    @apply py-1 px-2;
}

.gr-popover-content {
    @apply text-xs;
    width: 300px;
}

.gr-popover-title {
    @apply font-bold mb-4;
}

.gr-popover-text {
    @apply inline-block mb-4 text-justify;
}
</style>
