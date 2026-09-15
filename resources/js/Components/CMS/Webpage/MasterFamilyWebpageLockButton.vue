<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLock, faLockOpen } from '@fal'
import Modal from '@/Components/Utils/Modal.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'

interface FamilyWebpageLock {
    id: number
    code: string
    shop_code: string
    shop_name: string
    website_domain?: string | null
    is_locked: boolean
    locked_by?: string | null
    lock_scope?: string | null
    can_manage: boolean
}

type Mode = 'lock' | 'unlock'

const props = defineProps<{
    locks: {
        webpages: FamilyWebpageLock[]
        lock_route: routeType
        unlock_route: routeType
    }
}>()

const mode = ref<Mode | null>(null)

const form = useForm({
    webpage_ids: [] as number[],
    reason: '',
    note: '',
})

const hasWebpages = computed(() => props.locks.webpages.length > 0)
const lockedWebpages = computed(() => props.locks.webpages.filter(webpage => webpage.is_locked))
const listedWebpages = computed(() => mode.value === 'unlock' ? lockedWebpages.value : props.locks.webpages)

const openModal = (selectedMode: Mode) => {
    form.reset()
    form.clearErrors()
    mode.value = selectedMode
}

const submit = () => {
    const targetRoute = mode.value === 'unlock' ? props.locks.unlock_route : props.locks.lock_route

    form
        .transform(data => mode.value === 'unlock' ? { webpage_ids: data.webpage_ids, reason: data.reason } : data)
        .post(route(targetRoute.name, targetRoute.parameters), {
            preserveScroll: true,
            onSuccess: () => {
                mode.value = null
                form.reset()
            },
        })
}
</script>

<template>
<div class="flex items-center gap-2">
    <Button
        type="tertiary"
        :icon="faLock"
        :label="trans('Lock family webpages')"
        :disabled="!hasWebpages"
        v-tooltip="hasWebpages ? trans('Lock this family webpage on the websites you choose') : trans('No webpages for this family yet')"
        @click="openModal('lock')"
    />
    <Button
        v-if="lockedWebpages.length"
        type="tertiary"
        :icon="faLockOpen"
        :label="trans('Unlock family webpages')"
        @click="openModal('unlock')"
    />

    <Modal :isOpen="mode !== null" @onClose="mode = null" width="w-full max-w-2xl">
        <form class="space-y-3" @submit.prevent="submit">
            <div class="text-lg font-semibold">
                <FontAwesomeIcon :icon="mode === 'unlock' ? faLockOpen : faLock" fixed-width aria-hidden="true" />
                {{ mode === 'unlock' ? trans('Unlock this family on selected websites') : trans('Lock this family across selected websites') }}
            </div>
            <div class="text-sm text-gray-600">
                {{ mode === 'unlock'
                    ? trans('Only the websites you tick are unlocked. Pages you leave unticked stay locked.')
                    : trans('Only the websites you tick are locked. Pages you leave unticked stay editable, for example translations that still need work.') }}
            </div>

            <div class="max-h-72 divide-y divide-gray-100 overflow-y-auto rounded border border-gray-200">
                <label
                    v-for="webpage in listedWebpages"
                    :key="webpage.id"
                    class="flex items-center gap-3 px-3 py-2 text-sm"
                    :class="webpage.can_manage ? 'cursor-pointer hover:bg-gray-50' : 'cursor-not-allowed opacity-60'"
                >
                    <input
                        type="checkbox"
                        :value="webpage.id"
                        v-model="form.webpage_ids"
                        :disabled="!webpage.can_manage"
                        class="rounded border-gray-300"
                    />
                    <span class="w-12 shrink-0 font-semibold">{{ webpage.shop_code }}</span>
                    <span class="min-w-0 flex-1 truncate">
                        {{ webpage.shop_name }}
                        <span v-if="webpage.website_domain" class="text-gray-400">· {{ webpage.website_domain }}</span>
                    </span>
                    <span class="shrink-0 text-gray-500">{{ webpage.code }}</span>
                    <span
                        v-if="webpage.is_locked"
                        class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800"
                        v-tooltip="webpage.can_manage
                            ? (webpage.lock_scope === 'master_family' ? trans('Family lock') : trans('This webpage only'))
                            : trans('Locked by someone else')"
                    >
                        <FontAwesomeIcon :icon="faLock" fixed-width aria-hidden="true" /> {{ webpage.locked_by }}
                    </span>
                </label>
            </div>
            <span v-if="form.errors.webpage_ids" class="text-xs text-red-500">{{ form.errors.webpage_ids }}</span>

            <label class="block text-sm">
                {{ trans('Reason') }} <span class="text-red-500">*</span>
                <input v-model="form.reason" required maxlength="255" class="mt-1 w-full rounded border-gray-300 text-sm" />
                <span v-if="form.errors.reason" class="text-xs text-red-500">{{ form.errors.reason }}</span>
            </label>
            <label v-if="mode === 'lock'" class="block text-sm">
                {{ trans('Note') }}
                <textarea v-model="form.note" rows="2" class="mt-1 w-full rounded border-gray-300 text-sm" />
            </label>

            <div class="flex items-center justify-between gap-2 pt-2">
                <span class="text-xs text-gray-500">{{ trans(':count selected', { count: String(form.webpage_ids.length) }) }}</span>
                <div class="flex gap-2">
                    <Button type="tertiary" :label="trans('Cancel')" @click="mode = null" />
                    <Button
                        :type="mode === 'unlock' ? 'negative' : 'primary'"
                        :label="mode === 'unlock' ? trans('Unlock selected webpages') : trans('Lock selected webpages')"
                        :disabled="!form.webpage_ids.length"
                        :loading="form.processing"
                        @click="submit"
                    />
                </div>
            </div>
        </form>
    </Modal>
</div>
</template>
