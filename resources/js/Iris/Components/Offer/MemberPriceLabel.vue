<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans";
import { inject, ref } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import Popover from "primevue/popover"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMedal } from "@fas";

library.add(faInfoCircle)

const props = defineProps<{
    active?: boolean
    offer?: {
        allowances?: {
            percentage_off?: number
        }[]
    }
}>()

const layout = inject("layout", retinaLayoutStructure)

const infoPopover = ref<any>(null)

const showInfo = (event?: Event) => {
    infoPopover.value?.show?.(event)
}

const hideInfo = (event?: Event) => {
    infoPopover.value?.hide?.(event)
}




const webpage_data = inject("webpage_data", null)


</script>

<template>
       <!--  <img :src="active
            ? `/assets/promo/gr-aw.png`
            // : `/assets/promo/gr-inactive.png`
            : `/assets/promo/gr-inactive-2.png`
            " alt="Gold Reward" v-tooltip="ctrans('Gold Reward')" class="h-7 w-auto shrink-0" /> -->

        <div class="inline-flex items-center gap-1 rounded cursor-pointer transition-all duration-150 "
            @mouseenter="showInfo"
            @mouseleave="hideInfo"
            @focus="showInfo"
            @blur="hideInfo"
            tabindex="0"
            aria-haspopup="true">

            <FontAwesomeIcon :icon="faMedal" class="text-lg" :class="active ? 'text-[#E87928]' : 'text-[#b3b3b3]'"/>

        <div class="flex items-center gap-2 rounded px-1 md:py-[5px] py-[3px] xl:py-[3px] text-[8px] xl:text-[10px] 2xl:text-xs font-semibold leading-none cursor-pointer text-white transform transition-all duration-150 "
        :class="[ active ? 'bg-[#E87928] ' : 'bg-[#b3b3b3] border border-transparent ' ]" tabindex="0" aria-haspopup="true">
        <!-- :class="active ? 'bg-[#E87928]' : 'bg-[#c8c8c8]'"> -->
            <div v-if="offer?.allowances?.[0]?.percentage_off">
                {{ offer.allowances[0].percentage_off * 100 }}%

               <!--  <span class="hidden lg:inline-flex">
                    {{ ctrans("OFF") }}
                </span> -->
            </div>
        </div>

        <Popover ref="infoPopover">
            <div class="max-w-[280px] space-y-3 text-sm">
                <p class="font-semibold">
                    {{ ctrans("GOLD REWARD DISCOUNT") }}
                </p>

                <div v-if="webpage_data?.sub_type != 'family'">
                    <p class="text-[#555]">
                        {{
                            ctrans(
                                "This product qualifies for an exclusive Gold Reward member discount."
                            )
                        }}
                    </p>

                    <p class="text-[#555]">
                        {{
                            ctrans(
                                "Sign in with your Gold Reward account to access member pricing and enjoy additional savings on  this product."
                            )
                        }}
                    </p>

                </div>
                <div v-else>
                    <p class="text-[#555]" v-html="offer?.products_triggers_label">
                    </p>
                </div>


            </div>
        </Popover>
    </div>
</template>