<script setup lang='ts'>
import { ref, inject } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faFrown, faMeh, faChevronDown, faChevronUp } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { throttle } from 'lodash-es'
import { routeType } from '@/types/route'
library.add(faTimes, faFrown, faMeh, faChevronDown, faChevronUp, faSpinnerThird)

interface FailReason {
    message: string
    count: number
    rows: number[]
}

interface UploadProgress {
    data: {
        number_success: number
        number_fails: number
    }
    done: number
    total: number
    estimatedTime: string
    fail_reasons?: FailReason[]
    report_route?: routeType | null
    show_route?: routeType
}

interface EchoPersonal {
    isShowProgress: boolean
    progressBars: {
        Upload?: Record<string, UploadProgress>
    }
}

defineProps<{
    description?: string
}>()

const selectedEchopersonal = inject<EchoPersonal>('selectedEchopersonal', { isShowProgress: false, progressBars: {} })

const expandedUploads = ref<Record<string, boolean>>({})

const isFinished = (upload: UploadProgress) => upload.done >= upload.total

const closeModal = () => {
    selectedEchopersonal.isShowProgress = false

    const uploads = selectedEchopersonal.progressBars?.Upload ?? {}
    Object.keys(uploads).forEach((uploadId) => {
        if (isFinished(uploads[uploadId])) {
            delete uploads[uploadId]
            delete expandedUploads.value[uploadId]
        }
    })
}

const toggleReasons = (uploadId: string) => {
    expandedUploads.value[uploadId] = !expandedUploads.value[uploadId]
}

const rowsLabel = (reason: FailReason) => {
    const rows = reason.rows.join(', ')
    const remaining = reason.count - reason.rows.length

    if (remaining > 0) {
        return ctrans('Rows :rows and :remaining more', { rows, remaining: String(remaining) })
    }

    return reason.rows.length > 1 ? ctrans('Rows :rows', { rows }) : ctrans('Row :rows', { rows })
}

const throttledValue = throttle((newValue) => {
    return newValue
}, 800)
</script>

