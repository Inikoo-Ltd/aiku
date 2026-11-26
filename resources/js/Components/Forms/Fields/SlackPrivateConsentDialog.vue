<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import Checkbox from 'primevue/checkbox'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes } from '@fal'

const props = defineProps<{
    consentVersion: string
    termsVersion: string
    startRoute: { name: string }
    unavailableReason?: string | null
}>()

const visible = defineModel<boolean>('visible', { default: false })
const understood = ref(false)
const showTerms = ref(false)
const isSending = ref(false)
const error = ref<string | null>(null)

watch(visible, (isOpen) => {
    if (isOpen) {
        understood.value = false
        showTerms.value = false
        error.value = null
    }
})

const terms = [
    'Verba is provided by the company as a work tool. Messages, files and pictures in Verba are company records.',
    'Use Verba for work. Occasional social messages are allowed. Do not use it for private or non-work conversations, pictures or files. Offensive, discriminatory, harassing, sexual or illegal content breaks company rules and may lead to disciplinary action.',
    'By connecting your Slack, you agree that Verba copies, and keeps up to date, the messages and files you wrote in your direct messages and group chats, and the private channels you are a member of.',
    'Each conversation can be seen only by its members. The company does not monitor conversations.',
    'Anyone else may open a direct message, a deleted message or a message kept for a legal reason only to meet a legal obligation or for a formal investigation. This needs the approval of two group administrators and is recorded. The members are told, unless the law forbids it.',
    'Deleting a message removes it from the conversation. The company keeps a copy.',
    'Messages are kept as company records. Messages in public and private channels stay. You can ask, at any time and also after you leave, for your own words in direct messages and group chats to be removed. A group administrator decides. They are removed at once, unless a legal reason (a dispute or claim, an investigation or a legal duty) requires keeping them; then they are kept hidden until that reason ends, at most 6 years. You are told either way.',
    'You can withdraw your consent at any time in your settings. The direct messages Verba brought from your Slack are then deleted. Private channels stay, as they belong to all their members. Your messages in Slack are not affected.',
    'Messages are protected by access controls. They are not end-to-end encrypted. They are stored on servers and storage run for the company, with backups.',
    'You can ask for a copy of your data, or for it to be corrected, or deleted as clause 7 says, subject to the company\'s legal obligations. Ask the help desk.',
    'These terms may change. You will be told before they do.',
]

const submit = () => {
    router.post(route(props.startRoute.name), {
        understood: understood.value,
        consent_version: props.consentVersion,
        terms_version: props.termsVersion,
    }, {
        preserveScroll: true,
        onStart: () => { isSending.value = true; error.value = null },
        onError: (errors) => { error.value = Object.values(errors)[0] as string },
        onFinish: () => isSending.value = false,
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Bring your Slack messages to Verba')" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm text-gray-700">
            <p>{{ ctrans('Here\'s what Verba will keep, and keep up to date from now on:') }}</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>
                    <span class="font-semibold">{{ ctrans('Your own messages and files in direct messages and group chats.') }}</span>
                    {{ ctrans('Only your words: the others\' messages come over only when they bring them themselves. Until then you\'ll see a little gap where their message was.') }}
                </li>
                <li>
                    <span class="font-semibold">{{ ctrans('The private channels you\'re in, as a whole.') }}</span>
                    {{ ctrans('They\'re shared work spaces, so everyone in the channel will see that you brought it.') }}
                </li>
            </ul>
            <p><span class="font-semibold">{{ ctrans('Who can see them:') }}</span> {{ ctrans('only the people in each conversation, wherever they appear in Verba: the chat, search, alerts and AI tools. Not your manager, and not administrators.') }}</p>
            <p><span class="font-semibold">{{ ctrans('How long they\'re kept:') }}</span> {{ ctrans('as work records. You can ask at any time, even after you\'ve left, for your own words in direct messages and group chats to be removed. Private channels stay, because they belong to everyone in them.') }}</p>
            <p><span class="font-semibold">{{ ctrans('Changed your mind?') }}</span> {{ ctrans('Switch this off whenever you like in your settings, and the direct messages Verba brought from Slack are deleted. Private channels stay. Nothing changes in Slack itself.') }}</p>
            <p class="italic">{{ ctrans('A quick reminder: Verba is the company\'s work chat. Birthday wishes and the odd "well done" are very welcome, just keep it work-friendly. If an old Slack conversation has something private or non-work in it, delete those messages in Slack before you bring it over.') }}</p>

            <label class="flex items-center gap-2 pt-1 cursor-pointer">
                <Checkbox v-model="understood" binary input-id="slack-private-understood" />
                <span class="font-medium">{{ ctrans('I understand') }}</span>
            </label>

            <p v-if="unavailableReason" class="text-gray-500">{{ unavailableReason }}</p>
            <p v-if="error" class="text-xs text-red-600" role="alert">{{ error }}</p>
        </div>

        <template #footer>
            <div class="flex w-full flex-col gap-3">
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Not now')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Bring my messages')" :loading="isSending" :disabled="!understood || isSending || !!unavailableReason" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <div class="text-xs text-gray-500">
                <button type="button" class="underline" @click="showTerms = !showTerms">{{ ctrans('Terms') }}</button>
                <ol v-if="showTerms" class="list-decimal pl-5 mt-2 space-y-1 max-h-48 overflow-y-auto">
                    <li v-for="term in terms" :key="term">{{ ctrans(term) }}</li>
                </ol>
            </div>
            </div>
        </template>
    </Dialog>
</template>
