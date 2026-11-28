<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ToggleSwitch from 'primevue/toggleswitch'
import SlackPrivateConsentDialog from '@/Components/Forms/Fields/SlackPrivateConsentDialog.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlug, faExclamationTriangle } from '@fal'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

const connection = computed(() => props.fieldData?.connection ?? {})
const isDisconnecting = ref(false)
const isLeaving = ref(false)
const privateSettings = computed(() => connection.value.private ?? null)
const isConsentOpen = ref(false)

const stateLabel = computed(() => {
    if (privateSettings.value?.on) {
        return ctrans('Connected for public channels and private conversations')
    }

    return connection.value.state === 'connected' ? ctrans('Connected for public channels') : ctrans('Not connected')
})

const connect = () => {
    if (privateSettings.value) {
        isConsentOpen.value = true

        return
    }
    isLeaving.value = true
    window.location.href = connection.value.connect_url
}

const disconnect = (teamId: string | null = null) => {
    router.delete(route(connection.value.disconnect_route.name, teamId ? { team_id: teamId } : {}), {
        preserveScroll: true,
        onStart: () => isDisconnecting.value = true,
        onFinish: () => isDisconnecting.value = false,
    })
}

const otherWorkspaces = computed<any[]>(() => connection.value.other_workspaces ?? [])

const connectTo = (url: string) => {
    isLeaving.value = true
    window.location.href = url
}
</script>

<template>
    <div class="space-y-3 text-sm">
        <p v-if="connection.error" class="text-red-600" role="alert">{{ connection.error }}</p>
        <p v-else-if="connection.notice" class="text-green-700" role="status">{{ connection.notice }}</p>

        <p class="font-medium text-gray-700">{{ stateLabel }}</p>

        <div v-if="connection.state === 'connected'" class="flex flex-wrap items-center justify-between gap-3">
            <div>
                {{ ctrans('Connected as') }} <span class="font-medium">@{{ connection.slack_name }}</span>
                <span v-if="connection.connected_at" class="text-gray-500">&nbsp;{{ ctrans('since') }} {{ useFormatTime(connection.connected_at) }}</span>
            </div>
            <Button type="tertiary" :label="ctrans('Disconnect')" :loading="isDisconnecting" :disabled="isDisconnecting" @click.prevent="disconnect()">
                <template #icon>
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>

        <template v-else>
            <p v-if="connection.state === 'reconnect'" class="text-amber-700">
                {{ ctrans('The permission you gave no longer covers what aiku needs, so it has to be given again.') }}
            </p>
        </template>

        <p v-if="privateSettings && connection.state !== 'connected'" class="text-gray-600">
            {{ ctrans('Connect once and your Slack comes to Verba: your channels, direct messages and group chats. What you write in Verba shows in Slack as you.') }}
        </p>

        <div v-else class="text-gray-600 space-y-1">
            <p v-if="connection.state === 'connected'">{{ ctrans('What you write in aiku appears in Slack as you. aiku can, as you:') }}</p>
            <p v-else>{{ ctrans('Connect your Slack account so that what you write in aiku appears in Slack as you. Slack will ask you to confirm. aiku will then be able to, as you:') }}</p>
            <ul class="list-disc pl-5">
                <li>{{ ctrans('post, edit and delete your messages') }}</li>
                <li>{{ ctrans('add and remove your reactions') }}</li>
                <li>{{ ctrans('upload files') }}</li>
                <li>{{ ctrans('create, join, leave and archive public channels') }}</li>
                <li>{{ ctrans('pin messages and add channel bookmarks') }}</li>
                <li>{{ ctrans('schedule messages and set reminders') }}</li>
                <li>{{ ctrans('set your status, presence and do not disturb') }}</li>
            </ul>
            <p v-if="!privateSettings?.on">{{ ctrans('aiku uses this only in public channels. It does not get permission to read your direct messages or private channels. You can disconnect at any time.') }}</p>
        </div>

        <template v-if="connection.state !== 'connected'">
            <p v-if="connection.unavailable_reason" class="text-gray-500">{{ connection.unavailable_reason }}</p>
            <Button v-else type="tertiary" :label="connection.state === 'reconnect' ? ctrans('Reconnect') : ctrans('Connect')" :loading="isLeaving" :disabled="isLeaving" @click.prevent="connect">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlug" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>

        <div v-for="workspace in otherWorkspaces" :key="workspace.team_id" class="border-t border-gray-200 pt-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium text-gray-700">{{ workspace.name }}</p>
                <p v-if="workspace.state === 'connected'" class="text-gray-500">
                    {{ ctrans('Connected as') }} <span class="font-medium">@{{ workspace.slack_name }}</span>
                    <span v-if="workspace.connected_at">&nbsp;{{ ctrans('since') }} {{ useFormatTime(workspace.connected_at) }}</span>
                </p>
                <p v-else-if="workspace.unavailable_reason" class="text-gray-500">{{ workspace.unavailable_reason }}</p>
                <p v-else class="text-gray-500">{{ ctrans('Connect it to react, pin, upload files and add bookmarks in its channels as @:name', { name: workspace.slack_name }) }}</p>
            </div>
            <Button v-if="workspace.state === 'connected'" type="tertiary" :label="ctrans('Disconnect')" :loading="isDisconnecting" :disabled="isDisconnecting" @click.prevent="disconnect(workspace.team_id)">
                <template #icon>
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <Button v-else-if="!workspace.unavailable_reason" type="tertiary" :label="workspace.state === 'reconnect' ? ctrans('Reconnect') : ctrans('Connect')" :loading="isLeaving" :disabled="isLeaving" @click.prevent="connectTo(workspace.connect_url)">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlug" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>

        <div v-if="privateSettings && connection.state === 'connected'" class="border-t border-gray-200 pt-3 space-y-2">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="font-medium text-gray-700">{{ ctrans('Private conversations') }}</p>
                    <p class="text-gray-500">
                        <template v-if="privateSettings.on">
                            {{ ctrans('Your direct messages, group chats and private channels come to Verba') }}<span v-if="privateSettings.consented_at">&nbsp;{{ ctrans('since') }} {{ useFormatTime(privateSettings.consented_at) }}</span>
                        </template>
                        <template v-else>{{ ctrans('Off. Bring your direct messages, group chats and private channels to Verba') }}</template>
                    </p>
                    <p v-if="privateSettings.on && privateSettings.history" class="text-gray-500">
                        <template v-if="privateSettings.history.finished">{{ ctrans('All your earlier direct messages and group chats are in Verba') }}</template>
                        <template v-else>{{ ctrans('Bringing your earlier direct messages and group chats: :done of :total', { done: privateSettings.history.done, total: privateSettings.history.total }) }}</template>
                    </p>
                </div>
                <ToggleSwitch v-if="!privateSettings.on" :model-value="false" :aria-label="ctrans('Private conversations')" @update:model-value="isConsentOpen = true" />
            </div>
        </div>

        <SlackPrivateConsentDialog
            v-if="privateSettings"
            v-model:visible="isConsentOpen"
            :consent-version="privateSettings.consent_version"
            :terms-version="privateSettings.terms_version"
            :start-route="privateSettings.start_route"
            :unavailable-reason="connection.unavailable_reason"
        />

    </div>
</template>
