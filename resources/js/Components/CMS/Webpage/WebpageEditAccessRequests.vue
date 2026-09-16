<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faKey } from '@fal'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import type { WebpageLock, WebpageEditAccessRequest } from '@/Components/CMS/Webpage/WebpageLockBanner.vue'

const props = defineProps<{
    lock: WebpageLock
}>()

type GrantMode = 'until_publish' | 'one_hour' | 'until_date'

const approvingRequest = ref<WebpageEditAccessRequest | null>(null)
const grantMode = ref<GrantMode>('until_publish')
const grantUntil = ref('')

const approveForm = useForm({
    user_id: 0,
    mode: 'until_publish' as GrantMode,
    until: null as string | null,
})

const openApproveModal = (accessRequest: WebpageEditAccessRequest) => {
    approveForm.clearErrors()
    grantMode.value = 'until_publish'
    grantUntil.value = ''
    approvingRequest.value = accessRequest
}

const submitApprove = () => {
    if (!approvingRequest.value) {
        return
    }

    approveForm
        .transform(() => ({
            user_id: approvingRequest.value?.user_id,
            mode: grantMode.value,
            until: grantMode.value === 'until_date' && grantUntil.value ? new Date(grantUntil.value).toISOString() : null,
        }))
        .post(route(props.lock.approve_access_route.name, props.lock.approve_access_route.parameters), {
            preserveScroll: true,
            onSuccess: () => approvingRequest.value = null,
        })
}

const decliningRequest = ref<WebpageEditAccessRequest | null>(null)
const declineForm = useForm({ message: '' })

const openDeclineModal = (accessRequest: WebpageEditAccessRequest) => {
    declineForm.reset()
    declineForm.clearErrors()
    decliningRequest.value = accessRequest
}

const submitDecline = () => {
    if (!decliningRequest.value) {
        return
    }

    declineForm
        .transform(data => ({ user_id: decliningRequest.value?.user_id, message: data.message }))
        .post(route(props.lock.decline_access_route.name, props.lock.decline_access_route.parameters), {
            preserveScroll: true,
            onSuccess: () => decliningRequest.value = null,
        })
}
</script>

<template>
<div class="mt-2 border-t border-amber-300 pt-2">
    <div class="flex items-center gap-2 text-sm font-semibold">
        <FontAwesomeIcon :icon="faKey" fixed-width aria-hidden="true" />
        {{ trans('Edit access requests') }}
    </div>
    <ul class="mt-1 space-y-1">
        <li v-for="accessRequest in lock.requests" :key="accessRequest.user_id" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span class="font-semibold">{{ accessRequest.name }}</span>
            <span class="text-amber-800">{{ useFormatTime(accessRequest.requested_at, { formatTime: 'short-datetime' }) }}</span>
            <span v-if="accessRequest.note" class="italic">“{{ accessRequest.note }}”</span>
            <div class="ml-auto flex gap-2">
                <Button type="positive" size="xs" :label="trans('Allow editing')" @click="openApproveModal(accessRequest)" />
                <Button type="negative" size="xs" :label="trans('Decline')" @click="openDeclineModal(accessRequest)" />
            </div>
        </li>
    </ul>

    <Modal :isOpen="!!approvingRequest" @onClose="approvingRequest = null" width="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="submitApprove">
            <div class="text-lg font-semibold">
                <FontAwesomeIcon :icon="faKey" fixed-width aria-hidden="true" />
                {{ trans('Allow editing for :name', { name: approvingRequest?.name ?? '' }) }}
            </div>
            <div v-if="approvingRequest?.note" class="text-sm italic text-gray-600">“{{ approvingRequest.note }}”</div>
            <div class="space-y-1 text-sm">
                <label class="flex items-center gap-2"><input type="radio" value="until_publish" v-model="grantMode" /> {{ trans('Once, until next publish') }}</label>
                <label class="flex items-center gap-2"><input type="radio" value="one_hour" v-model="grantMode" /> {{ trans('For 1 hour') }}</label>
                <label class="flex items-center gap-2">
                    <input type="radio" value="until_date" v-model="grantMode" /> {{ trans('Until a chosen date and time') }}
                    <input v-if="grantMode === 'until_date'" type="datetime-local" v-model="grantUntil" required class="rounded border-gray-300 text-sm" />
                </label>
                <span v-if="approveForm.errors.until" class="text-xs text-red-500">{{ approveForm.errors.until }}</span>
            </div>
            <div class="text-xs text-gray-500">{{ trans('The page locks again automatically once the permitted edit is published or the permission expires.') }}</div>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="approvingRequest = null" />
                <Button type="positive" :label="trans('Allow editing')" :loading="approveForm.processing" @click="submitApprove" />
            </div>
        </form>
    </Modal>

    <Modal :isOpen="!!decliningRequest" @onClose="decliningRequest = null" width="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="submitDecline">
            <div class="text-lg font-semibold">
                <FontAwesomeIcon :icon="faKey" fixed-width aria-hidden="true" />
                {{ trans('Decline edit access for :name', { name: decliningRequest?.name ?? '' }) }}
            </div>
            <div v-if="decliningRequest?.note" class="text-sm italic text-gray-600">“{{ decliningRequest.note }}”</div>
            <label class="block text-sm">
                {{ trans('Message to :name', { name: decliningRequest?.name ?? '' }) }} ({{ trans('optional') }})
                <textarea v-model="declineForm.message" rows="3" maxlength="1000" class="mt-1 w-full rounded border-gray-300 text-sm" :placeholder="trans('Tell them why, or what to do instead')" />
                <span v-if="declineForm.errors.message" class="text-xs text-red-500">{{ declineForm.errors.message }}</span>
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="decliningRequest = null" />
                <Button type="negative" :label="trans('Decline')" :loading="declineForm.processing" @click="submitDecline" />
            </div>
        </form>
    </Modal>
</div>
</template>
