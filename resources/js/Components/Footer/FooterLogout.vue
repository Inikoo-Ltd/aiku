<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSignOutAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import { inject, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useLogoutAuth } from "@/Composables/useAppMethod"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faSignOutAlt)

const layout = inject("layout", layoutStructure)
const isLoggingOut = ref(false)

const logout = () => {
    useLogoutAuth(layout.user, {
        onStart: () => (isLoggingOut.value = true),
        onError: () => (isLoggingOut.value = false),
    })
}
</script>

<template>
    <Popover v-slot="{ open }" class="relative h-full">
        <PopoverButton
            type="button"
            :class="open ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 text-gray-200'"
            class="inline-flex items-center gap-x-1 px-3 h-full outline-none focus:outline-none focus:ring-0">
            <FontAwesomeIcon icon="fal fa-sign-out-alt" class="text-xs" fixed-width aria-hidden="true" />
            <span class="text-xs leading-none font-extralight">{{ ctrans("Logout") }}</span>
        </PopoverButton>

        <PopoverPanel class="absolute bottom-full right-0 z-10 mb-1 rounded-md border border-gray-200 bg-white px-4 py-3 shadow">
            <div class="flex min-w-32 flex-col justify-center gap-y-2">
                <div class="whitespace-nowrap text-xs text-gray-500">
                    {{ ctrans("Are you sure want to logout?") }}
                </div>
                <div class="mx-auto">
                    <Button @click="logout" :loading="isLoggingOut" :label="ctrans('Yes, Logout')" type="red" />
                </div>
            </div>
        </PopoverPanel>
    </Popover>
</template>
