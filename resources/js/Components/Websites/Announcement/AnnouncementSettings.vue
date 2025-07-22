<script setup lang='ts'>
import { inject, nextTick, onMounted, ref, toRaw, toRef } from 'vue'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputGroup from 'primevue/inputgroup'
import InputGroupAddon from 'primevue/inputgroupaddon'
import Tag from '@/Components/Tag.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { cloneDeep, get, remove, set } from 'lodash'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { useCopyText } from '@/Composables/useCopyText'
import VueDatePicker from '@vuepic/vue-datepicker'
import { usePage } from '@inertiajs/vue3'
import { faLink } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { useFormatTime } from '@/Composables/useFormatTime'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
library.add(faLink)

const props = defineProps<{
    domain: string
    onPublish: Function
    isLoadingPublish: boolean
}>()

const emits = defineEmits<{
    (e: 'onMounted'): void
}>()

const announcementData = inject('announcementData', {})
// const announcementData.schedule_at = toRef(() => announcementData.schedule_at)
const announcementScheduleFinishAt = toRef(() => announcementData.schedule_finish_at)
const announcementDataSettings = toRef(() => announcementData.settings)

// console.log('kkkk', announcementData.schedule_at.value)

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
            auth_state: 'all', // 'login' || 'logout'
        })
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



// const getAnnouncementScriptUrl = () => {
//     let xxx
//     if (usePage().props.environment === 'local') {
//         xxx = `delivery.wowsbar.test`
//     } else if (usePage().props.environment === 'staging') {
//         xxx = `https://delivery-staging.wowsbar.com`
//     } else if (usePage().props.environment === 'production') {
//         xxx = `https://delivery.wowsbar.com`
//     }

//     return window.location.origin + `/announcement.min.js?ulid=${announcementData.ulid}&delivery=${xxx}`
// }

const settingsUser = ref({
    authState: 'all', // 'logout' || 'all'
})

</script>

<template>
    <!-- <div>
        <div class="text-lg">{{ trans("Put this script in your website and you will be fine") }}😀</div>
        <div class="bg-white border border-gray-300 flex items-center justify-between gap-x-3 rounded-lg md:w-fit max-w-full mb-6">
            <a :href="getAnnouncementScriptUrl()" target="_blank" class="pl-4 md:pl-5 inline-block py-2 text-base text-gray-400 truncate text-ellipsis hover:text-gray-600">{{ getAnnouncementScriptUrl() }}</a>
            <Button :style="'secondary'" class="" size="xl" @click="useCopyText(getAnnouncementScriptUrl())" title="Copy url to clipboard">
                <FontAwesomeIcon icon='fal fa-link' class='text-gray-500' aria-hidden='true' />
            </Button>
        </div>
    </div> -->

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
                <label for="pages-all"
                    class="cursor-pointer block font-medium ">{{ trans("All pages") }}</label>
            </div>
            <div v-if="true" class="flex items-center gap-x-3">
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
                    <div>The announcement should</div>
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
                                <InputGroupAddon>{{ domain }}/</InputGroupAddon>
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
                    <div>Show ({{ announcementDataSettings.target_pages.specific.filter(item => item.will === 'show').length || 0 }}):</div>
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
                    <div>Hide ({{ announcementDataSettings.target_pages.specific.filter(item => item.will === 'hide').length }}):</div>
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
                    @input="(val: string) => (console.log('========'), set(announcementDataSettings, 'target_users.auth_state', val.target.value))"
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
                    value="login"
                    @input="(val: string) => set(announcementDataSettings, 'target_users.auth_state', val.target.value)"
                    :checked="get(announcementDataSettings, 'target_users.auth_state', false) === 'login'"
                    id="auth-login"
                    name="input-auth-state"
                    type="radio"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <label for="auth-login" class="cursor-pointer block font-medium ">
                    {{ trans("Visitor logged in") }}
                </label>
            </div>

            <div class="flex items-center gap-x-3">
                <input
                    value="logout"
                    @input="(val: string) => set(announcementDataSettings, 'target_users.auth_state', val.target.value)"
                    :checked="get(announcementDataSettings, 'target_users.auth_state', false) === 'logout'"
                    id="auth-logout"
                    name="input-auth-state"
                    type="radio"
                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                />
                <label for="auth-logout" class="cursor-pointer block font-medium ">
                    {{ trans("Visitor logged out") }}
                </label>

            </div>
        </div>
    </fieldset>

    <!-- Section: Published -->
    <fieldset class="mb-6 bg-white px-7 pt-4 pb-7 border border-gray-200 rounded-xl">
        <div class="text-xl font-semibold">Published</div>
        <p class="text-sm/6 text-gray-600">
            {{ trans("Select how announcement will published") }}
        </p>
        <div class="grid grid-cols-1 h-fit gap-y-4 ">
            <!-- Section: Start date -->
            <fieldset class="">
                <div class="text-sm/6 font-semibold ">Start date</div>
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
                    
                    <div v-if="false" class="flex items-center gap-x-3">
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
                <div class="text-sm/6 font-semibold ">Finish date</div>
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
                        <label for="inp-finish-unlimited" class="block text-sm/6 font-medium cursor-pointer ">Until I deactivated</label>
                    </div>
                    
                    <div v-if="false" class="flex items-center gap-x-3">
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
                    :modelValue="get(announcementData, 'published_message', '')"
                    @update:modelValue="(e) => set(announcementData, 'published_message', e)"
                    :placeholder="trans('My first publish')"
                    inputClass=""
                />
            </fieldset>

            <Button
                @click="() => onPublish()"
                label="Publish"
                icon="fal fa-rocket-launch"
                full
                size="xl"
                :disabled="!get(announcementData, 'published_message', '') || isLoadingPublish || !get(announcementData, 'template_code', false)"
                v-tooltip="!get(announcementData, 'template_code', false) ? trans('Select template to publish') : !get(announcementData, 'published_message', '') ? trans('Fill the description') : ''"
            />
            
            <!-- <pre>{{announcementData.schedule_at}}</pre> -->
        </div>
    </fieldset>
</template>

<style lang="css" scoped>
:deep(.p-inputtext::placeholder) {
    color: #9ca3af;
}
</style>