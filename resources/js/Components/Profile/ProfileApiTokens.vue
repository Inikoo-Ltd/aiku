<script setup lang='ts'>
import { ref } from 'vue'
import axios from 'axios'
import { ctrans } from '@/Composables/useTrans'
import { notify } from '@kyvg/vue3-notification'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useFormatTime } from '@/Composables/useFormatTime'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faKey, faCopy, faTrashAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faKey, faCopy, faTrashAlt)

interface ApiToken {
    id: number
    name: string
    last_used_at: string | null
    created_at: string
}

const props = defineProps<{
    data: {
        tokens: ApiToken[]
    }
}>()

const tokens = ref<ApiToken[]>(props.data.tokens || [])
const newTokenName = ref('')
const newPlainTextToken = ref('')
const isCreating = ref(false)
const deletingId = ref<number | null>(null)

const refreshTokens = async () => {
    const { data } = await axios.get(route('grp.profile.api-tokens.index'))
    tokens.value = data.tokens
}

const onCreateToken = async () => {
    if (!newTokenName.value.trim()) {
        return
    }

    isCreating.value = true
    try {
        const { data } = await axios.post(route('grp.profile.api-tokens.store'), {
            name: newTokenName.value.trim()
        })
        newPlainTextToken.value = data.token
        newTokenName.value = ''
        await refreshTokens()
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong.'),
            text: ctrans('Failed to create token.'),
            type: 'error',
        })
    } finally {
        isCreating.value = false
    }
}

const onDeleteToken = async (token: ApiToken) => {
    deletingId.value = token.id
    try {
        await axios.delete(route('grp.profile.api-tokens.delete', { tokenId: token.id }))
        await refreshTokens()
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong.'),
            text: ctrans('Failed to revoke token.'),
            type: 'error',
        })
    } finally {
        deletingId.value = null
    }
}

const isCopied = ref(false)
const onCopyToken = () => {
    navigator.clipboard.writeText(newPlainTextToken.value)
    isCopied.value = true
    setTimeout(() => isCopied.value = false, 2000)
}

const mcpUrl = `${window.location.origin}/mcp/aiku`

const copiedSnippet = ref('')
const onCopySnippet = (key: string, text: string) => {
    navigator.clipboard.writeText(text)
    copiedSnippet.value = key
    setTimeout(() => copiedSnippet.value = '', 2000)
}
</script>

