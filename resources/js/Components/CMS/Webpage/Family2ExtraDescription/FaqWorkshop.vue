<script setup lang="ts">
import { ref, computed} from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faMinus } from "@fortawesome/free-solid-svg-icons"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
    fieldValue: any
    screenType: "mobile" | "tablet" | "desktop"
}>()

const openIndex = ref<number | null>(0)

const toggle = (index: number) => {
    openIndex.value = openIndex.value === index ? null : index
}

const containerStyle = computed(() => (getStyles(props.fieldValue?.faq?.container?.properties)))
</script>

<template>
  
    <section
        v-if="fieldValue?.family?.faq?.length"
        class="w-full" :style="containerStyle"
    >
    
        <div
            class="mx-auto w-full"
        >
            <div
                v-for="(item, index) in fieldValue.family.faq"
                :key="index"
                class="border-b border-[#B8BCC1]"
            >
                <button
                    type="button"
                    :aria-expanded="openIndex === index"
                    class="group flex w-full items-center justify-between gap-4 py-4 sm:py-5 text-left"
                    @click="toggle(index)"
                >
                    <span
                        class="flex-1 text-[15px] sm:text-base lg:text-[17px] font-medium leading-6 text-black"
                    >
                        {{ item.question }}
                    </span>

                    <FontAwesomeIcon
                        :icon="openIndex === index ? faMinus : faPlus"
                        class="shrink-0 text-sm text-black transition-transform duration-200"
                    />
                </button>

                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[1000px] opacity-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="max-h-[1000px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div
                        v-if="openIndex === index"
                        class="overflow-hidden"
                    >
                        <div
                            class="pb-5 pr-8 text-sm leading-6 text-neutral-700 "
                        >
                            {{ item.answer }}
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </section>
</template>