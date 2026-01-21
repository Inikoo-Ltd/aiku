<script setup lang='ts'>
import Image from '@/Components/Image.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useLiveUsers } from '@/Stores/active-users'
import { Image as ImageTS } from '@/types/Image'
import { inject, ref, watch } from 'vue'
import Tag from '@/Components/Tag.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
import PermissionsPictogram from '@/Components/DataDisplay/PermissionsPictogram.vue'
import Toggle from '@/Components/Pure/Toggle.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faSkull, faTimes } from '@fal'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

const props = defineProps<{
    tab: string
    twoFAStatus: {
        has_2fa: boolean
        is_two_factor_required: boolean
    }
    data: {
        data: {
            id: number
            username: string
            avatar: ImageTS
            email?: string
            about?: string
            parent_type: string
            contact_name: string
            authorizedOrganisations: {
                slug: string
                name: string
                type: string
                // code: string
                // type_label: string
                // type_icon: {
                //     tooltip: string
                //     icon: string | string[]
                // }
                // number_employees_state_working?: number
                // number_shops_state_open?: number
                // number_customers?: number
            }[]
            permissions: string[]
            permissions_pictogram: {}
            last_active_at?: Date
            last_login: {
                ip?: string
                geolocation: string[]
            }
            optionsEachOrganisations: {}
        }
    }
}>()

const layout = inject('layout', layoutStructure)
const activeUsers = useLiveUsers().liveUsers
const isLoadingUpdate = ref(false);
const isLoadingUpdateRequire2FA = ref(false);

const disable2FA = async () => {
    isLoadingUpdate.value = true;
    await axios.patch(route('grp.models.user.update', {user: props.data.data.id}), {
        disable_2fa: true
    })
        .then((response) => {
            props.twoFAStatus.has_2fa = false;
            notify({
                title: "Success",
                text: "Successfully updated the user data",
                type: "success",
            })
        })
        .catch((response) => {
            notify({
                title: "Failed",
                text: "Fail to update the user data",
                type: "error",
            })
        }).finally(() => {
            isLoadingUpdate.value = false
        });
}

const force2FA = async () => {
    await axios.patch(route('grp.models.user.update', {user: props.data.data.id}), {
        is_two_factor_required: props.twoFAStatus.is_two_factor_required
    })
        .then((response) => {
            notify({
                title: "Success",
                text: "Successfully updated the user data",
                type: "success",
            })
        })
        .catch((response) => {
            notify({
                title: "Failed",
                text: "Fail to update the user data",
                type: "error",
            })
        }).finally(() => {
            isLoadingUpdateRequire2FA.value = false;
        });
}

</script>

