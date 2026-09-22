<script setup lang='ts'>
import Image from '../../Common/Components/Image.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { Image as ImageTS } from '@/types/Image'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faEnvelope, faCalendarAlt, faAt, faEye, faEyeSlash, faUserClock, faArrowRight, faCamera, faCommentAlt, faPen, faCheck, faTimes, faSignOutAlt } from '@fal'
import { faCheckCircle, faTimesCircle } from '@fas'
import { trans } from 'laravel-vue-i18n'
import { computed, inject, nextTick, onMounted, ref } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { router } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { formatDistanceToNowStrict } from 'date-fns'

library.add(faEnvelope, faCalendarAlt, faAt, faEye, faEyeSlash, faUserClock, faArrowRight, faCamera, faCommentAlt, faPen, faCheck, faTimes, faSignOutAlt, faCheckCircle, faTimesCircle)

interface ProfileData {
    username: string
    avatar?: ImageTS | null
    email: string | null
    status: {
        tooltip: string
        icon: string
        class: string
    }
    contact_name: string | null
    nickname: string | null
    created_at: string | null
}

defineProps<{
    isLoadingLogout?: boolean
}>()

const emits = defineEmits<{
    (e: 'logout'): void
    (e: 'loaded'): void
}>()

const layout = inject('layout', layoutStructure)

const emailVisibilityStorageKey = 'profile_showcase_email_hidden'

const readStoredEmailVisibility = (): boolean => {
    try {
        return localStorage.getItem(emailVisibilityStorageKey) !== '0'
    } catch {
        return true
    }
}

const isEmailHidden = ref(readStoredEmailVisibility())

const profile = ref<ProfileData | null>(null)
const isLoadingProfile = ref(true)

const fetchProfile = async () => {
    const response = await axios.get(route('grp.profile.showcase.show'))
    profile.value = { ...response.data.data }
}

const fallbackAccentColor = '#4f46e5'
const accentColor = computed(() => layout.app?.theme?.[0] ?? fallbackAccentColor)

const accentVariables = computed(() => ({
    '--profile-accent': accentColor.value,
    '--profile-accent-soft': `color-mix(in srgb, ${accentColor.value} 15%, white)`,
}))

const bannerStyle = computed(() => ({
    backgroundImage: `linear-gradient(to right, ${accentColor.value}, color-mix(in srgb, ${accentColor.value} 65%, white))`,
}))

const maxNicknameLength = 24
const maxAvatarSizeInMb = 12

const _avatarInput = ref<HTMLInputElement | null>(null)
const _nicknameInput = ref<HTMLInputElement | null>(null)
const isUploadingAvatar = ref(false)
const isEditingNickname = ref(false)
const isSavingNickname = ref(false)
const nicknameDraft = ref('')
const nicknameError = ref<string | null>(null)
const displayName = computed(() => profile.value?.contact_name || profile.value?.username || '')
const firstName = computed(() => displayName.value.trim().split(/\s+/)[0] ?? '')
const isActive = computed(() => profile.value?.status?.class?.includes('green'))

const initials = computed(() => displayName.value
    .split(/[\s._-]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join(''))

const memberFor = computed(() => profile.value?.created_at
    ? formatDistanceToNowStrict(new Date(profile.value.created_at))
    : null)

const maskedEmail = computed(() => {
    const [localPart, domain] = (profile.value?.email ?? '').split('@')
    if (!domain) {
        return '••••••'
    }

    return `${localPart.charAt(0)}${'•'.repeat(Math.max(3, localPart.length - 1))}@${domain}`
})

const toggleEmailVisibility = () => {
    isEmailHidden.value = !isEmailHidden.value
    try {
        localStorage.setItem(emailVisibilityStorageKey, isEmailHidden.value ? '1' : '0')
    } catch {
        return
    }
}

const firstValidationMessage = (error: any, field: string): string => {
    return error?.response?.data?.errors?.[field]?.[0]
        ?? error?.response?.data?.message
        ?? trans('Something went wrong.')
}

const updateProfile = async (payload: FormData) => {
    payload.append('_method', 'patch')
    await axios.post(route('grp.models.profile.update'), payload)

    await fetchProfile()
    router.reload()
}

const onPickAvatar = async (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) {
        return
    }

    if (file.size > maxAvatarSizeInMb * 1024 * 1024) {
        notify({ title: trans('Image is too large'), text: trans('Maximum size is :size MB', { size: String(maxAvatarSizeInMb) }), type: 'error' })
        return
    }

    isUploadingAvatar.value = true
    try {
        const payload = new FormData()
        payload.append('image', file)
        await updateProfile(payload)
        notify({ title: trans('Profile photo updated'), type: 'success' })
    } catch (error: any) {
        notify({ title: trans('Failed to update profile photo'), text: firstValidationMessage(error, 'image'), type: 'error' })
    } finally {
        isUploadingAvatar.value = false
    }
}

