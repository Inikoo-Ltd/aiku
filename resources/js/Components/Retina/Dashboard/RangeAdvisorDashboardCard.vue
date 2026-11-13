<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSparkles, faArrowRight } from "@far"
import { ctrans } from "@/Composables/useTrans"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { shoppingAssistantQuestionKey } from "@/Iris/Composables/useShoppingAssistant"
import Button from "@/Components/Elements/Buttons/Button.vue"
library.add(faSparkles, faArrowRight)

const layout: any = inject("layout", retinaLayoutStructure)

const name = computed(() => layout.iris?.shopping_assistant?.name || ctrans("Range Advisor"))
const draft = ref("")

const starterQuestions = computed(() => [
    ctrans("What did I use to order that still sells?"),
    ctrans("Low-priced Christmas gifts that sell well"),
])

const ask = (question: string) => {
    if (question.trim()) {
        sessionStorage.setItem(shoppingAssistantQuestionKey, question.trim().slice(0, 1000))
        router.visit(route("retina.ecom.range_advisor.show"))
    }
}
</script>

<template>
    <section class="flex flex-col rounded-lg border border-gray-200 bg-white p-4 lg:self-stretch" aria-labelledby="range-advisor-card-heading">
        <div class="flex items-start gap-3">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[var(--app-accent-soft)] text-[var(--app-accent)]" aria-hidden="true">
                <FontAwesomeIcon icon="far fa-sparkles" fixed-width />
            </span>
            <div class="min-w-0">
                <h2 id="range-advisor-card-heading" class="text-lg font-semibold text-gray-900">{{ ctrans("Ask :name", { name }) }}</h2>
                <p class="mt-1 text-sm text-gray-700">{{ ctrans(":name finds what sells for shops like yours, from what is in stock now.", { name }) }}</p>
            </div>
        </div>

        <Textarea
            v-model="draft"
            rows="4"
            maxlength="1000"
            class="mt-3 min-h-28 w-full flex-1 resize-none text-sm"
            :aria-label="ctrans('Describe your shop')"
            :placeholder="ctrans('e.g. We’re a small crystal and gift shop in York. I’d like Christmas gifts and to try bath & body.')"
            @keydown.enter.exact.prevent="ask(draft)"
        />

        <div class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="question in starterQuestions"
                :key="question"
                type="button"
                class="rounded-full border border-gray-300 bg-white px-3 py-1 text-xs text-gray-700 hover:bg-gray-50"
                @click="ask(question)"
            >
                {{ question }}
            </button>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <Link :href="route('retina.ecom.range_advisor.show')" class="text-sm text-gray-600 underline hover:text-gray-900">
                {{ ctrans("See :name’s picks for you", { name }) }}
            </Link>
            <Button type="tertiary" size="xs" :label="ctrans('Ask')" :disabled="!draft.trim()" @click="ask(draft)">
                <template #icon>
                    <FontAwesomeIcon icon="far fa-arrow-right" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </section>
</template>
