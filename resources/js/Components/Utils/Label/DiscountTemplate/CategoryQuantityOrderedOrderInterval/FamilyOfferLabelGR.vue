<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { inject, ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
import { ctrans } from '@/Composables/useTrans'

library.add(faInfoCircle)

const props = defineProps<{
    offer: {}
}>()

const layout = inject('layout', retinaLayoutStructure)
const _popoverInfoCircle = ref<InstanceType<any>[] | null>(null)
</script>

<template>
    <section
        class="gr-wrapper"
        :class="(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr) 
            ? 'background-primary' 
            : 'gr-inactive'"
    >
        <!-- Content -->
        <div class="gr-content">
            
            <!-- Amnesty -->
            <template v-if="layout?.user?.gr_data?.amnesty">
                <span>
                    <span v-if="offer?.max_percentage_discount">
                        {{ Number(offer?.max_percentage_discount) * 100 + ' ' }}% <strong>OFF</strong>
                    </span>
                    {{ ' ' + ctrans('Gold Reward Amnesty') }}

                    <FontAwesomeIcon
                        icon="fas fa-candle-holder"
                        class="gr-icon-small"
                        fixed-width
                        aria-hidden="true"
                    />

                    <span class="gr-date">
                        ({{ ctrans('Until :amnestyUntil', { 
                            amnestyUntil: useFormatTime(layout?.user?.gr_data?.amnesty_until, { formatTime: 'MMM do' }) 
                        }) }})
                    </span>
                </span>
            </template>

            <!-- Active -->
            <template v-else-if="layout?.user?.gr_data?.customer_is_gr">
                <span>
                    <span v-if="offer?.max_percentage_discount">
                        {{ Number(offer?.max_percentage_discount) * 100 + ' ' }}% <strong>OFF</strong>
                    </span>
                    {{ ' ' + ctrans('Gold Reward Active') }}
                </span>
            </template>

            <!-- Inactive -->
            <template v-else>
                {{ ctrans('Gold Reward Inactive') }}
            </template>
        </div>

        <!-- Info Icon -->
        <span
            v-if="!(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr)"
            @click="_popoverInfoCircle?.toggle"
            @mouseenter="_popoverInfoCircle?.show"
            @mouseleave="_popoverInfoCircle?.hide"
            class="gr-info-icon"
        >
            <FontAwesomeIcon
                icon="fal fa-info-circle"
                class="align-middle"
                fixed-width
                aria-hidden="true"
            />
        </span>

        <!-- Popover -->
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

    <div
        class="gr-logo-wrapper"
        v-if="(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr)"
    >
        <img
            :src="`/assets/promo/gr-${layout.retina.organisation}.png`"
            alt="Gold Reward Logo"
            class="gr-logo"
        />
    </div>
</template>

<style scoped>
.gr-wrapper {
    @apply relative w-full md:w-fit flex justify-between items-center rounded-lg px-5 py-1 shadow-md text-white mb-2;
}

.gr-inactive {
    @apply bg-gray-400/70;
}

.gr-content {
    @apply w-full relative flex items-center text-2xl;
}

.gr-icon-small {
    @apply text-base;
}

.gr-date {
    @apply text-base;
}

.gr-info-icon {
    @apply align-middle ml-2 opacity-80 hover:opacity-100 cursor-pointer;
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

.gr-logo-wrapper {
    @apply flex items-center gap-x-2;
}

.gr-logo {
    @apply h-[4em];
}
</style>