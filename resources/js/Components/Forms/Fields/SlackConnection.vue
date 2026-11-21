<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import Button from '@/Components/Elements/Buttons/Button.vue'
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

const connect = () => {
    isLeaving.value = true
    window.location.href = connection.value.connect_url
}

const disconnect = () => {
    router.delete(route(connection.value.disconnect_route.name), {
        preserveScroll: true,
        onStart: () => isDisconnecting.value = true,
        onFinish: () => isDisconnecting.value = false,
    })
}
</script>

<template>
    <div class="space-y-3 text-sm">
        <p v-if="connection.error" class="text-red-600" role="alert">{{ connection.error }}</p>
        <p v-else-if="connection.notice" class="text-green-700" role="status">{{ connection.notice }}</p>

        <div v-if="connection.state === 'connected'" class="flex flex-wrap items-center justify-between gap-3">
            <div>
                {{ ctrans('Connected as') }} <span class="font-medium">@{{ connection.slack_name }}</span>
                <span v-if="connection.connected_at" class="text-gray-500">&nbsp;{{ ctrans('since') }} {{ useFormatTime(connection.connected_at) }}</span>
            </div>
            <Button type="tertiary" :label="ctrans('Disconnect')" :loading="isDisconnecting" :disabled="isDisconnecting" @click.prevent="disconnect">
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

        <div class="text-gray-600 space-y-1">
            <p v-if="connection.state === 'connected'">{{ ctrans('What you write in aiku appears in Slack as you. aiku can, as you:') }}</p>
            <p v-else>{{ ctrans('Connect your Slack account so that what you write in aiku appears in Slack as you. Slack will ask you to confirm. aiku will then be able to, as you:') }}</p>
            <ul class="list-disc pl-5">
                <li>{{ ctrans('post, edit and delete your messages') }}</li>
                <li>{{ ctrans('add and remove your reactions') }}</li>
            </ul>
            <p>{{ ctrans('aiku uses this only in public channels. It does not get permission to read your direct messages or private channels, nor to create, rename or archive channels. When a new feature needs more, you will be asked to connect again. You can disconnect at any time.') }}</p>
        </div>

        <template v-if="connection.state !== 'connected'">
            <p v-if="connection.unavailable_reason" class="text-gray-500">{{ connection.unavailable_reason }}</p>
            <Button v-else type="tertiary" :label="connection.state === 'reconnect' ? ctrans('Reconnect') : ctrans('Connect')" :loading="isLeaving" :disabled="isLeaving" @click.prevent="connect">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlug" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </div>
</template>
