<script lang="ts">
import { routeType } from '@/types/route'

export interface WebpageLockGrant { user_id: number, name?: string, until?: string | null, until_publish: boolean }

export interface WebpageEditAccessRequest { user_id: number, name?: string, note?: string | null, requested_at: string }

export interface WebpageLock {
    is_locked: boolean
    scope?: 'webpage' | 'master_family' | null
    owner?: string
    is_owner: boolean
    locked_at?: string
    reason?: string
    note?: string
    editors: WebpageLockGrant[]
    can_edit: boolean
    can_manage: boolean
    can_edit_lock: boolean
    message?: string
    users: { value: number, label: string }[]
    requests: WebpageEditAccessRequest[]
    has_requested_access: boolean
    declined_request?: { declined_at: string, declined_by?: string | null, message?: string | null } | null
    lock_route: routeType
    unlock_route: routeType
    request_access_route: routeType
    approve_access_route: routeType
    decline_access_route: routeType
}
</script>

<script setup lang="ts">
import { ref } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLock, faTimes, faTimesCircle } from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'
import WebpageEditAccessRequests from '@/Components/CMS/Webpage/WebpageEditAccessRequests.vue'
import WebpageEditAccessRequestButton from '@/Components/CMS/Webpage/WebpageEditAccessRequestButton.vue'

defineProps<{
    lock: WebpageLock
}>()

const isDismissed = ref(false)

const grantLabel = (grant: WebpageLockGrant) => grant.until_publish
    ? ctrans('until next publish')
    : grant.until ? ctrans('until') + ' ' + useFormatTime(grant.until, { formatTime: 'short-datetime' }) : ctrans('no expiry')
</script>

<template>
<div v-if="lock.is_locked && !isDismissed" class="mx-4 mt-3 rounded-md border border-amber-400 bg-amber-50 px-4 py-3 text-amber-900">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <div class="flex items-center gap-2 font-semibold">
            <FontAwesomeIcon :icon="faLock" fixed-width aria-hidden="true" />
            {{ ctrans('PROTECTED PAGE — DO NOT EDIT WITHOUT AUTHORISATION') }}
        </div>
        <div class="text-sm">
            <span>{{ ctrans('Owner') }}: <span class="font-semibold">{{ lock.owner }}</span></span>
            <span class="mx-2">·</span>
            <span>{{ ctrans('Reason') }}: {{ lock.reason }}</span>
            <span class="mx-2">·</span>
            <span>{{ ctrans('Locked') }}: {{ useFormatTime(lock.locked_at) }}</span>
            <template v-if="lock.scope === 'master_family'">
                <span class="mx-2">·</span>
                <span>{{ ctrans('Family lock across selected websites') }}</span>
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <WebpageEditAccessRequestButton :lock="lock" size="xs" />
            <button
                type="button"
                class="rounded p-1 text-amber-700 hover:bg-amber-100 hover:text-amber-900"
                :title="ctrans('Close')"
                :aria-label="ctrans('Close')"
                @click="isDismissed = true"
            >
                <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
            </button>
        </div>
    </div>
    <div v-if="lock.note" class="mt-1 text-sm">{{ lock.note }}</div>
    <div v-if="lock.editors.length" class="mt-1 text-sm">
        {{ ctrans('Allowed editors') }}:
        <span v-for="grant in lock.editors" :key="grant.user_id" class="mr-2">{{ grant.name }} ({{ grantLabel(grant) }})</span>
    </div>
    <div v-if="!lock.can_edit" class="mt-1 text-sm font-semibold">{{ lock.message }}</div>
    <div v-if="lock.declined_request && !lock.has_requested_access && !lock.can_edit" class="mt-2 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
        <FontAwesomeIcon :icon="faTimesCircle" fixed-width aria-hidden="true" />
        {{ ctrans('Your edit access request was declined by :name on :date.', { name: lock.declined_request.declined_by ?? '', date: useFormatTime(lock.declined_request.declined_at) }) }}
        <div v-if="lock.declined_request.message" class="mt-1 italic">“{{ lock.declined_request.message }}”</div>
    </div>
    <WebpageEditAccessRequests v-if="lock.can_manage && lock.requests?.length" :lock="lock" />
</div>
</template>
