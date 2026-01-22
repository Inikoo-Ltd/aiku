<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { inject, ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { layoutStructure } from '@/Composables/useLayoutStructure'
library.add(faInfoCircle)

// const props = defineProps<{
//     offer: {

//     }
// }>()

const layout = inject('layout', layoutStructure)

const _popoverInfoCircle = ref<InstanceType<any>[] | null>(null)
</script>

<template>
    <section
        class="relative w-full md:w-fit flex justify-between items-center rounded-lg px-5 py-1 shadow-md text-white mb-2"
        :class="layout?.user?.gr_data?.customer_is_gr ? 'bg-[#ff862f]' : 'bg-gray-400/70'"
    >
        
        <!-- Content -->
        <div class="w-full relative flex items-center text-3xl">
            {{ layout?.user?.gr_data?.customer_is_gr ? trans("Gold Reward Active") : trans("Gold Reward Inactive") }}
        </div>

        <span @click="_popoverInfoCircle?.toggle" @mouseenter="_popoverInfoCircle?.show" @mouseleave="_popoverInfoCircle?.hide" class="align-middle ml-2 opacity-80 hover:opacity-100 cursor-pointer">
            <FontAwesomeIcon icon="fal fa-info-circle" class="align-middle" fixed-width aria-hidden="true" />
        </span>

        <!-- Popover: Question circle GR member -->
        <Popover ref="_popoverInfoCircle" :style="{width: '350px'}" class="py-1 px-2">
            <div class="text-xs">
                <p class="font-bold mb-4">{{ trans("Gold Reward Membership") }}</p>
                <p class="inline-block mb-4 text-justify">
                    {{ trans("Place an order within 30 days of your last invoice and Gold Reward status applies automatically. This unlocks the best pricing across eligible ranges, without needing to bulk up every order") }}.
                </p>
            </div>
        </Popover>
    </section>
</template>