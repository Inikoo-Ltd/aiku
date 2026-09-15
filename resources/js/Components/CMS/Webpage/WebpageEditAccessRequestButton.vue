<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faKey } from '@fal'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import type { WebpageLock } from '@/Components/CMS/Webpage/WebpageLockBanner.vue'

const props = withDefaults(defineProps<{
    lock: WebpageLock
    size?: string
}>(), {
    size: undefined,
})

const isRequestModalOpen = ref(false)
const requestForm = useForm({ note: '' })
const canRequestAccess = computed(() => props.lock.is_locked && !props.lock.can_edit && !props.lock.can_manage)

const submitRequest = () => {
    requestForm.post(route(props.lock.request_access_route.name, props.lock.request_access_route.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            isRequestModalOpen.value = false
            requestForm.reset()
        },
    })
}
</script>

<template>
<div v-if="canRequestAccess">
    <Button
        type="secondary"
        :size="size"
        :icon="faKey"
        :label="lock.has_requested_access ? trans('Edit access requested') : trans('Request Edit Access')"
        :disabled="lock.has_requested_access"
        @click="isRequestModalOpen = true"
    />

    <Modal :isOpen="isRequestModalOpen" @onClose="isRequestModalOpen = false" width="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="submitRequest">
            <div class="text-lg font-semibold">
                <FontAwesomeIcon :icon="faKey" fixed-width aria-hidden="true" /> {{ trans('Request Edit Access') }}
            </div>
            <div class="text-sm text-gray-600">{{ lock.message }}</div>
            <label class="block text-sm">
                {{ trans('What do you need to change?') }}
                <textarea v-model="requestForm.note" rows="3" maxlength="1000" class="mt-1 w-full rounded border-gray-300 text-sm" />
                <span v-if="requestForm.errors.note" class="text-xs text-red-500">{{ requestForm.errors.note }}</span>
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="isRequestModalOpen = false" />
                <Button :label="trans('Send request')" :loading="requestForm.processing" @click="submitRequest" />
            </div>
        </form>
    </Modal>
</div>
</template>
