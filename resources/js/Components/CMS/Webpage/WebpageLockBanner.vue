<script lang="ts">
import { routeType } from '@/types/route'

export interface WebpageLockGrant { user_id: number, name?: string, until?: string | null, until_publish: boolean }

export interface WebpageEditAccessRequest { user_id: number, name?: string, note?: string | null, requested_at: string }

export interface WebpageLock {
    is_locked: boolean
    owner?: string
    is_owner: boolean
    locked_at?: string
    reason?: string
    note?: string
    editors: WebpageLockGrant[]
    can_edit: boolean
    can_manage: boolean
    message?: string
    users: { value: number, label: string }[]
    requests: WebpageEditAccessRequest[]
    has_requested_access: boolean
    lock_route: routeType
    unlock_route: routeType
    request_access_route: routeType
    approve_access_route: routeType
    decline_access_route: routeType
}
</script>

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLock } from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'
import WebpageEditAccessRequests from '@/Components/CMS/Webpage/WebpageEditAccessRequests.vue'
import WebpageEditAccessRequestButton from '@/Components/CMS/Webpage/WebpageEditAccessRequestButton.vue'

defineProps<{
    lock: WebpageLock
}>()

const grantLabel = (grant: WebpageLockGrant) => grant.until_publish
    ? trans('until next publish')
    : grant.until ? trans('until') + ' ' + useFormatTime(grant.until, { formatTime: 'short-datetime' }) : trans('no expiry')
</script>

<template>
<div v-if="lock.is_locked" class="mx-4 mt-3 rounded-md border border-amber-400 bg-amber-50 px-4 py-3 text-amber-900">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <div class="flex items-center gap-2 font-semibold">
            <FontAwesomeIcon :icon="faLock" fixed-width aria-hidden="true" />
            {{ trans('PROTECTED PAGE — DO NOT EDIT WITHOUT AUTHORISATION') }}
        </div>
        <div class="text-sm">
            <span>{{ trans('Owner') }}: <span class="font-semibold">{{ lock.owner }}</span></span>
            <span class="mx-2">·</span>
            <span>{{ trans('Reason') }}: {{ lock.reason }}</span>
            <span class="mx-2">·</span>
            <span>{{ trans('Locked') }}: {{ useFormatTime(lock.locked_at) }}</span>
        </div>
        <WebpageEditAccessRequestButton :lock="lock" size="xs" class="ml-auto" />
    </div>
    <div v-if="lock.note" class="mt-1 text-sm">{{ lock.note }}</div>
    <div v-if="lock.editors.length" class="mt-1 text-sm">
        {{ trans('Allowed editors') }}:
        <span v-for="grant in lock.editors" :key="grant.user_id" class="mr-2">{{ grant.name }} ({{ grantLabel(grant) }})</span>
    </div>
    <div v-if="!lock.can_edit" class="mt-1 text-sm font-semibold">{{ lock.message }}</div>
    <WebpageEditAccessRequests v-if="lock.can_manage && lock.requests?.length" :lock="lock" />
</div>
</template>
