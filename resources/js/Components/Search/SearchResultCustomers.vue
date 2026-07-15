<script setup lang="ts">
import Skeleton from 'primevue/skeleton'
import Icon from '@/Components/Icon.vue'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Icon as IconTS } from '@/types/Utils/Icon'
import { ref } from 'vue'

type Customer = {
    id: number
    slug: string
    name: string
    contact_name: string
    company_name: string
    email: string
    phone: string
    location: string[]
    state_icon: IconTS
}

type CustomersResults = {
    customers: Customer[]
}

const model = defineModel('open')

defineProps<{
    results: CustomersResults | null
    isLoading: boolean
    query: string
}>()

const loadingId = ref<number | null>(null)
</script>

<template>
    <div class="col-span-3 border-r p-4 bg-gray-50">
        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="2.5rem" borderRadius="0.375rem" />
        </div>
        <div v-else class="space-y-2">
            <div class="p-3 rounded-md bg-white text-sm flex items-center justify-between">
                <span class="font-medium text-slate-700">
                    <FontAwesomeIcon icon='fal fa-users' fixed-width aria-hidden='true' />
                    {{ ctrans("Customers") }}
                </span>
                <span class="text-xs text-gray-400">{{ results?.customers?.length ?? 0 }}</span>
            </div>
        </div>
    </div>

    <div class="col-span-9 flex flex-col min-h-0">
        <div class="flex-1 p-4 space-y-4 overflow-y-auto">
            <div v-if="isLoading" class="space-y-4">
                <div v-for="i in 6" :key="i" class="p-4 rounded-md border bg-white">
                    <div class="flex justify-between items-center mb-2">
                        <Skeleton width="50%" height="1rem" />
                        <Skeleton width="60px" height="0.75rem" borderRadius="999px" />
                    </div>
                    <Skeleton width="70%" height="0.75rem" class="mb-2" />
                    <Skeleton width="40%" height="0.75rem" />
                </div>
            </div>

            <div v-else-if="results?.customers?.length">
                <Link
                    v-for="customer in results.customers"
                    :key="customer.id"
                    :href="route('grp.majordomo.redirect_customer', [customer.id])"
                    class="block p-4 rounded-md border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm mb-3"
                    @start="() => { model = false; loadingId = customer.id }"
                    @finish="() => loadingId = null"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold truncate min-w-0">
                            {{ customer.name || customer.contact_name || customer.company_name }}
                            <span v-if="customer.name && (customer.name != customer.contact_name)" class="font-normal italic opacity-75">{{ customer.contact_name }}</span>
                        </p>
                        <span v-if="loadingId === customer.id" class="shrink-0 text-slate-400">
                            <FontAwesomeIcon icon='fal fa-spinner-third' spin fixed-width aria-hidden='true' />
                        </span>
                        <Icon v-else-if="customer.state_icon" :data="customer.state_icon" size="2xs" class="shrink-0" />
                    </div>

                    <p v-if="customer.company_name" class="text-xs text-gray-500 mt-1 truncate">
                        <FontAwesomeIcon icon='fal fa-building' class='mr-1' fixed-width aria-hidden='true' />{{ customer.company_name }}
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                        <span v-if="customer.email" class="inline-flex items-center gap-1 min-w-0 max-w-full truncate">
                            <FontAwesomeIcon icon='fal fa-envelope' fixed-width aria-hidden='true' />
                            <span class="truncate">{{ customer.email }}</span>
                        </span>
                        <span v-if="customer.phone" class="inline-flex items-center gap-1">
                            <FontAwesomeIcon icon='fal fa-phone' fixed-width aria-hidden='true' />
                            {{ customer.phone }}
                        </span>
                    </div>

                    <div v-if="customer.location?.length" class="mt-2 text-xs text-gray-400">
                        <AddressLocation :data="customer.location" />
                    </div>
                </Link>
            </div>

            <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">
                {{ ctrans("No customers") }}
            </div>
        </div>
    </div>
</template>