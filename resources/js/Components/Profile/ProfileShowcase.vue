<script setup lang='ts'>
import Image from '@/Components/Image.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { Image as ImageTS } from '@/types/Image'
import { faAndroid } from "@fortawesome/free-brands-svg-icons"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'

import AppLogin from "@/Components/Forms/Fields/AppLogin.vue"
import { trans } from 'laravel-vue-i18n'

library.add(faAndroid)

const props = defineProps<{
    data: {
        data: {
            id: number
            username: string
            avatar?: ImageTS
            email: string
            about: string
            status: {
                tooltip: string
                icon: string
                class: string
            }
            parent_type: string
            contact_name: string
            created_at: string
            roles: string[]
            permissions: string[]
        }
    }
}>()
</script>

<template>
    <div class="px-6 py-6 grid grid-cols-9 gap-x-8">
        <div class="col-span-6 ring-1 ring-gray-300 shadow rounded-2xl py-6 grid grid-cols-2 gap-y-4">
            <div class="flex flex-col gap-y-4 px-8">
                <div class="mx-auto w-fit aspect-square rounded-full overflow-hidden md:h-56" :src="'person.imageUrl'" alt="">
                    <Image :src="data?.data?.avatar" />
                </div>
                
                <div class="col-span-4">
                    <div class="flex items-end gap-x-2">
                        <div class="font-semibold text-2xl">{{ data?.data?.contact_name }}</div>
                        <div class="text-gray-400">
                            #{{ data?.data?.id }} {{ data?.data?.username }}
                        </div>
                    </div>
                    <!-- <div class="mt-4">
                        <div class="font-medium">
                            Description
                        </div>
                        <div v-if="data?.data?.about" class="text-gray-500">
                            {{ data?.data?.about }}
                        </div>
                        <div v-else class="text-gray-400 italic">
                            {{ 'No description yet' }}
                        </div>
                    </div> -->
                </div>
            </div>

            <!-- Section: Contact Information -->
            <div class="mt-6 border-l border-gray-300 pl-6 space-y-3">
                <div class="font-semibold">{{ trans("Contact Information") }} <span class="ml-1 text-gray-400 font-normal">#{{ data?.data?.id }} {{ data?.data?.username }}</span></div>

                <div class="space-y-2">
                    <!-- <div class="grid grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            Status
                        </div>
                        <div class="col-span-2 font-medium">
                            {{ data?.data?.status?.tooltip }}
                        </div>
                    </div> -->

                    <!-- <div class="grid grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            User type
                        </div>
                        <div class="col-span-2 font-medium">
                            {{ data?.data?.parent_type || '-' }}
                        </div>
                    </div> -->

                    <div class="grid grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            Member since
                        </div>
                        <div class="col-span-2 font-medium">
                            {{ useFormatTime(data?.data?.created_at) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            {{ trans("Email") }}
                        </div>
                        <div class="col-span-2 font-medium">
                            {{ data?.data?.email || '-' }}
                        </div>
                    </div>

                    <!-- <div class="grid grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            Roles
                        </div>
                        <div class="col-span-2 font-medium">
                            {{ data?.data?.roles?.length }} roles
                        </div>
                    </div> -->

                    <!-- <div class="grid grid-cols-3 gap-x-5 text-sm">
                        <div class="text-gray-400">
                            Permissions
                        </div>
                        <div class="col-span-2 font-medium">
                            {{ data?.data?.permissions?.length }} access
                        </div>
                    </div> -->
                </div>
            </div>
        </div>

        <div class="h-fit grid col-span-3 ring-1 ring-gray-300 shadow rounded-2xl p-6 gap-y-6">
            <AppLogin :route="{ name: 'grp.models.profile.app-login-qrcode' }" />

            <div class="mt-8 flex flex-col items-center gap-y-1">
                <div class="text-gray-400 italic">Don't have the app?</div>
                <a href="https://github.com/inikoo/maya/releases" target="_blank"
                    class="text-blue-700 hover:underline flex items-center gap-x-2">
                    <FontAwesomeIcon icon='fab fa-android' class='' size="xl" fixed-width aria-hidden='true' />

                    <div class="text-lg font-semibold leading-5">
                        Download Android App
                    </div>
                </a>
            </div>
        </div>


    </div>


</template>