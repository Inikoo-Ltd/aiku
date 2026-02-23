<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { Popover } from "primevue"
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faQuestionCircle)

defineProps<{
    product: {}
}>()

const _popoverQuestionCircle = ref(null)
</script>

<template>
    <div class="member-price-wrapper">
        <div class="member-badge">
            {{ trans("Member Price") }}
        </div>

        <div class="member-text">
            {{ trans("Not a member?") }}
            <span
                class="question-trigger"
                @click="_popoverQuestionCircle?.toggle"
                @mouseenter="_popoverQuestionCircle?.show"
                @mouseleave="_popoverQuestionCircle?.hide"
            >
                <FontAwesomeIcon icon="fal fa-question-circle" fixed-width aria-hidden="true" />
            </span>
        </div>

        <Popover ref="_popoverQuestionCircle" class="member-popover ">
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
    </div>
</template>

<style scoped>
.member-price-wrapper {
    @apply relative w-fit;
}

.member-badge {
    @apply bg-gray-400 rounded px-2 py-0.5 text-xxs md:text-xs text-white w-fit;
}

.member-text {
    @apply my-1.5 text-xs;
    text-wrap: balance;
}

.question-trigger {
    @apply cursor-pointer ml-0.5;
}

.member-popover {
    @apply py-1 px-2 w-max max-w-[90vw];
}

.popover-content {
    @apply text-xs w-max max-w-[260px];
}

.popover-title {
    @apply font-bold mb-4;
}

.popover-paragraph {
    @apply mb-4 ;
}
</style>
