<script setup lang="ts">
import Skeleton from 'primevue/skeleton'

type User = {
    username: string
    email: string
    contact_name: string
    status: boolean
}

type Guest = {
    id: number
    slug: string
    code: string
    contact_name: string
    email: string
}

type SysAdminResults = {
    users: User[]
    guests: Guest[]
}

defineProps<{
    results: SysAdminResults | null
    isLoading: boolean
    query: string
}>()
</script>

<template>
    <div class="col-span-3 border-r p-4 bg-gray-50">
        <p class="text-xs text-gray-400 mb-1">Query</p>
        <p class="font-semibold text-sm mb-4">{{ query }}</p>
        <p class="text-xs text-gray-400 mb-2">Summary</p>
        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="2.5rem" borderRadius="0.75rem" />
            <Skeleton height="2.5rem" borderRadius="0.75rem" />
        </div>
        <div v-else class="space-y-2">
            <div class="p-3 rounded-xl bg-white text-sm flex items-center justify-between cursor-pointer transition hover:bg-slate-100 hover:shadow-sm active:scale-[0.98]">
                <span class="font-medium text-slate-700">Users</span>
                <span class="text-xs text-gray-400">{{ results?.users?.length ?? 0 }}</span>
            </div>
            <div class="p-3 rounded-xl bg-white text-sm flex items-center justify-between cursor-pointer transition hover:bg-slate-100 hover:shadow-sm active:scale-[0.98]">
                <span class="font-medium text-slate-700">Guests</span>
                <span class="text-xs text-gray-400">{{ results?.guests?.length ?? 0 }}</span>
            </div>
        </div>
    </div>

    <div class="col-span-4 border-r flex flex-col min-h-0">
        <div class="flex-1 p-4 space-y-4 overflow-y-auto">
            <div v-if="isLoading" class="space-y-4">
                <div v-for="i in 5" :key="i" class="p-4 rounded-xl border bg-white">
                    <div class="flex justify-between items-center mb-2">
                        <Skeleton width="60%" height="1rem" />
                        <Skeleton width="40px" height="0.75rem" borderRadius="999px" />
                    </div>
                    <Skeleton width="80%" height="0.75rem" class="mb-2" />
                    <Skeleton width="40%" height="0.75rem" />
                </div>
            </div>
            <div v-else-if="results?.users?.length">
                <div
                    v-for="user in results.users"
                    :key="user.username"
                    class="group p-4 rounded-xl border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm cursor-pointer transition-all duration-150 mb-3"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">{{ user.contact_name }}</p>
                        <span
                            class="text-[10px] px-2 py-0.5 rounded-full"
                            :class="user.status ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ user.status ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ user.email }}</p>
                    <p class="text-xs text-gray-400 mt-2">@{{ user.username }}</p>
                </div>
            </div>
            <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">No users</div>
        </div>
    </div>

    <div class="col-span-5 flex flex-col min-h-0">
        <div class="flex-1 p-4 space-y-4 overflow-y-auto">
            <div v-if="isLoading" class="space-y-4">
                <div v-for="i in 5" :key="i" class="p-4 rounded-xl border bg-slate-50">
                    <Skeleton width="70%" height="1rem" class="mb-2" />
                    <Skeleton width="80%" height="0.75rem" class="mb-2" />
                    <Skeleton width="40%" height="0.75rem" />
                </div>
            </div>
            <div v-else-if="results?.guests?.length">
                <div
                    v-for="guest in results.guests"
                    :key="guest.id"
                    class="group p-4 rounded-xl border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm cursor-pointer transition-all duration-150 mb-3"
                >
                    <p class="text-sm font-semibold text-slate-900">{{ guest.contact_name }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ guest.email }}</p>
                    <p class="text-xs text-gray-400 mt-2">Code: {{ guest.code }}</p>
                </div>
            </div>
            <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">No guests</div>
        </div>
    </div>
</template>
