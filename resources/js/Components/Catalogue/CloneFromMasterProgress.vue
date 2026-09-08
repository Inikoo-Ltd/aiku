<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes, faChevronDown } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useEchoGrpPersonal, type CloneFamilyProgress } from '@/Stores/echo-grp-personal'
library.add(faCheck, faTimes, faChevronDown, faSpinnerThird)

const echoPersonal = useEchoGrpPersonal()

const isMounted = ref(false)
onMounted(() => isMounted.value = true)

const progressList = computed(() => echoPersonal.cloneFamilyProgressList)
const runningCount = computed(() => progressList.value.filter(progress => !progress.isFinished).length)
const isAllFinished = computed(() => runningCount.value === 0)

const barsOf = (progress: CloneFamilyProgress) => [
    {
        key: 'families',
        label: trans('Families'),
        done: progress.families.done,
        total: progress.families.total,
    },
    {
        key: 'products',
        label: trans('Products'),
        done: progress.products.done,
        total: progress.products.total,
    },
]

const percentageOf = (done: number, total: number) => total > 0 ? Math.min(100, (done / total) * 100) : 0

const onCloseAll = () => {
    progressList.value.forEach(progress => echoPersonal.clearCloneFamilyProgress(progress.masterFamilyId))
    echoPersonal.isShowCloneFamilyProgress = false
}
</script>

<template>
    <Teleport v-if="isMounted && echoPersonal.hasCloneFamilyProgress" to="#clone-from-master-progress">
        <div class="relative h-full flex items-center">
            <button
                type="button"
                class="h-full flex items-center gap-x-2 px-3 hover:bg-white/10 transition-all duration-200 ease-in-out"
                @click="echoPersonal.isShowCloneFamilyProgress = !echoPersonal.isShowCloneFamilyProgress">
                <FontAwesomeIcon
                    v-if="!isAllFinished"
                    icon="fad fa-spinner-third"
                    class="animate-spin text-amber-400"
                    fixed-width
                    aria-hidden="true" />
                <FontAwesomeIcon
                    v-else
                    icon="fal fa-check"
                    class="text-lime-400"
                    fixed-width
                    aria-hidden="true" />
                <span class="text-xs">
                    {{ isAllFinished ? trans('Cloning finished') : trans('Cloning to shops…') }}
                </span>
                <span
                    v-if="runningCount > 1"
                    class="rounded-full bg-white/20 px-1.5 text-[10px] tabular-nums">
                    {{ runningCount }}
                </span>
            </button>

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0">
                <div
                    v-if="echoPersonal.isShowCloneFamilyProgress"
                    class="absolute bottom-full right-0 mb-2 z-[9999] w-[380px] max-w-[92vw] rounded-lg border border-gray-200 bg-white text-gray-700 shadow-2xl overflow-hidden">
                    <div class="flex items-center gap-x-2 border-b border-gray-200 bg-gray-50 px-3 py-2">
                        <span class="text-xs font-semibold text-gray-700">
                            {{ isAllFinished ? trans('Cloning finished') : trans('Cloning to shops…') }}
                        </span>
                        <div class="ml-auto flex items-center gap-x-1">
                            <button
                                type="button"
                                v-tooltip="ctrans('Hide')"
                                class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600"
                                @click="echoPersonal.isShowCloneFamilyProgress = false">
                                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true" />
                            </button>
                            <button
                                v-if="isAllFinished"
                                type="button"
                                v-tooltip="ctrans('Close')"
                                class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600"
                                @click="onCloseAll()">
                                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    <div class="max-h-72 space-y-3 overflow-y-auto p-3">
                        <div v-for="progress in progressList" :key="progress.masterFamilyId" class="space-y-2">
                            <div class="flex items-center gap-x-2 text-xs">
                                <FontAwesomeIcon
                                    v-if="!progress.isFinished"
                                    icon="fad fa-spinner-third"
                                    class="animate-spin text-gray-400"
                                    fixed-width
                                    aria-hidden="true" />
                                <FontAwesomeIcon
                                    v-else
                                    icon="fal fa-check"
                                    class="text-lime-600"
                                    fixed-width
                                    aria-hidden="true" />
                                <span class="truncate font-semibold text-gray-700">
                                    {{ progress.masterFamily || trans('Preparing to clone…') }}
                                </span>
                            </div>

                            <div v-for="bar in barsOf(progress)" :key="bar.key" class="flex items-center gap-x-2">
                                <div class="w-16 shrink-0 text-xs text-gray-500">{{ bar.label }}</div>
                                <div class="relative h-3 flex-1 overflow-hidden rounded-full bg-gray-200 ring-1 ring-gray-300">
                                    <div
                                        class="h-full bg-lime-500 transition-all duration-300 ease-in-out"
                                        :style="{ width: percentageOf(bar.done, bar.total) + '%' }" />
                                    <div class="absolute inset-0 flex items-center justify-center text-[10px] font-medium tabular-nums text-gray-700">
                                        {{ bar.done }} / {{ bar.total }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