<template>
    <div :class="selectedEchopersonal?.isShowProgress ? 'bottom-16':'-bottom-24' "
        class="backdrop-blur-sm bg-white/90 shadow-lg ring-1 ring-gray-300 rounded-md py-2 pl-4 pr-10 z-[100] fixed right-1/2 translate-x-1/2 transition-all duration-200 ease-in-out flex gap-x-6 tabular-nums">
        <template v-if="Object.keys(selectedEchopersonal?.progressBars?.Upload ?? {}).length > 0">
            <TransitionGroup name="progressbar">
                <div v-for="(upload, uploadId) in selectedEchopersonal?.progressBars?.Upload" :key="uploadId" class="flex justify-center items-center flex-col gap-y-1 text-gray-600 w-72">
                    <template v-if="upload.total">
                        <div v-if="isFinished(upload)" class="text-center">
                            <span v-if="upload.data.number_fails === 0" class="text-lime-600">
                                {{ ctrans("Finished, all :count rows added", { count: String(upload.data.number_success) }) }}🥳
                            </span>

                            <span v-else-if="upload.data.number_success === 0" class="text-red-600">
                                {{ ctrans("Finished, none of the :count rows could be added", { count: String(upload.data.number_fails) }) }}
                                <FontAwesomeIcon icon='fal fa-frown' fixed-width aria-hidden='true' />
                            </span>

                            <span v-else class="text-gray-600">
                                {{ ctrans("Finished, :success added and :fails failed", { success: String(upload.data.number_success), fails: String(upload.data.number_fails) }) }}
                                <FontAwesomeIcon icon='fal fa-meh' fixed-width aria-hidden='true' />
                            </span>
                        </div>

                        <template v-else>
                            <div>
                                {{ description ?? ctrans('Adding')}} ({{ upload.data.number_success + upload.data.number_fails }}/<span class="font-semibold inline">{{ upload.total }}</span>)
                            </div>

                            <div class="text-xs text-gray-500 leading-none tabular-nums">{{ throttledValue(upload.estimatedTime) }} {{ ctrans("remaining") }}</div>
                        </template>

                        <div class="overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-300 w-64 flex justify-start">
                            <div class="h-2 bg-lime-600 transition-all duration-100 ease-in-out" :style="`width: ${(upload.data.number_success/upload.total)*100}%`" />
                            <div class="h-2 bg-red-500 transition-all duration-100 ease-in-out" :style="`width: ${(upload.data.number_fails/upload.total)*100}%`" />
                        </div>

                        <div class="flex w-full justify-around">
                            <div class="text-lime-600">{{ ctrans("Success") }}: {{ upload.data.number_success }}</div>
                            <div class="text-red-500">{{ ctrans("Fails") }}: {{ upload.data.number_fails }}</div>
                        </div>

                        <template v-if="upload.fail_reasons?.length">
                            <button
                                type="button"
                                @click="() => toggleReasons(String(uploadId))"
                                class="text-xs text-red-500 hover:text-red-600 hover:underline flex items-center gap-x-1"
                            >
                                {{ expandedUploads[uploadId] ? ctrans('Hide why rows failed') : ctrans('Why did :count rows fail?', { count: String(upload.data.number_fails) }) }}
                                <FontAwesomeIcon :icon="expandedUploads[uploadId] ? 'fal fa-chevron-up' : 'fal fa-chevron-down'" class="text-[10px]" fixed-width aria-hidden='true' />
                            </button>

                            <div v-if="expandedUploads[uploadId]" class="w-full max-h-56 overflow-y-auto border-t border-gray-200 pt-2 flex flex-col gap-y-2 text-xs">
                                <div v-for="reason in upload.fail_reasons" :key="reason.message" class="flex gap-x-2">
                                    <span class="shrink-0 font-semibold text-red-500 tabular-nums">{{ reason.count }}×</span>
                                    <div>
                                        <div class="text-gray-700">{{ reason.message }}</div>
                                        <div class="text-gray-400">{{ rowsLabel(reason) }}</div>
                                    </div>
                                </div>

                                <Link
                                    v-if="upload.report_route?.name"
                                    :href="route(upload.report_route.name, upload.report_route.parameters)"
                                    class="self-center text-[--app-accent] hover:text-[--app-accent-strong] hover:underline"
                                    @click="closeModal"
                                >
                                    {{ ctrans('See this upload in the upload history') }}
                                </Link>
                                <Link
                                    v-else-if="upload.show_route?.name"
                                    :href="route(upload.show_route.name, upload.show_route.parameters)"
                                    class="self-center text-[--app-accent] hover:text-[--app-accent-strong] hover:underline"
                                >
                                    {{ ctrans('See every row of this upload') }}
                                </Link>
                            </div>
                        </template>
                    </template>
                </div>
            </TransitionGroup>
        </template>
        <div v-else class="w-64 flex justify-center flex-col items-center gap-y-2 py-1 text-gray-500">
            <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin' fixed-width aria-hidden='true' />
            <div class="text-sm">{{ ctrans("Calculating data..") }}</div>
        </div>

        <button
            type="button"
            @click="closeModal"
            class="absolute top-1.5 right-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-gray-600 ring-1 ring-gray-300 transition-colors hover:bg-gray-200 hover:text-gray-900"
            :aria-label="ctrans('Close')"
            v-tooltip="ctrans('Close')"
        >
            <FontAwesomeIcon icon='fal fa-times' class='text-sm' fixed-width aria-hidden='true' />
        </button>
    </div>
</template>

<style scoped>
.progressbar-move,
.progressbar-enter-active,
.progressbar-leave-active {
    transition: all 0.5s ease;
}

.progressbar-enter-from,
.progressbar-leave-to {
    opacity: 0;
    transform: scale(0.1);
}

.progressbar-leave-active {
    position: absolute;
}
</style>
