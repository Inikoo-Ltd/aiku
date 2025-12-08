<script setup lang='ts'>
import { computed, inject, nextTick, onMounted, ref, toRaw, toRef } from 'vue'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputGroup from 'primevue/inputgroup'
import InputGroupAddon from 'primevue/inputgroupaddon'
import Tag from '@/Components/Tag.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { cloneDeep, get, remove, set } from 'lodash-es'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import VueDatePicker from '@vuepic/vue-datepicker'
import { Link } from '@inertiajs/vue3'
import { faLink, faExternalLinkAlt } from '@fal'
import { faExclamationTriangle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { useFormatTime } from '@/Composables/useFormatTime'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import ListItem from '@tiptap/extension-list-item'
library.add(faLink, faExternalLinkAlt, faExclamationTriangle)

const props = defineProps<{
    // domain: string
    onPublish: Function
    isLoadingPublish: boolean
    routes_list: {
        fetch_active_announcements_route: {
            name: string,
            parameters: Record<string, any>
        }
    }
}>()

const emits = defineEmits<{
    (e: 'onMounted'): void
}>()

const layout = inject('layout', layoutStructure)
const announcementData = inject('announcementData', {})

// const announcementData.schedule_at = toRef(() => announcementData.schedule_at)
const announcementScheduleFinishAt = toRef(() => announcementData.schedule_finish_at)
const announcementDataSettings = toRef(() => announcementData.settings)


// Section: target_pages
const specificNew = ref({
    will: 'show', // 'hide'
    when: 'contain', // 'matches'
    url: ''
})

// Get date: today + 2 days
const nexterday = (new Date).setDate((new Date).getDate() + 2)

const addSpecificPage = async () => {
    const newTargetPage = cloneDeep(specificNew.value)
    
    if (Array.isArray(announcementDataSettings.value?.target_pages?.specific)) {
        announcementDataSettings.value.target_pages.specific.push(newTargetPage)
    } else {
        set(announcementDataSettings.value, ['target_pages', 'specific'], [newTargetPage])
    }

    console.log('opopop', get(announcementDataSettings.value, ['target_pages', 'specific']))
    
    await nextTick()
    specificNew.value.url = ''
}
const onDeleteSpecific = (specIndex: number) => {
    remove(announcementDataSettings.value.target_pages.specific, (item, index) => {
        return index == specIndex
    })

}

onMounted(async () => {
    if (Array.isArray(announcementDataSettings.value) && announcementDataSettings.value.length === 0) {
        // Convert from [] to {}
        announcementData.settings = {}
    }

    // Set default value target_pages
    if (!get(announcementDataSettings.value, 'target_pages.type', false)) {
        set(announcementDataSettings.value, 'target_pages', {
            type: 'all', // 'specific'
            specific: []
        })
    }

    // Set default value target_users
    if(!get(announcementDataSettings.value, 'target_users.auth_state', false)) {
        set(announcementDataSettings.value, 'target_users', {
            auth_state: 'all', // 'logged_in' || 'logged_out'
        })
    }

    if(!get(announcementDataSettings.value, 'position', false)) {
        set(announcementDataSettings.value, 'position', 'top-bar')
    }

    // // Set default value publish_start
    // if(!get(announcementDataSettings.value, 'publish_start.type', false)) {
    //     set(announcementDataSettings.value, 'publish_start', {
    //         type: 'instant',
    //         scheduled: null
    //     })
    // }

    // // Set default value publish_finish
    // if(!get(announcementDataSettings.value, 'publish_finish.type', false)) {
    //     set(announcementDataSettings.value, 'publish_finish', {
    //         type: 'infinite',
    //         scheduled: null
    //     })
    // }

    emits('onMounted')
})



const settingsUser = ref({
    authState: 'all', // 'logged_out' || 'all'
})

const publishMessage = ref('')

const listActiveAnnouncements = ref([])
const isLoadingCheckingActiveAnnouncements = ref(false)

const canPublish = computed(() => {
  const result = listActiveAnnouncements.value.find((item) => {
    return item.position === announcementDataSettings.value.position
  })
  return result
})

const onCheckActiveAnnouncements = async () => {
    isLoadingCheckingActiveAnnouncements.value = true
    try {
        const response = await axios.get(
            route(
                props.routes_list.fetch_active_announcements_route.name,
                props.routes_list.fetch_active_announcements_route.parameters
            )
        )
        if (response.status !== 200) {
            
        }
        console.log('Response axio qqqqs:', response.data)
        listActiveAnnouncements.value = (response.data.data || []).filter(
            (item: any) => item.ulid !== announcementData.ulid
        )

        if (listActiveAnnouncements.value.find((item) =>  item.position === announcementDataSettings.value.position)) {
            notify({
                title: trans("Something went wrong"),
                text: trans("Unable to publish, you have active announcements running."),
                type: "error",
            })
        } else {
            // Proceed to publish
            if (props.onPublish) {
                props.onPublish({ bodyToSend: { published_message: publishMessage.value } })
            }
        }
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    }
    isLoadingCheckingActiveAnnouncements.value = false
}

const onPublishAnyway = () => {
    if (props.onPublish) {
        listActiveAnnouncements.value = []
        props.onPublish({ bodyToSend: { published_message: publishMessage.value } })
    }
}

const routeAnnouncement = (announcement: { id: number, website_id: number }) => {
    return route('grp.org.shops.show.web.announcements.show', {
        organisation: layout.currentParams.organisation,
        shop: layout.currentParams.shop,
        website: layout.currentParams.website,
        announcement: announcement.ulid
    })
}
</script>

<template>
    <!-- Section: Page -->
    <fieldset class="mb-6 bg-white px-7 pt-4 pb-7 border border-gray-200 rounded-xl">
        <div class="text-xl font-semibold">{{ trans("Page") }}</div>
        <p class="text-sm/6 text-gray-600">
            {{ trans("Select where the Announcement will be displayed") }}
        </p>
        <div class="mt-2">
            <div class="flex items-center gap-x-3">
                <input
                    value="all"
                    @input="(val: string) => set(announcementDataSettings, 'target_pages.type', val.target.value)"
                    :checked="announcementDataSettings?.target_pages?.type ==  'all'"
                    id="pages-all"
                    name="input-target"
                    type="radio"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <label for="pages-all" class="cursor-pointer block font-medium ">
                    {{ trans("All pages") }}
                </label>
            </div>

            <div v-if="false" class="flex items-center gap-x-3">
                <input
                    value="specific"
                    @input="(val: string) => set(announcementDataSettings, 'target_pages.type', val.target.value)"
                    :checked="announcementDataSettings?.target_pages?.type ==  'specific'"
                    id="pages-specific"
                    name="input-target"
                    type="radio"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <label for="pages-specific" class="cursor-pointer block font-medium ">
                    {{ trans("Specific page") }}
                </label>

            </div>

            <!-- Section: Target specific -->
            <div v-if="true && announcementDataSettings?.target_pages?.type == 'specific'" class="mt-2 space-y-4">
                <div class="flex gap-x-4 items-center">
                    <div>{{ trans("The announcement should") }}</div>
                    <div class="w-24">
                        <Select
                            v-model="specificNew.will"
                            :options="
                                // ['show', 'hide']
                                ['show']
                            "
                            class="w-full"
                        />
                    </div>
                    <div>if URL:</div>
                </div>

                <div>
                    <div class="flex gap-x-2">
                        <div class="w-32">
                            <Select
                                v-model="specificNew.when"
                                :options="[
                                    'contain',
                                    // 'matches'
                                ]"
                                placeholder="When?"
                                class="w-full"
                            />
                        </div>
                        <div class="min-w-80 w-fit max-w-96">
                            <InputGroup v-show="specificNew.when === 'matches'">
                                <InputGroupAddon>{{ 'domain' }}/</InputGroupAddon>
                                <InputText @keydown.enter="() => specificNew.url ? addSpecificPage() : ''" type="text" v-model="specificNew.url" placeholder="blog/subpage" />
                            </InputGroup>
                            <InputText
                                v-show="specificNew.when === 'contain'"
                                @keydown.enter="() => specificNew.url ? addSpecificPage() : ''"
                                v-model="specificNew.url"
                                fluid
                                type="text"
                                placeholder="blog/subpage"
                                class="placeholder:text-gray-200"
                            />
                        </div>
                        <Button @click="() => addSpecificPage()" :style="'secondary'" label="Add" :disabled="!specificNew.url" />
                    </div>

                    <div v-show="specificNew.when === 'matches'" class="text-xs italic mt-2 text-gray-500">
                        <FontAwesomeIcon icon='fal fa-info-circle' class='text-sm' fixed-width aria-hidden='true' />
                        Put '*' to select the subpages as well (example: blog/subpage/* will affect blog/subpage/aromatheraphy/diffuser/lavender)
                    </div>
                </div>

                <!-- Section: Show URL list -->
                <div>
                    <div>{{ trans("Show") }} ({{ announcementDataSettings.target_pages.specific.filter(item => item.will === 'show').length || 0 }}):</div>
                    <TransitionGroup v-if="announcementDataSettings.target_pages.specific.length" name="list" tag="ul" class="bg-slate-200 px-2 py-2 rounded">
                        <template v-for="(spec, specIndex) in announcementDataSettings.target_pages.specific" :key="`${spec.will}${spec.when}${spec.url}`">
                            <li v-if="true || spec.will === 'show'" class="list-disc list-inside">
                                <template v-if="spec.when === 'contain'">If <span class="italic">contain</span> <span class="font-bold">{{ spec.url }}</span> in the <Tag label="URL" /></template>
                                <template v-if="spec.when === 'matches'">If <Tag label="URL" /><span class="italic">matches</span> in <span class="font-bold">{{ spec.url }}</span> </template>
                                <div @click="() => onDeleteSpecific(specIndex)" class="px-1 py-px inline cursor-pointer text-red-300 hover:text-red-500">
                                    <FontAwesomeIcon icon='fal fa-times' class='' fixed-width aria-hidden='true' />
                                </div>
                            </li>
                        </template>
                    </TransitionGroup>

                    <div v-else class="bg-slate-200 px-4 py-2 rounded text-gray-500 italic">
                        <li class="list-none list-inside">
                            <!-- <template v-if="spec.when === 'contain'">If <span class="italic">contain</span> <span class="font-bold">{{ spec.url }}</span> in the <Tag label="URL" /></template>
                            <template v-if="spec.when === 'matches'">If <Tag label="URL" /><span class="italic">matches</span> in <span class="font-bold">{{ spec.url }}</span> </template>
                            <div @click="() => onDeleteSpecific(specIndex)" class="px-1 py-px inline cursor-pointer text-red-300 hover:text-red-500">
                                <FontAwesomeIcon icon='fal fa-times' class='' fixed-width aria-hidden='true' />
                            </div> -->
                            {{ trans("No selected page yet") }}
                        </li>
                    </div>

                </div>

                <!-- Section: Hide URL list -->
                <div v-if="announcementDataSettings?.target_pages?.specific?.filter(item => item.will === 'hide').length">
                    <div>{{ trans("Hide") }} ({{ announcementDataSettings.target_pages.specific.filter(item => item.will === 'hide').length }}):</div>
                    <TransitionGroup name="list" tag="ul" class="bg-slate-200 px-2 py-2 rounded">
                        <template v-for="(spec, specIndex) in announcementDataSettings.target_pages.specific" :key="`${spec.will}${spec.when}${spec.url}`">
                            <li v-if="spec.will === 'hide'" class="list-disc list-inside">
                                <template v-if="spec.when === 'contain'"><span class="italic">contain</span> <span class="font-bold">{{ spec.url }}</span> in the <Tag label="URL" /></template>
                                <template v-if="spec.when === 'matches'"><Tag label="URL" /><span class="italic">matches</span> in <span class="font-bold">{{ spec.url }}</span> </template>
                                <div @click="() => onDeleteSpecific(specIndex)" class="px-1 py-px inline cursor-pointer text-red-300 hover:text-red-500">
                                    <FontAwesomeIcon icon='fal fa-times' class='' fixed-width aria-hidden='true' />
                                </div>
                            </li>
                        </template>
                    </TransitionGroup>
                </div>

            </div>
        </div>
    </fieldset>


    <fieldset class="mb-6 bg-white px-7 pt-4 pb-7 border border-gray-200 rounded-xl">
        <div class="text-xl font-semibold">{{ trans("Position") }}</div>
        <p class="text-sm/6 text-gray-600">
            {{ trans("Select position where the Announcement will be displayed") }}
        </p>
       <div class="mt-2">
            <div class="flex items-center gap-x-3">
                <input
                    value="top-bar"
                    @input="e => set(announcementDataSettings, 'position', e.target.value)"
                    :checked="get(announcementDataSettings, 'position') === 'top-bar'"
                    id="top-bar"
                    name="position"
                    type="radio"
                    class="h-4 w-4 border-gray-300 focus:ring-indigo-600"
                />
                <label for="top-bar" class="cursor-pointer block font-medium">
                    {{ trans('Top bar') }}
                </label>
            </div>

            <div class="flex items-center gap-x-3">
                <input
                    value="bottom-menu"
                    @input="e => set(announcementDataSettings, 'position', e.target.value)"
                    :checked="get(announcementDataSettings, 'position') === 'bottom-menu'"
                    id="bottom-menu"
                    name="position"
                    type="radio"
                    class="h-4 w-4 border-gray-300 focus:ring-indigo-600"
                />
                <label for="bottom-menu" class="cursor-pointer block font-medium">
                    {{ trans('Bottom Menu') }}
                </label>
            </div>

            <div class="flex items-center gap-x-3">
                <input
                    value="top-footer"
                    @input="e => set(announcementDataSettings, 'position', e.target.value)"
                    :checked="get(announcementDataSettings, 'position') === 'top-footer'"
                    id="top-footer"
                    name="position"
                    type="radio"
                    class="h-4 w-4 border-gray-300 focus:ring-indigo-600"
                />
                <label for="top-footer" class="cursor-pointer block font-medium">
                    {{ trans('Top footer') }}
                </label>
            </div>
        </div>
    </fieldset>

    <!-- Section: target_users -->
    <fieldset class="mb-6 bg-white px-7 pt-4 pb-7 border border-gray-200 rounded-xl">
        <div class="text-xl font-semibold">User</div>
        <p class="text-sm/6 text-gray-600">
            {{ trans("Select who will receive the Announcement") }}
        </p>
        <div class="mt-2">
            <div class="flex items-center gap-x-3">
                <input
                    value="all"
                    @input="(val: string) => (set(announcementDataSettings, 'target_users.auth_state', val.target.value))"
                    :checked="get(announcementDataSettings, 'target_users.auth_state', false) === 'all'"
                    id="auth-both"
                    name="input-auth-state"
                    type="radio"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <label for="auth-both" class="cursor-pointer block font-medium ">
                    {{ trans("Both") }}
                </label>
            </div>
            
            <div class="flex items-center gap-x-3">
                <input
                    value="logged_in"
                    @input="(val: string) => set(announcementDataSettings, 'target_users.auth_state', val.target.value)"
                    :checked="get(announcementDataSettings, 'target_users.auth_state', false) === 'logged_in'"
                    id="auth-logged_in"
                    name="input-auth-state"
                    type="radio"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <label for="auth-logged_in" class="cursor-pointer block font-medium ">
                    {{ trans("Visitor logged in") }}
                </label>
            </div>

            <div class="flex items-center gap-x-3">
                <input
                    value="logged_out"
                    @input="(val: string) => set(announcementDataSettings, 'target_users.auth_state', val.target.value)"
                    :checked="get(announcementDataSettings, 'target_users.auth_state', false) === 'logged_out'"
                    id="auth-logged_out"
                    name="input-auth-state"
                    type="radio"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <label for="auth-logged_out" class="cursor-pointer block font-medium ">
                    {{ trans("Visitor logged out") }}
                </label>

            </div>
        </div>
    </fieldset>

    <!-- Section: Published -->
    <fieldset class="mb-6 bg-white px-7 pt-4 pb-7 border border-gray-200 rounded-xl">
        <div class="text-xl font-semibold">{{ trans("Published") }}</div>
        <p class="text-sm/6 text-gray-600">
            {{ trans("Select how announcement will published") }}
        </p>
        <div class="grid grid-cols-1 h-fit gap-y-4 ">
            <!-- Section: Start date -->
            <fieldset class="">
                <div class="text-sm/6 font-semibold ">{{ trans("Start date") }}</div>
                <div class="bg-gray-50 rounded p-4 border border-gray-200 space-y-6">
                    <div class="flex items-center gap-x-3">
                        <input
                            value="instant"
                            @input="(val: string) => announcementData.schedule_at = null"
                            :checked="!announcementData.schedule_at"
                            id="inp-publish-now"
                            name="inp-publish-now"
                            type="radio"
                            class="cursor-pointer h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        />
                        <label for="inp-publish-now" class="block text-sm/6 cursor-pointer ">Publish now</label>
                    </div>
                    
                    <div  class="flex items-center gap-x-3">
                        <input
                            value="scheduled"
                            @input="(val: string) => announcementData.schedule_at = new Date(nexterday)"
                            :checked="announcementData.schedule_at"
                            id="inp-publish-schedule"
                            name="inp-publish-schedule"
                            type="radio"
                            class="cursor-pointer h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        />
                        <!-- <label for="inp-publish-schedule" class="block text-sm/6 font-medium cursor-pointer ">Scheduled</label> -->
                        <VueDatePicker
                            :modelValue="announcementData.schedule_at"
                            @update:modelValue="(e) => announcementData.schedule_at = e"
                            time-picker-inline
                            auto-apply
                            :min-date="new Date()"
                            :clearable="false"
                            class="w-fit"
                        >
                            <template #trigger>
                                <Button :style="'tertiary'" size="xs" :disabled="!announcementData.schedule_at">
                                    {{ useFormatTime(announcementData.schedule_at || nexterday, {formatTime: 'hm'}) }}
                                </Button>
                            </template>
                        </VueDatePicker>
                    </div>
                </div>
            </fieldset>

            <!-- Section: Finish date -->
            <fieldset class="">
                <div class="text-sm/6 font-semibold ">{{ trans("Finish date") }}</div>
                <div class="bg-gray-50 rounded p-4 border border-gray-200 space-y-6">
                    <div class="flex items-center gap-x-3">
                        <input
                            value="infinite"
                            @input="(val: string) => announcementData.schedule_finish_at = null"
                            :checked="!announcementData.schedule_finish_at"
                            id="inp-finish-unlimited"
                            name="inp-finish-unlimited"
                            type="radio"
                            class="cursor-pointer h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        />
                        <label for="inp-finish-unlimited" class="block text-sm/6 font-medium cursor-pointer ">{{ trans("Until deactivated") }}</label>
                    </div>
                    
                    <div class="flex items-center gap-x-3">
                        <input
                            value="scheduled"
                            @input="(val: string) => announcementData.schedule_finish_at = new Date(nexterday)"
                            :checked="announcementData.schedule_finish_at"
                            id="inp-finish-scheduled"
                            name="inp-finish-scheduled"
                            type="radio"
                            class="cursor-pointer h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        />
                        <!-- <label for="inp-finish-scheduled" class="block text-sm/6 font-medium cursor-pointer ">Scheduled</label> -->
                        <VueDatePicker
                            :modelValue="announcementData.schedule_finish_at"
                            @update:modelValue="(e) => announcementData.schedule_finish_at = e"
                            time-picker-inline
                            auto-apply
                            :min-date="new Date(announcementData.schedule_at) || new Date()"
                            :clearable="false"
                            class="w-fit"
                        >
                            <template #trigger>
                                <Button :style="'tertiary'" size="xs" :disabled="!announcementData.schedule_finish_at">
                                    {{ useFormatTime(announcementData.schedule_finish_at || nexterday, {formatTime: 'hm'}) }}
                                </Button>
                            </template>
                        </VueDatePicker>
                    </div>
                </div>
            </fieldset>

            
            <fieldset class="">
                <div class="text-sm/6 font-semibold "><span class="text-red-500 text-base leading-none mr-0.5">*</span>{{ trans("Description") }}</div>
                <PureTextarea
                    v-model="publishMessage"
                    :placeholder="trans('My first publish')"
                    inputClass=""
                />
            </fieldset>

            <!-- Section: List active announcements -->
            <div v-if="canPublish" class="relative text-sm text-amber-700 bg-amber-50 border border-amber-300 rounded px-3 py-2">
                <FontAwesomeIcon v-tooltip="trans('Warning')" icon="fas fa-exclamation-triangle" class="text-amber-700/50 absolute top-3 right-3 text-lg" fixed-width aria-hidden="true" />

                <div class="font-medium">
                    {{ trans("You have current :_count active announcements:", { _count: listActiveAnnouncements.length }) }}
                </div>

                <ul class="list-disc list-inside">
                    <li class="group w-fit ">
                        <Link :href="routeAnnouncement(canPublish)">
                            <span class="underline cursor-pointer">{{ canPublish.name }}</span>
                            <FontAwesomeIcon icon="fal fa-external-link-alt" class="ml-1 opacity-50 group-hover:opacity-100" fixed-width aria-hidden="true" />
                        </Link>
                    </li>
                </ul>

                <div class="mt-2 italic opacity-80 text-xs">
                    {{ trans("If you want to publish anyway, those active announcements will be set to inactive") }}. <span @click="onPublishAnyway" class="cursor-pointer underline font-medium opacity-80 hover:opacity-100">Publish anyway</span>
                </div>
            </div>

            <Button
                @click="() => onCheckActiveAnnouncements()"
                :label="trans('Publish')"
                icon="fal fa-rocket-launch"
                full
                :loading="isLoadingCheckingActiveAnnouncements"
                size="xl"
                :disabled="!publishMessage || isLoadingPublish || !get(announcementData, 'template_code', false)"
                v-tooltip="!get(announcementData, 'template_code', false) ? trans('Select template to publish') : !publishMessage ? trans('Enter the description') : ''"
            />
            
        </div>
    </fieldset>
</template>

<style lang="css" scoped>
:deep(.p-inputtext::placeholder) {
    color: #9ca3af;
}
</style>