const startEditingNickname = async () => {
    nicknameDraft.value = profile.value?.nickname ?? ''
    nicknameError.value = null
    isEditingNickname.value = true
    await nextTick()
    _nicknameInput.value?.focus()
}

const cancelEditingNickname = () => {
    isEditingNickname.value = false
    nicknameError.value = null
}

const saveNickname = async () => {
    if (isSavingNickname.value) {
        return
    }
    if (nicknameDraft.value.trim() === (profile.value?.nickname ?? '')) {
        cancelEditingNickname()
        return
    }

    isSavingNickname.value = true
    nicknameError.value = null
    try {
        const payload = new FormData()
        payload.append('nickname', nicknameDraft.value.trim())
        await updateProfile(payload)
        isEditingNickname.value = false
    } catch (error: any) {
        nicknameError.value = firstValidationMessage(error, 'nickname')
    } finally {
        isSavingNickname.value = false
    }
}

const openClocking = () => {
    router.visit(route('grp.clocking_employees.index'), {
        onSuccess: () => layout.stackedComponents = [],
    })
}

onMounted(async () => {
    try {
        await fetchProfile()
    } catch {
        notify({ title: trans('Something went wrong.'), text: trans('Failed to load your profile.'), type: 'error' })
    } finally {
        isLoadingProfile.value = false
        await nextTick()
        emits('loaded')
    }
})
</script>

