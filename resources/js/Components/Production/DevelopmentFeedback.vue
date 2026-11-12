<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import SelectButton from 'primevue/selectbutton'
import Checkbox from 'primevue/checkbox'
import Textarea from 'primevue/textarea'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faFlask, faCommentAltLines, faPaperPlane, faTimes } from '@fal'
library.add(faFlask, faCommentAltLines, faPaperPlane, faTimes)

const props = defineProps<{
    artefacts: { id: number, code: string }[]
    feedback: {
        route: { name: string, parameters: object, method: string }
        reasons: { value: string, label: string }[]
    }
}>()

const isOpen = ref(false)
const artefactId = ref<number | null>(props.artefacts.length === 1 ? props.artefacts[0].id : null)
const reasons = ref<string[]>([])
const comment = ref('')
const isSending = ref(false)
const error = ref<string | null>(null)
const sentFor = ref<string | null>(null)

watch(() => props.artefacts.map(artefact => artefact.id).join(','), () => {
    isOpen.value = false
    artefactId.value = props.artefacts.length === 1 ? props.artefacts[0].id : null
    sentFor.value = null
})

function send() {
    if (!artefactId.value) return
    isSending.value = true
    error.value = null
    const code = props.artefacts.find(artefact => artefact.id === artefactId.value)?.code ?? ''
    router.post(
        route(props.feedback.route.name, { artefact: artefactId.value }),
        { reasons: reasons.value, comment: comment.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                sentFor.value = code
                isOpen.value = false
                reasons.value = []
                comment.value = ''
            },
            onError: (errors: Record<string, string>) => error.value = Object.values(errors)[0] ?? ctrans('Could not send, try again'),
            onFinish: () => isSending.value = false,
        }
    )
}
</script>

<template>
    <div class="w-full rounded-xl border-2 border-violet-300 bg-violet-50 p-4 text-left">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-lg font-semibold text-violet-800">
                <FontAwesomeIcon icon="fal fa-flask" fixed-width aria-hidden="true" />
                {{ ctrans('Product in development: the steps and timings are still being worked out') }}
            </div>
            <button v-if="!isOpen" type="button" class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-3 text-lg font-semibold text-gray-700"
                @click="isOpen = true; sentFor = null">
                <FontAwesomeIcon icon="fal fa-comment-alt-lines" fixed-width aria-hidden="true" />
                {{ ctrans('Send feedback') }}
            </button>
        </div>
        <div v-if="sentFor && !isOpen" class="mt-2 text-green-700">{{ ctrans('Thank you, your feedback on :code went to the production manager', { code: sentFor }) }}</div>

        <div v-if="isOpen" class="mt-4 space-y-4">
            <SelectButton v-if="artefacts.length > 1" v-model="artefactId" :options="artefacts" optionLabel="code" optionValue="id" :allowEmpty="false" />
            <label v-for="reason in feedback.reasons" :key="reason.value" class="flex cursor-pointer items-center gap-3 text-lg">
                <Checkbox v-model="reasons" :value="reason.value" />
                {{ reason.label }}
            </label>
            <Textarea v-model="comment" rows="2" autoResize maxlength="1000" class="w-full text-lg" :placeholder="ctrans('Anything else? (optional)')" :aria-label="ctrans('Comment')" />
            <div v-if="error" class="text-red-600">{{ error }}</div>
            <div class="flex gap-3">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-[var(--app-accent)] px-8 py-3 text-xl font-semibold text-[var(--app-accent-text)] disabled:opacity-40"
                    :disabled="isSending || !artefactId || (!reasons.length && !comment.trim())" @click="send">
                    <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                    {{ ctrans('Send') }}
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-3 text-xl font-semibold text-gray-700"
                    @click="isOpen = false; error = null">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    {{ ctrans('Cancel') }}
                </button>
            </div>
        </div>
    </div>
</template>