<template>
    <!-- <pre>{{ props.data }}</pre> -->
    <div class="flex py-4 px-8 gap-x-8">
        <div class="">
            <div class="h-40 aspect-square rounded-full overflow-hidden shadow m-5">
                <Image :src="data?.data?.avatar" :alt="data?.data?.contact_name" />
            </div>
        </div>

        <dl class="w-full grid grid-cols-1 sm:grid-cols-2">
            <div class="border-t border-gray-100 px-4 py-6 sm:col-span-1 sm:px-0">
                <dt class="text-sm font-medium">{{ trans("Name") }}:</dt>
                <dd class="mt-1 text-sm sm:mt-2">{{ data?.data?.contact_name }}</dd>
            </div>

            <div class="px-4 py-6 sm:col-span-1 sm:px-0">
                <dt class="text-sm font-medium">{{ trans("Email") }}:</dt>
                <!-- <dd class="mt-1 text-sm sm:mt-2">{{ data?.data?.email || '-' }}</dd> -->
                <a
                    v-if="data?.data?.email"
					:href="`mailto:${data?.data?.email}`"
					class="mt-1 text-sm sm:mt-2 xtext-gray-500 white w-full truncate hover:underline"
					>{{ data?.data?.email }}</a
				>
                <div v-else>
                    -
                </div>
            </div>
            <!-- <div class="border-t border-gray-100 px-4 py-6 sm:col-span-1 sm:px-0">
                <dt class="text-sm font-medium">{{ trans("Status") }}:</dt>
                <dd class="mt-1 text-sm sm:mt-2">
                    <Tag :label="activeUsers[data?.data?.id] ? trans('Online') : trans('Offline')" :theme="activeUsers[data?.data?.id] ? 3 : undefined" />
                </dd>
            </div> -->

            <div class="border-t border-gray-100 px-4 py-6 sm:col-span-1 sm:px-0">
                <dt class="text-sm font-medium">{{ trans("Last Active") }}:</dt>
                <dd class="mt-1 text-sm sm:mt-2">
                    {{ activeUsers[data?.data?.id]?.last_active ? useFormatTime(activeUsers[data?.data?.id].last_active) : '-' }}
                </dd>
            </div>

            <div v-if="data?.data?.authorizedOrganisations?.length" class="border-t border-gray-100 px-4 py-6 sm:px-0">
                <dt class="text-sm font-medium">{{ trans("Authorized Organisations") }}:</dt>
                <dd class="mt-1 text-sm sm:mt-2 flex flex-wrap">
                    <div v-for="item of data?.data?.authorizedOrganisations" class="m-1">
                        <Tag :label="item.name" />
                    </div>
                </dd>
            </div>

            <!-- Section: Geolocation -->
            <div class="border-t border-gray-100 px-4 py-6 sm:px-0">
                <dt class="text-sm font-medium">{{ trans("Geolocation") }}:</dt>
                <dd class="mt-1 text-sm sm:mt-2 flex flex-wrap">
                    {{ data?.data?.last_login.geolocation.filter(geo => geo).join(', ') }}
                    <!-- <template v-for="item of data?.data?.last_login.geolocation">
                        <div v-if="item" class="m-1">
                            <Tag :label="item" />
                        </div>
                    </template> -->
                </dd>
            </div>

            <div v-if="data?.data?.permissions_pictogram" class="sm:col-span-2">
                <PermissionsPictogram
                    :data_pictogram="data?.data?.permissions_pictogram"
                />
            </div>

        </dl>

        <div class="w-48 py-6">
            <div class="mb-3 w-full">
                <dt class="text-sm font-medium">{{ trans('Has 2FA') }}: </dt>
                <dd class="pt-1 inline-grid w-full">
                    <div v-if="twoFAStatus.has_2fa" class="w-full">
                        <span class="border rounded-md border-green-500 px-2 py-1">
                            <FontAwesomeIcon :icon="faCheck" class="text-green-500"/> {{ trans('Enabled') }}
                        </span>
                        <span class="border rounded-md border-red-500 hover:border-red-300 active:border-red-700 text-red-500 hover:text-red-300 active:text-red-700 cursor-pointer px-2 py-1 ml-2" @click="disable2FA()">
                            <LoadingIcon v-if="isLoadingUpdate"/>
                            <FontAwesomeIcon v-else :icon="faSkull"/>
                        </span>
                    </div>
                    <div v-else class="w-full">
                        <span class="border rounded-md border-red-500 px-2 py-1">
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500"/> {{ trans('Disabled') }}
                        </span>
                    </div>
                </dd>
            </div>
            <div class="mb-4">
                <dt class="text-sm font-medium">{{ trans('Force 2FA') }}: </dt>
                <dd>
                    <Toggle :model-value="twoFAStatus.is_two_factor_required" @update:model-value="twoFAStatus.is_two_factor_required = !twoFAStatus.is_two_factor_required; force2FA()" :disabled="isLoadingUpdateRequire2FA">
                    </Toggle>
                    <LoadingIcon v-if="isLoadingUpdateRequire2FA" class="ml-1"/>
                </dd>
            </div>
        </div>
    </div>

    
    <!-- <pre>{{ data?.data?.permissions_pictogram }}</pre> -->
</template>