<template>
    <header class="-mt-6 bg-white" :style="accentVariables">
        <div>
            <div class="relative h-28" :style="bannerStyle">
                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_20%_120%,white,transparent_45%),radial-gradient(circle_at_85%_-20%,white,transparent_40%)]" />

                <div class="absolute right-14 top-4 flex flex-row items-center gap-2 sm:right-16">
                    <button type="button" @click="openClocking"
                        v-tooltip="trans('Clocking')" :aria-label="trans('Clocking')"
                        class="group inline-flex h-9 w-9 items-center justify-center gap-x-2 rounded-full bg-white/15 text-sm font-medium text-white ring-1 ring-inset ring-white/30 backdrop-blur-sm transition hover:bg-white hover:text-[color:var(--profile-accent)] sm:h-9 sm:w-32 sm:px-4">
                        <FontAwesomeIcon icon="fal fa-user-clock" fixed-width aria-hidden="true" />
                        <span class="hidden sm:inline">{{ trans('Clocking') }}</span>
                        <FontAwesomeIcon icon="fal fa-arrow-right" class="hidden text-xs transition-transform group-hover:translate-x-0.5 sm:inline-block" fixed-width aria-hidden="true" />
                    </button>

                    <Popover class="relative">
                        <PopoverButton :disabled="isLoadingLogout"
                            v-tooltip="trans('Logout')" :aria-label="trans('Logout')"
                            class="inline-flex h-9 w-9 items-center justify-center gap-x-2 rounded-full bg-red-500/30 text-sm font-medium text-white ring-1 ring-inset ring-red-200/50 backdrop-blur-sm transition hover:bg-white hover:text-red-600 focus:outline-none disabled:opacity-60 sm:h-9 sm:w-32 sm:px-4">
                            <LoadingIcon v-if="isLoadingLogout" />
                            <FontAwesomeIcon v-else icon="fal fa-sign-out-alt" fixed-width aria-hidden="true" />
                            <span class="hidden sm:inline">{{ trans('Logout') }}</span>
                        </PopoverButton>

                        <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <PopoverPanel class="absolute right-0 top-full z-20 mt-2 bg-white rounded-md px-4 py-3 border border-gray-200 shadow">
                                <div class="min-w-32 flex flex-col justify-center gap-y-2">
                                    <div class="whitespace-nowrap text-gray-500 text-xs">{{ trans('Are you sure want to logout?') }}</div>
                                    <div class="mx-auto">
                                        <Button @click="emits('logout')" :loading="isLoadingLogout" :label="trans('Yes, logout')" type="red" :full="true" />
                                    </div>
                                </div>
                            </PopoverPanel>
                        </transition>
                    </Popover>
                </div>
            </div>

            <div v-if="isLoadingProfile" class="animate-pulse px-6 sm:px-8 pb-6" role="status" :aria-label="trans('Loading profile')">
                <div class="-mt-14 flex items-end gap-x-5">
                    <div class="h-28 w-28 shrink-0 rounded-full bg-gray-200 ring-4 ring-white" />
                    <div class="flex-1 space-y-2 pb-2">
                        <div class="h-6 w-64 max-w-full rounded bg-gray-200" />
                        <div class="h-4 w-24 rounded bg-gray-100" />
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div v-for="tile in 3" :key="tile" class="h-16 rounded-xl bg-gray-100" />
                </div>
            </div>

            <div v-else class="relative px-4 sm:px-8 pb-6">
                <div class="pointer-events-none -mt-14 flex items-start gap-x-4 sm:items-end sm:gap-x-5">
                    <button type="button" @click="_avatarInput?.click()" :disabled="isUploadingAvatar"
                        v-tooltip="trans('Change profile photo')"
                        class="pointer-events-auto group relative h-24 w-24 sm:h-28 sm:w-28 shrink-0 rounded-full ring-4 ring-white bg-[color:var(--profile-accent-soft)] overflow-hidden shadow-md flex items-center justify-center focus:outline-none focus-visible:ring-[color:var(--profile-accent)]">
                        <Image v-if="profile?.avatar" :src="profile.avatar" :alt="displayName" imageCover class="h-full w-full object-cover" />
                        <span v-else class="text-3xl font-semibold text-[color:var(--profile-accent)] select-none">{{ initials }}</span>

                        <span class="absolute inset-0 flex flex-col items-center justify-center gap-y-1 bg-black/50 text-white text-xs font-medium transition-opacity"
                            :class="isUploadingAvatar ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100'">
                            <LoadingIcon v-if="isUploadingAvatar" />
                            <template v-else>
                                <FontAwesomeIcon icon="fal fa-camera" class="text-lg" fixed-width aria-hidden="true" />
                                {{ trans('Change') }}
                            </template>
                        </span>
                        <span class="sr-only">{{ trans('Change profile photo') }}</span>
                    </button>
                    <input ref="_avatarInput" type="file" accept="image/*" class="sr-only" @change="onPickAvatar" />

                    <div class="min-w-0 flex-1 pb-1 pt-16 sm:pt-0">
                        <div class="pointer-events-auto w-fit max-w-full">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <h2 class="truncate text-xl font-semibold text-gray-900 sm:text-2xl">
                                    <span class="sm:hidden">{{ firstName }}</span>
                                    <span class="hidden sm:inline">{{ displayName }}</span>
                                </h2>
                                <span class="inline-flex items-center gap-x-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset"
                                    :class="isActive ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'">
                                    <FontAwesomeIcon :icon="isActive ? 'fas fa-check-circle' : 'fas fa-times-circle'" class="text-[10px]" fixed-width aria-hidden="true" />
                                    {{ profile?.status?.tooltip }}
                                </span>
                            </div>
                            <div class="mt-0.5 flex items-center gap-x-1 text-sm text-gray-500">
                                <FontAwesomeIcon icon="fal fa-at" class="text-xs" fixed-width aria-hidden="true" />
                                {{ profile?.username }}
                            </div>
                        </div>
                    </div>
                </div>

                <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div class="flex items-start gap-x-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-inset ring-gray-100">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[color:var(--profile-accent)] ring-1 ring-gray-200">
                            <FontAwesomeIcon icon="fal fa-envelope" fixed-width aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans('Email') }}</dt>
                            <dd class="truncate text-sm font-medium text-gray-900">
                                <span v-if="!profile?.email" class="text-gray-400">-</span>
                                <span v-else-if="isEmailHidden" class="tracking-wide text-gray-500">{{ maskedEmail }}</span>
                                <a v-else :href="`mailto:${profile.email}`" class="hover:underline">{{ profile.email }}</a>
                            </dd>
                        </div>
                        <button v-if="profile?.email" type="button" @click="toggleEmailVisibility"
                            v-tooltip="isEmailHidden ? trans('Show email') : trans('Hide email')"
                            :aria-label="isEmailHidden ? trans('Show email') : trans('Hide email')"
                            class="flex h-8 w-8 shrink-0 items-center justify-center self-center rounded-full text-gray-400 hover:bg-white hover:text-[color:var(--profile-accent)]">
                            <FontAwesomeIcon :icon="isEmailHidden ? 'fal fa-eye' : 'fal fa-eye-slash'" fixed-width aria-hidden="true" />
                        </button>
                    </div>

                    <div class="flex items-start gap-x-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-inset ring-gray-100">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[color:var(--profile-accent)] ring-1 ring-gray-200">
                            <FontAwesomeIcon icon="fal fa-calendar-alt" fixed-width aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans('Member since') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                {{ useFormatTime(profile?.created_at) }}
                                <span v-if="memberFor" class="ml-1 font-normal text-gray-400">({{ memberFor }})</span>
                            </dd>
                        </div>
                    </div>

                    <div class="flex items-start gap-x-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-inset"
                        :class="nicknameError ? 'ring-red-300' : 'ring-gray-100'">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[color:var(--profile-accent)] ring-1 ring-gray-200">
                            <FontAwesomeIcon icon="fal fa-comment-alt" fixed-width aria-hidden="true" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans('Chat nickname') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                <form v-if="isEditingNickname" @submit.prevent="saveNickname" class="mt-1 flex items-center gap-x-1">
                                    <input ref="_nicknameInput" v-model="nicknameDraft" type="text" :maxlength="maxNicknameLength"
                                        :placeholder="trans('Short name shown in staff chat')"
                                        :disabled="isSavingNickname"
                                        @keydown.esc="cancelEditingNickname"
                                        class="min-w-0 flex-1 rounded-md border-gray-300 py-1 px-2 text-sm focus:border-[color:var(--profile-accent)] focus:ring-[color:var(--profile-accent)]" />
                                    <button type="submit" :disabled="isSavingNickname" :aria-label="trans('Save')"
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-green-600 hover:bg-white disabled:opacity-50">
                                        <LoadingIcon v-if="isSavingNickname" />
                                        <FontAwesomeIcon v-else icon="fal fa-check" fixed-width aria-hidden="true" />
                                    </button>
                                    <button type="button" @click="cancelEditingNickname" :disabled="isSavingNickname" :aria-label="trans('Cancel')"
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-white hover:text-gray-600 disabled:opacity-50">
                                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                                    </button>
                                </form>
                                <template v-else>
                                    <span v-if="profile?.nickname" class="truncate">{{ profile.nickname }}</span>
                                    <span v-else class="font-normal italic text-gray-400">{{ trans('Not set') }}</span>
                                </template>
                            </dd>
                            <p v-if="nicknameError" class="mt-1 text-xs text-red-600">{{ nicknameError }}</p>
                        </div>
                        <button v-if="!isEditingNickname" type="button" @click="startEditingNickname"
                            v-tooltip="trans('Edit chat nickname')" :aria-label="trans('Edit chat nickname')"
                            class="flex h-8 w-8 shrink-0 items-center justify-center self-center rounded-full text-gray-400 hover:bg-white hover:text-[color:var(--profile-accent)]">
                            <FontAwesomeIcon icon="fal fa-pen" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </dl>
            </div>
        </div>
    </header>
</template>
