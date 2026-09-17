<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEnvelope, faCheckCircle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

const props = defineProps<{
    fieldData: {
        value: {
            connected: boolean
            email: string | null
            connected_at: string | null
            connect_url: string
            disconnect_route: { name: string; parameters: any }
        }
    }
}>()

const isDisconnecting = ref(false)

const disconnect = () => {
    if (!confirm(trans("Disconnect this mailbox? Emails will stop arriving in the CRM."))) {
        return
    }
    router.post(route(props.fieldData.value.disconnect_route.name, props.fieldData.value.disconnect_route.parameters), {}, {
        onStart: () => (isDisconnecting.value = true),
        onFinish: () => (isDisconnecting.value = false),
    })
}
</script>

<template>
    <div class="w-full max-w-2xl rounded-md border border-gray-200 bg-white p-4">
        <template v-if="fieldData.value.connected">
            <div class="flex items-start gap-3">
                <FontAwesomeIcon :icon="faCheckCircle" class="mt-0.5 text-green-500" fixed-width />
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-700 truncate">{{ fieldData.value.email }}</div>
                    <div class="text-xs text-gray-500">
                        {{ trans("Connected") }}<template v-if="fieldData.value.connected_at"> · {{ useFormatTime(fieldData.value.connected_at) }}</template>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        {{ trans("Customer emails arriving in this mailbox appear as conversations in the CRM inbox. Replies written there are sent from this address.") }}
                    </p>
                </div>
                <Button :label="trans('Disconnect')" type="negative" size="xs" :loading="isDisconnecting" @click="disconnect" />
            </div>
        </template>

        <template v-else>
            <div class="flex items-start gap-3">
                <FontAwesomeIcon :icon="faEnvelope" class="mt-0.5 text-gray-400" fixed-width />
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-700">{{ trans("No mailbox connected") }}</div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ trans("Sign in with the address customers write to, for example info@ or care@ of this shop, not with your own account. The account you pick is the inbox that gets connected.") }}
                    </p>
                    <a :href="fieldData.value.connect_url" class="mt-3 inline-block">
                        <Button :label="trans('Connect Google mailbox')" :icon="faEnvelope" type="primary" size="xs" />
                    </a>
                </div>
            </div>
        </template>
    </div>
</template>
