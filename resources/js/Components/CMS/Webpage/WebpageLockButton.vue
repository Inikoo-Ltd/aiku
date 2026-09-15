<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLock, faLockOpen } from '@fal'
import WebpageEditAccessRequestButton from '@/Components/CMS/Webpage/WebpageEditAccessRequestButton.vue'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import type { WebpageLock, WebpageLockGrant } from '@/Components/CMS/Webpage/WebpageLockBanner.vue'

const props = defineProps<{
    lock: WebpageLock
}>()

const isLockModalOpen = ref(false)
const isUnlockModalOpen = ref(false)

type GrantMode = 'until_publish' | 'one_hour' | 'until_date'
const grantMode = ref<GrantMode>(props.lock.editors[0]?.until_publish ? 'until_publish' : props.lock.editors[0]?.until ? 'until_date' : 'until_publish')
const grantUntil = ref<string>(props.lock.editors[0]?.until?.slice(0, 16) ?? '')

const lockForm = useForm({
    reason: props.lock.reason ?? '',
    note: props.lock.note ?? '',
    editor_ids: props.lock.editors.map(grant => grant.user_id) as number[],
})
const unlockForm = useForm({ reason: '' })
const buildEditors = (): WebpageLockGrant[] => {
    const until = grantMode.value === 'one_hour'
        ? new Date(Date.now() + 3600 * 1000).toISOString()
        : grantMode.value === 'until_date' && grantUntil.value ? new Date(grantUntil.value).toISOString() : null
    return lockForm.editor_ids.map(user_id => ({ user_id, until, until_publish: grantMode.value === 'until_publish' }))
}

const submitLock = () => {
    isLockModalOpen.value = false
    lockForm.transform(data => ({ reason: data.reason, note: data.note, editors: buildEditors() }))
        .post(route(props.lock.lock_route.name, props.lock.lock_route.parameters), {
            preserveScroll: true,
            onError: () => isLockModalOpen.value = true,
        })
}

const onProtectedPageClick = () => {
    if (props.lock.can_edit_lock) {
        isLockModalOpen.value = true
    } else if (props.lock.can_manage) {
        isUnlockModalOpen.value = true
    }
}

const openUnlockModal = () => {
    isLockModalOpen.value = false
    isUnlockModalOpen.value = true
}

const submitUnlock = () => {
    isUnlockModalOpen.value = false
    unlockForm.post(route(props.lock.unlock_route.name, props.lock.unlock_route.parameters), {
        preserveScroll: true,
        onError: () => isUnlockModalOpen.value = true,
    })
}
</script>

<template>
<div v-if="lock.is_locked || lock.can_manage" class="flex items-center gap-2">
    <Button
        v-if="lock.is_locked"
        type="warning"
        :icon="faLock"
        :label="trans('PROTECTED PAGE')"
        v-tooltip="lock.can_edit_lock ? trans('Edit lock') : lock.can_manage ? trans('Unlock page') : lock.message"
        :class="lock.can_edit_lock || lock.can_manage ? '' : 'cursor-default'"
        @click="onProtectedPageClick"
    />
    <Button
        v-else
        type="tertiary"
        :icon="faLockOpen"
        :label="trans('Protect page')"
        @click="isLockModalOpen = true"
    />
    <WebpageEditAccessRequestButton :lock="lock" />

    <Modal :isOpen="isLockModalOpen" @onClose="isLockModalOpen = false" width="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="submitLock">
            <div class="text-lg font-semibold">
                <FontAwesomeIcon :icon="faLock" fixed-width aria-hidden="true" /> {{ lock.is_locked ? trans('Edit lock') : trans('Protect page') }}
            </div>
            <label class="block text-sm">
                {{ trans('Reason') }} <span class="text-red-500">*</span>
                <input v-model="lockForm.reason" required maxlength="255" class="mt-1 w-full rounded border-gray-300 text-sm" />
                <span v-if="lockForm.errors.reason" class="text-xs text-red-500">{{ lockForm.errors.reason }}</span>
            </label>
            <label class="block text-sm">
                {{ trans('Note') }}
                <textarea v-model="lockForm.note" rows="2" class="mt-1 w-full rounded border-gray-300 text-sm" />
            </label>
            <div class="text-sm">
                {{ trans('Who is permitted to edit') }}
                <PureMultiselect v-model="lockForm.editor_ids" :options="lock.users" mode="tags" label="label" valueProp="value" searchable :placeholder="trans('Select users')" />
            </div>
            <div v-if="lockForm.editor_ids.length" class="space-y-1 text-sm">
                <div>{{ trans('Permission expires') }}</div>
                <label class="flex items-center gap-2"><input type="radio" value="until_publish" v-model="grantMode" /> {{ trans('After next publish') }}</label>
                <label class="flex items-center gap-2"><input type="radio" value="one_hour" v-model="grantMode" /> {{ trans('In 1 hour') }}</label>
                <label class="flex items-center gap-2">
                    <input type="radio" value="until_date" v-model="grantMode" /> {{ trans('At a chosen date and time') }}
                    <input v-if="grantMode === 'until_date'" type="datetime-local" v-model="grantUntil" required class="rounded border-gray-300 text-sm" />
                </label>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <Button v-if="lock.is_locked" type="negative" :icon="faLockOpen" :label="trans('Unlock')" @click="openUnlockModal" />
                <div class="ml-auto flex gap-2">
                    <Button type="tertiary" :label="trans('Cancel')" @click="isLockModalOpen = false" />
                    <Button :label="lock.is_locked ? trans('Save') : trans('Protect page')" :loading="lockForm.processing" @click="submitLock" />
                </div>
            </div>
        </form>
    </Modal>

    <Modal :isOpen="isUnlockModalOpen" @onClose="isUnlockModalOpen = false" width="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="submitUnlock">
            <div class="text-lg font-semibold">
                <FontAwesomeIcon :icon="faLockOpen" fixed-width aria-hidden="true" /> {{ trans('Unlock page') }}
            </div>
            <label class="block text-sm">
                {{ trans('Reason') }} <span v-if="!lock.is_owner" class="text-red-500">*</span>
                <input v-model="unlockForm.reason" :required="!lock.is_owner" maxlength="255" class="mt-1 w-full rounded border-gray-300 text-sm" />
                <span v-if="unlockForm.errors.reason" class="text-xs text-red-500">{{ unlockForm.errors.reason }}</span>
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="isUnlockModalOpen = false" />
                <Button type="negative" :label="trans('Unlock')" :loading="unlockForm.processing" @click="submitUnlock" />
            </div>
        </form>
    </Modal>
</div>
</template>
