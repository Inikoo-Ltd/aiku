<script setup lang='ts'>
import { ref } from 'vue'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
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
            title: trans('Something went wrong.'),
            text: trans('Failed to create token.'),
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
            title: trans('Something went wrong.'),
            text: trans('Failed to revoke token.'),
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
        <div>
            <h2 class="text-lg font-semibold">{{ trans('AI access keys') }}</h2>
            <p class="text-sm text-gray-500">
                {{ trans('A key lets your AI assistant (like Claude) look things up in Aiku for you. It sees only what you can see, and works like a password — keep it to yourself.') }}
            </p>
        </div>

        <form class="flex gap-2" @submit.prevent="onCreateToken">
            <input
                v-model="newTokenName"
                type="text"
                maxlength="64"
                :placeholder="trans('Give your key a name, e.g. Claude')"
                class="flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <Button
                type="submit"
                :label="trans('Create key')"
                icon="fal fa-key"
                :loading="isCreating"
                :disabled="!newTokenName.trim()"
            />
        </form>

        <div v-if="newPlainTextToken" class="rounded-md border border-amber-300 bg-amber-50 p-4 space-y-2">
            <p class="text-sm font-medium text-amber-800">
                {{ trans('Copy your key now and keep it somewhere safe. For security, it will not be shown again.') }}
            </p>
            <div class="flex items-center gap-2">
                <code class="flex-1 break-all rounded bg-white px-2 py-1 text-xs border border-amber-200 select-all">{{ newPlainTextToken }}</code>
                <Button
                    :label="isCopied ? trans('Copied!') : trans('Copy')"
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
                    <th class="py-2 font-medium">{{ trans('Name') }}</th>
                    <th class="py-2 font-medium">{{ trans('Created') }}</th>
                    <th class="py-2 font-medium">{{ trans('Last used') }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="token in tokens" :key="token.id" class="border-b last:border-0">
                    <td class="py-2 font-medium">
                        <FontAwesomeIcon icon="fal fa-key" class="mr-1.5 text-gray-400" fixed-width />{{ token.name }}
                    </td>
                    <td class="py-2 text-gray-500">{{ useFormatTime(token.created_at) }}</td>
                    <td class="py-2 text-gray-500">{{ token.last_used_at ? useFormatTime(token.last_used_at) : trans('Never') }}</td>
                    <td class="py-2 text-right">
                        <Button
                            :label="trans('Revoke')"
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
            {{ trans('No tokens yet.') }}
        </div>

        <div class="space-y-3 pt-4 border-t">
            <h3 class="text-base font-semibold">{{ trans('Connect Aiku to your AI assistant') }}</h3>
            <p class="text-sm text-gray-600">
                {{ trans('This lets your AI assistant answer questions using Aiku data, for example "What were the sales in my shop last month?". It can only see what you can see in Aiku, and it can never change anything.') }}
            </p>
            <p class="text-sm text-gray-600">
                {{ trans('You need two things: the address below, and a key (create one above with the button — it looks like a long random text).') }}
            </p>
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">{{ trans('Address') }}:</span>
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs select-all">{{ mcpUrl }}</code>
                <Button
                    :label="copiedSnippet === 'url' ? trans('Copied!') : trans('Copy')"
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
                    <li>{{ trans('Open claude.ai and click your initials (bottom left), then') }} <span class="font-medium">{{ trans('Settings') }}</span></li>
                    <li>{{ trans('Click') }} <span class="font-medium">{{ trans('Connectors') }}</span>, {{ trans('then') }} <span class="font-medium">{{ trans('Add custom connector') }}</span></li>
                    <li>{{ trans('Name: type') }} <span class="font-medium">Aiku</span>. {{ trans('URL: paste the address above') }}</li>
                    <li>{{ trans('Where it asks for authentication, paste your key') }}</li>
                    <li>{{ trans('Start a new chat and ask something, for example: "How many orders did my shop get this week?"') }}</li>
                </ol>
            </details>

            <details class="rounded-md border border-gray-200">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                    Perplexity
                </summary>
                <div class="space-y-2 px-4 pb-4 pt-1 text-sm text-gray-600">
                    <p class="text-xs text-amber-600">{{ trans('Only works on paid Perplexity plans.') }}</p>
                    <ol class="list-decimal space-y-1.5 pl-5">
                        <li>{{ trans('Open Perplexity and go to') }} <span class="font-medium">{{ trans('Settings') }}</span>, {{ trans('then') }} <span class="font-medium">{{ trans('Connectors') }}</span></li>
                        <li>{{ trans('Click') }} <span class="font-medium">{{ trans('+ Custom connector') }}</span> {{ trans('and choose') }} <span class="font-medium">{{ trans('Remote') }}</span></li>
                        <li>{{ trans('Name: type') }} <span class="font-medium">Aiku</span>. {{ trans('Server URL: paste the address above') }}</li>
                        <li>{{ trans('For authentication choose') }} <span class="font-medium">{{ trans('API Key') }}</span> {{ trans('and paste your key') }}</li>
                        <li>{{ trans('Accept the confirmation messages and you are done') }}</li>
                    </ol>
                </div>
            </details>

            <details class="rounded-md border border-gray-200">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-sm font-medium hover:bg-gray-50">
                    ChatGPT {{ trans('and') }} Google Gemini
                </summary>
                <div class="px-4 pb-4 pt-1 text-sm text-gray-600">
                    {{ trans('ChatGPT and Google Gemini do not work with Aiku yet. Please use Claude or Perplexity for now.') }}
                </div>
            </details>

            <p class="text-xs text-gray-500">
                {{ trans('Your key works like a password: anyone who has it can read your Aiku data. Do not share it or send it by email. If you think someone else has it, click Revoke next to it above and create a new one.') }}
            </p>
        </div>
    </div>
</template>
