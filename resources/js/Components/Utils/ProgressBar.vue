<script setup lang='ts'>
import { watch, ref } from 'vue';
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faFrown, faMeh } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { set, throttle } from 'lodash-es'
import { inject } from 'vue'
library.add(faTimes, faFrown, faMeh, faSpinnerThird)

interface EchoPersonal {
    isShowProgress: boolean
    progressBars: {
        Upload: {
            [key: string]: {
                data: {
                    number_success: number
                    number_fails: number
                }
                done: number
                total: number
                estimatedTime: string
            }
        }
    }
}

const props = defineProps<{
    description?: string
    // echo: Object
   
}>()

const selectedEchopersonal = inject('selectedEchopersonal', {})
const closeModal = () => {
    // selectedEchopersonal.isShowProgress = false
    set(selectedEchopersonal, 'isShowProgress', false)
}

const throttledValue = throttle((newValue) => {
    return newValue
}, 800)

// watch(
//   () => piniaData,
//   (newVal) => {
//     if (!newVal.isShowProgress) {
//       props.echo.isShowProgress = false; // Not recommended if 'props' is read-only
//       // Instead, consider using a reactive or ref object in the local state
//       // For example:
//       // localState.isShowProgress = false;
//     }
//   },
//   { deep: true }
// );

// console.log(useEchoGrpPersonal().progressBars)

</script>

<template>
    <div :class="selectedEchopersonal?.isShowProgress ? 'bottom-16':'-bottom-24' "
        class="backdrop-blur-xs bg-white/60 ring-1 ring-gray-300 rounded-md px-4 py-2 z-[100] fixed right-1/2 translate-x-1/2 transition-all duration-200 ease-in-out flex gap-x-6 tabular-nums">
        <!-- <pre>{{ selectedEchopersonal?.isShowProgress }}</pre> -->
        <template v-if="Object.keys(selectedEchopersonal?.progressBars?.Upload ?? {}).length > 0">
            <TransitionGroup name="progressbar">
                <div v-for="(upload, index) in selectedEchopersonal?.progressBars?.Upload" :key="index" class="flex justify-center items-center flex-col gap-y-1 text-gray-600">
                    <template v-if="upload.total">
                        <div v-if="upload.done >= upload.total">
                            <!-- Label: All failed -->
                            <span v-if="upload.done == upload.data.number_fails" class="text-red-600">
                                {{ trans("Oops Award: No Bravos") }}
                                <FontAwesomeIcon icon='fal fa-frown' class='' aria-hidden='true' />
                            </span>

                            <!-- Label: All success -->
                            <span v-else-if="upload.done == upload.data.number_success" class="text-gray-500">
                                {{ trans("Success Streak: Nailed it") }}🥳
                            </span>

                            <!-- Label: Fails is bigger -->
                            <span v-else-if="upload.data.number_success < upload.data.number_fails" class="text-gray-500">
                                {{ trans("Oops, more fails than victories!") }}
                                <FontAwesomeIcon icon='fal fa-meh' class='' aria-hidden='true' />
                            </span>

                            <!-- Label: Success is bigger -->
                            <span v-else class="text-lime-600">Yeah, success roars😎</span>
                        </div>

                        <!-- Section: Uploading progress & time remaining -->
                        <template v-else>
                            <div>
                                {{ description ?? trans('Adding')}} ({{ upload.data.number_success + upload.data.number_fails }}/<span class="font-semibold inline">{{ upload.total }}</span>)
                            </div>
                            
                            <!-- Time Remaining  -->
                            <div class="text-xs text-gray-500 leading-none tabular-nums">{{ throttledValue(upload.estimatedTime) }} {{ trans("remaining") }}</div>
                        </template>
                
                        <!-- Progress Bar -->
                        <div class="overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-300 w-64 flex justify-start">
                            <div class="h-2 bg-lime-600 transition-all duration-100 ease-in-out" :style="`width: ${(upload.data.number_success/upload.total)*100}%`" />
                            <div class="h-2 bg-red-500 transition-all duration-100 ease-in-out" :style="`width: ${(upload.data.number_fails/upload.total)*100}%`" />
                        </div>
                        <!-- Result count -->
                        <div class="flex w-full justify-around">
                            <div class="text-lime-600">{{ trans("Success") }}: {{ upload.data.number_success }}</div>
                            <div class="text-red-500">{{ trans("Fails") }}: {{ upload.data.number_fails }}</div>
                        </div>
                    </template>
                </div>
            </TransitionGroup>
        </template>
        <div v-else class="w-64 flex justify-center flex-col items-center gap-y-2 py-1 text-gray-500">
            <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin' aria-hidden='true' />
            <div class="text-sm">{{ trans("Calculating data..") }}</div>
        </div>


        <div @click="closeModal" class="absolute top-0 right-1 px-2 py-1 cursor-pointer text-gray-500 hover:text-gray-600">
            <FontAwesomeIcon icon='fal fa-times' class='text-xs' aria-hidden='true' />
        </div>
    </div>
</template>

<style scoped>
.progressbar-move, /* apply transition to moving elements */
.progressbar-enter-active,
.progressbar-leave-active {
    transition: all 0.5s ease;
}

.progressbar-enter-from,
.progressbar-leave-to {
    opacity: 0;
    transform: scale(0.1);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.progressbar-leave-active {
    position: absolute;
}
</style>