<template>
    <div class="p-6 max-w-3xl space-y-6">
        <div class="space-y-3">
            <h2 class="text-lg font-semibold">{{ ctrans('Connect Aiku to your AI assistant') }}</h2>
            <p class="text-sm text-gray-600">
                {{ ctrans('This lets your AI assistant answer questions using Aiku data, for example "What were the sales in my shop last month?". It can only see what you can see in Aiku, and it can never change anything.') }}
            </p>
            <p class="text-sm text-gray-600">
                {{ ctrans('You only need the address below. Your assistant will ask you to sign in to Aiku and approve it — no key to copy. (Perplexity is the exception: it asks for a key, see Access keys at the bottom.)') }}
            </p>
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">{{ ctrans('Address') }}:</span>
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs select-all">{{ mcpUrl }}</code>
                <Button
                    :label="copiedSnippet === 'url' ? ctrans('Copied!') : ctrans('Copy')"
                    icon="fal fa-copy"
                    type="tertiary"
                    size="xs"
                    @click="onCopySnippet('url', mcpUrl)"
                />
            </div>

            <details class="rounded-md border border-gray-200">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                    Claude
                </summary>
                <ol class="list-decimal space-y-1.5 px-4 pb-4 pl-9 pt-1 text-sm text-gray-600">
                    <li>{{ ctrans('Open claude.ai and click your initials (bottom left), then') }} <span class="font-medium">{{ ctrans('Settings') }}</span></li>
                    <li>{{ ctrans('Click') }} <span class="font-medium">{{ ctrans('Connectors') }}</span>, {{ ctrans('then') }} <span class="font-medium">{{ ctrans('Add custom connector') }}</span></li>
                    <li>{{ ctrans('Name: type') }} <span class="font-medium">Aiku</span>. {{ ctrans('URL: paste the address above') }}</li>
                    <li>{{ ctrans('Click Connect — a page from Aiku opens. Sign in with your normal Aiku username and password and click Approve') }}</li>
                    <li>{{ ctrans('Start a new chat and ask something, for example: "How many orders did my shop get this week?"') }}</li>
                    <li class="text-gray-500">{{ ctrans('If Aiku is already listed from an earlier attempt, remove it first and add it again') }}</li>
                </ol>
            </details>

            <details class="rounded-md border border-gray-200">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                    Perplexity
                </summary>
                <div class="space-y-2 px-4 pb-4 pt-1 text-sm text-gray-600">
                    <p class="text-xs text-amber-600">{{ ctrans('Only works on paid Perplexity plans.') }}</p>
                    <ol class="list-decimal space-y-1.5 pl-5">
                        <li>{{ ctrans('Open Perplexity and go to') }} <span class="font-medium">{{ ctrans('Settings') }}</span>, {{ ctrans('then') }} <span class="font-medium">{{ ctrans('Connectors') }}</span></li>
                        <li>{{ ctrans('Click') }} <span class="font-medium">{{ ctrans('+ Custom connector') }}</span> {{ ctrans('and choose') }} <span class="font-medium">{{ ctrans('Remote') }}</span></li>
                        <li>{{ ctrans('Name: type') }} <span class="font-medium">Aiku</span>. {{ ctrans('Server URL: paste the address above') }}</li>
                        <li>{{ ctrans('For authentication choose') }} <span class="font-medium">{{ ctrans('API Key') }}</span> {{ ctrans('and paste your key') }}</li>
                        <li>{{ ctrans('Accept the confirmation messages and you are done') }}</li>
                    </ol>
                </div>
            </details>

            <details class="rounded-md border border-gray-200">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                    ChatGPT
                </summary>
                <div class="space-y-2 px-4 pb-4 pt-1 text-sm text-gray-600">
                    <p class="text-xs text-amber-600">{{ ctrans('Needs a paid ChatGPT plan. No key needed — you will sign in with your normal Aiku login instead.') }}</p>
                    <ol class="list-decimal space-y-1.5 pl-5">
                        <li>{{ ctrans('Open ChatGPT and click your name (bottom left), then') }} <span class="font-medium">{{ ctrans('Settings') }}</span></li>
                        <li>{{ ctrans('Go to') }} <span class="font-medium">{{ ctrans('Connectors') }}</span>. {{ ctrans('If you do not see a create option, open') }} <span class="font-medium">{{ ctrans('Advanced') }}</span> {{ ctrans('and switch on') }} <span class="font-medium">{{ ctrans('Developer mode') }}</span></li>
                        <li>{{ ctrans('Click') }} <span class="font-medium">{{ ctrans('Create') }}</span>, {{ ctrans('name it') }} <span class="font-medium">Aiku</span> {{ ctrans('and paste the address above') }}</li>
                        <li>{{ ctrans('Choose OAuth as authentication if asked, then click through — a page from Aiku will open') }}</li>
                        <li>{{ ctrans('Sign in with your normal Aiku username and password and click Approve') }}</li>
                        <li>{{ ctrans('In a new chat, enable the Aiku connector and ask your question') }}</li>
                    </ol>
                </div>
            </details>

            <details class="rounded-md border border-gray-200">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                    Google Gemini
                </summary>
                <div class="space-y-2 px-4 pb-4 pt-1 text-sm text-gray-600">
                    <p class="text-xs text-amber-600">{{ ctrans('Only works with a personal Google account — not a work or school account. No key needed.') }}</p>
                    <ol class="list-decimal space-y-1.5 pl-5">
                        <li>{{ ctrans('Open gemini.google.com, click') }} <span class="font-medium">{{ ctrans('Settings & help') }}</span> ({{ ctrans('bottom left') }}), {{ ctrans('then') }} <span class="font-medium">{{ ctrans('Connected apps') }}</span></li>
                        <li>{{ ctrans('Under custom apps, click to add one and paste the address above') }}</li>
                        <li>{{ ctrans('Follow the steps on screen — when an Aiku page opens, sign in with your Aiku username and password and click Approve') }}</li>
                        <li>{{ ctrans('Ask Gemini something like "How many orders did my shop get this week?"') }}</li>
                    </ol>
                </div>
            </details>

            <p class="text-xs text-gray-500">
                {{ ctrans('Your AI can only read Aiku, it can never change or delete anything. It sees exactly what you can see, nothing more.') }}
            </p>
        </div>

            <details class="rounded-md border border-gray-200 mt-6">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                    {{ ctrans('Access keys') }}
                    <span class="font-normal text-gray-400">— {{ ctrans('only if your assistant asks for one, like Perplexity') }}</span>
                </summary>
                <div class="space-y-4 px-4 pb-4 pt-2">
        <p class="text-sm text-gray-500">
            {{ ctrans('A key works like a password: anyone who has it can read your Aiku data. Only create one if your assistant asks for a key instead of letting you sign in.') }}
        </p>

        <form class="flex gap-2" @submit.prevent="onCreateToken">
            <input
                v-model="newTokenName"
                type="text"
                maxlength="64"
                :placeholder="ctrans('Give your key a name, e.g. Perplexity')"
                class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <Button
                nativeType="submit"
                :label="ctrans('Create key')"
                icon="fal fa-key"
                :loading="isCreating"
                :disabled="!newTokenName.trim()"
            />
        </form>

        <div v-if="newPlainTextToken" class="rounded-md border border-amber-300 bg-amber-50 p-4 space-y-2">
            <p class="text-sm font-medium text-amber-800">
                {{ ctrans('Copy your key now and keep it somewhere safe. For security, it will not be shown again.') }}
            </p>
            <div class="flex items-center gap-2">
                <code class="flex-1 break-all rounded bg-white px-2 py-1 text-xs border border-amber-200 select-all">{{ newPlainTextToken }}</code>
                <Button
                    :label="isCopied ? ctrans('Copied!') : ctrans('Copy')"
                    icon="fal fa-copy"
                    type="tertiary"
                    size="xs"
                    @click="onCopyToken"
                />
            </div>
        </div>

        <table v-if="tokens.length" class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-gray-500">
                    <th class="py-2 font-medium">{{ ctrans('Name') }}</th>
                    <th class="py-2 font-medium">{{ ctrans('Created') }}</th>
                    <th class="py-2 font-medium">{{ ctrans('Last used') }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="token in tokens" :key="token.id" class="border-b last:border-0">
                    <td class="py-2 font-medium">
                        <FontAwesomeIcon icon="fal fa-key" class="mr-1.5 text-gray-400" fixed-width />{{ token.name }}
                    </td>
                    <td class="py-2 text-gray-500">{{ useFormatTime(token.created_at) }}</td>
                    <td class="py-2 text-gray-500">{{ token.last_used_at ? useFormatTime(token.last_used_at) : ctrans('Never') }}</td>
                    <td class="py-2 text-right">
                        <Button
                            :label="ctrans('Revoke')"
                            icon="fal fa-trash-alt"
                            type="negative"
                            size="xs"
                            :loading="deletingId === token.id"
                            @click="onDeleteToken(token)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-else class="text-sm text-gray-400 italic">
            {{ ctrans('No keys yet.') }}
        </div>
                </div>
            </details>
    </div>
</template>