<script setup lang="ts">
import { inject, onMounted, ref } from 'vue'
// import Input from '@/Components/Forms/Fields/Input.vue'
import { trans } from "laravel-vue-i18n"
import BannerPreview from '@/Components/Banners/BannerPreview.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { cloneDeep } from 'lodash-es'
import Button from '@/Components/Elements/Buttons/Button.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSign, faGlobe, faCopy, faCheck } from '@fal'
import { faLink } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useCopyText } from '@/Composables/useCopyText'
import { getAnnouncementComponent } from '@/Composables/useAnnouncement'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(faSign, faGlobe, faCopy, faCheck, faLink)

const props = defineProps<{
    data: {
        state: string
        template_code: string
        publisher: {
            contact_name: string
            username: string
        }
    }
    tab?: string
}>()
console.log('ccccc', props.data)
const locale = inject('locale', aikuLocaleStructure)


// Method: Copy ulid
const isOnCopy = ref(false)
const onCopyUlid = async (text: string) => {
    isOnCopy.value = true
    useCopyText(text)
    setTimeout(() => {
        isOnCopy.value = false
    }, 2000)
}
</script>


<template>
    <div class="py-3 space-y-4">
        <div class="border-b border-gray-200 pb-4">
            <!-- The banner -->
            <div v-if="data.template_code" class="mx-auto w-full max-w-5xl rounded-md overflow-hidden border border-gray-300 shadow">
                <component
                    :is="getAnnouncementComponent(data.template_code)"
                    :announcementData="data"
                    :key="data.template_code"
                    xisEditable
                />
            </div>

            <EmptyState v-else :data="{
                title: data.state != 'switch_off' ? trans('You do not have slides to show') : trans('You turn off the banner'),
                description: data.state != 'switch_off' ? trans('Create new slides in the workshop to get started') : trans('need re-publish the banner at workshop'),
            }" />
        </div>

        <div class="px-4 pqwezxc y-5 md:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
            <!-- Column 2: Order & Account Information -->
            <div class="space-y-6">
                <!-- Section: Order Information -->
                <div class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium flex items-center gap-2">
                            {{ trans('Detail information') }}:
                        </h3>
                    </div>

                    <!-- If parent_data exists -->
                    <div class="px-6 py-4 space-y-4">
                        <!-- Order Reference -->
                        <div class="flex items-center justify-between rounded-lg">
                            <dt class="text-sm font-medium">{{ trans('Name') }}</dt>
                            <dd class="text-lg font-semibold">
                                {{ data.name }}
                            </dd>
                        </div>

                        <div class="flex items-center justify-between ">
                            <dt class="text-sm font-medium text-gray-600">{{ trans('Created at') }}</dt>
                            <dd v-tooltip="useFormatTime(data.created_at, {formatTime: 'hms'} )" class="text-sm">
                                {{ useFormatTime(data.created_at, {formatTime: 'hm'} ) }}
                            </dd>
                        </div>

                        <!-- Net Amount -->
                        <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                            <dt class="text-sm font-medium text-gray-600">{{ trans('Last Publisher') }}</dt>
                            <dd class="text-sm">
                                <span class="font-bold">{{  data.publisher.contact_name }}</span> ({{ data.publisher.username  }})
                            </dd>
                        </div>

                        <!-- Payment Amount -->
                        <div class="flex items-center justify-between ">
                            <dt class="text-sm font-medium text-gray-600">{{ trans('Last Published time') }}</dt>
                            <dd v-tooltip="useFormatTime(data.ready_at, {formatTime: 'hms'} )" class="text-sm">
                                {{ useFormatTime(data.ready_at, {formatTime: 'hm'} ) }}
                            </dd>
                        </div>

                        <!-- Payment Amount -->
                        <div class="flex flex-col items-start justify-between">
                            <dt class="text-sm font-medium text-gray-600">{{ trans('Last Published message') }}</dt>
                            <dd class="text-sm border border-gray-300 bg-gray-700/5 italic text-gray-500 px-3 py-2 mt-1 w-full rounded">
                                {{ data.published_message ?? '-'}}
                            </dd>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

</template>