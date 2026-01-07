<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck } from '@far'
import { faCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faCheck, faCircle)

const props = defineProps<{
    currentStep: number
    options: {
        [key: string]: string
    }[]
}>()

const emits = defineEmits<{
    (e: 'nextStep'): void
    (e: 'previousStep'): void
}>()

</script>

<template>
    <div class="w-full py-6 flex">
        <!-- Step -->
        <div v-for="(step, stepIndex) in options"
            class="w-full">
            <div class="relative mb-2">
                <!-- Step: Tail -->
                <div v-if="stepIndex != 0"
                    class="w-full px-7 absolute flex align-center items-center align-middle content-center -translate-x-1/2 top-1/2 -translate-y-1/2">
                    <div class="w-full rounded-sm items-center align-middle align-center flex-1">
                        <div class="w-full py-1 rounded-sm" :class="[
                            stepIndex <= currentStep ? 'bg-lime-300' : 'bg-gray-200',
                            stepIndex == currentStep ? 'shimmer' : ''
                        ]" />
                    </div>
                </div>

                <!-- Step: Head -->
                <div @click="stepIndex < currentStep ? emits('previousStep') : ''"
                    class="h-10 aspect-square mx-auto rounded-full text-lg flex items-center"
                    :class="[
                        stepIndex == currentStep
                            ? 'ring-1 ring-lime-500 text-lime-500'  // If current
                            : stepIndex <= currentStep ? 'bg-lime-500 text-white' : 'ring-1 ring-gray-300 text-gray-300'  // before or after current
                    ]"
                >
                    <span class="text-center w-full">
                        <FontAwesomeIcon v-if="stepIndex < currentStep" icon='far fa-check' class='' aria-hidden='true' />
                        <FontAwesomeIcon v-if="stepIndex >= currentStep" icon='fas fa-circle' class='' aria-hidden='true' />
                    </span>
                </div>
            </div>

            <!-- Step: Description -->
            <div class="text-xs text-center md:text-base" :class="stepIndex > currentStep ? 'opacity-50' : ''">{{ step.label ?? '' }}</div>
        </div>
    </div>
</template>