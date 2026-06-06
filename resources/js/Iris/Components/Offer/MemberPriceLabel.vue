<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import Popover from "primevue/popover"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faInfoCircle)

const layout = inject("layout", retinaLayoutStructure)

const infoPopover = ref()

const toggleInfo = (event: Event) => {
    infoPopover.value?.toggle(event)
}

defineProps<{
    active?: boolean
    offer?: {
        allowances?: {
            percentage_off?: number
        }[]
    }
}>()
</script>

<template>
    <div
        class="inline-flex items-center overflow-hidden rounded"
        :class="{
            'gap-2': active,
            'gap-0': !active,
            'opacity-60': !active,
        }"
    >
        <img
            :src="
                active
                    ? `/assets/promo/gr-${layout.retina.organisation}.png`
                    : `/assets/promo/gr-inactive.png`
            "
            alt="Gold Reward logo"
            v-tooltip="ctrans('Gold Reward logo')"
            class="h-7 w-auto shrink-0"
        />

        <div
            class="flex items-center gap-2 rounded px-2 py-[3px] text-[8px] 2xl:text-xs font-semibold leading-none text-white"
            :class="active ? 'background-primary' : 'bg-[#c8c8c8]'"
        >
            <span v-if="offer?.allowances?.[0]?.percentage_off">
                {{ offer.allowances[0].percentage_off * 100 }}%

                <span class="hidden sm:inline">
                    {{ trans("OFF") }}
                </span>
            </span>

            <button
                type="button"
                class="flex items-center justify-center"
                @click="toggleInfo"
            >
                <FontAwesomeIcon
                    :icon="faInfoCircle"
                    class="text-[10px] 2xl:text-xs text-white/90 hover:text-white"
                />
            </button>
        </div>

       <Popover ref="infoPopover">
    <div class="max-w-[280px] space-y-3 text-sm">
        <p class="font-semibold">
            {{ trans("GR DISCOUNT") }}
        </p>

        <p class="text-[#555]">
            {{
                trans(
                    "This product qualifies for an exclusive Gold Reward member discount."
                )
            }}
        </p>

        <p class="text-[#555]">
            {{
                trans(
                    "Sign in with your Gold Reward account to access member pricing and enjoy additional savings on this product."
                )
            }}
        </p>
    </div>
</Popover>
    </div>
</template>