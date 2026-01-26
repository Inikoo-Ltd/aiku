<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { inject, ref } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faQuestionCircle)

const props = defineProps<{
    product: {}
}>()

const layout = inject('layout', layoutStructure)

const _popoverQuestionCircle = ref(null)
</script>

<template>
    <div class="relative w-fit">
        <div class="bg-gray-400 rounded px-2 py-0.5 text-xxs w-fit text-white">{{ trans("Member Price") }}</div>
        <div class="my-1.5 text-xs text-balance">
            {{ trans("Not a member?") }} <span @click="_popoverQuestionCircle?.toggle" @mouseenter="_popoverQuestionCircle?.show" @mouseleave="_popoverQuestionCircle?.hide" class="cursor-pointer">
                <FontAwesomeIcon icon="fal fa-question-circle" class="" fixed-width aria-hidden="true" />
            </span>
        </div>

        <!-- Popover: Question circle GR member -->
        <Popover ref="_popoverQuestionCircle" :style="{width: '250px'}" class="py-1 px-2">
            <div class="text-xs">
                <p class="font-bold mb-4">{{ trans("VOLUME DISCOUNT") }}</p>
                <p class="inline-block mb-4 text-justify">
                    {{ trans("You don't need Gold Reward status to access the lower price") }}.
                </p>
                <p class="mb-4 text-justify">
                    {{ trans("Order the listed volume and the member price applies automatically at checkout") }}. {{ trans("The volume can be made up from the whole product family, not just the same item") }}.
                </p>
            </div>
        </Popover>
    </div>
</template>