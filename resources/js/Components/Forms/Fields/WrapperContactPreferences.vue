<!--
  - Wrapper Contact Preferences Component
  - Wrapper component for ContactPreferences to be used in forms
  -->

<script setup lang="ts">
import { computed } from 'vue'
import ContactPreferences from '@/Components/ContactPreferences.vue'
import { trans } from "laravel-vue-i18n"

interface ContactPreference {
    label: string
    field: string
    is_allowed: boolean
    icon?: any
    updated_at: string | null
}

interface ContactPreferencesData {
    update_route?: {
        method: string
        name: string
        parameters: number[]
    }
    dont_contact_me: {
        label: string
        is_active: boolean
        activated_at: string | null
        reason: string | null
    }
    preferences: {
        [key: string]: ContactPreference
    }
}

interface Props {
    data?: ContactPreferencesData | null
    containerClass?: string
    showEditButton?: boolean
    editable?: boolean
    label?: string
}

const props = withDefaults(defineProps<Props>(), {
    containerClass: 'mt-4 w-64 border border-gray-300 rounded-md p-3',
    showEditButton: true,
    editable: true,
    label: 'Contact Preferences'
})

// Create default structure if no data provided
const defaultContactPreferences = computed((): ContactPreferencesData => ({
    dont_contact_me: {
        label: trans("Don't Contact Me"),
        is_active: false,
        activated_at: null,
        reason: null
    },
    preferences: {
        email: {
            label: trans('Email'),
            field: 'can_contact_by_email',
            is_allowed: true,
            updated_at: null
        },
        phone: {
            label: trans('Phone'),
            field: 'can_contact_by_phone',
            is_allowed: true,
            updated_at: null
        },
        address: {
            label: trans('Address'),
            field: 'can_contact_by_address',
            is_allowed: true,
            updated_at: null
        }
    }
}))

const contactPreferencesData = computed(() => {
    return props.data || defaultContactPreferences.value
})
</script>

<template>
    <div class="contact-preferences-wrapper">
        <!-- Optional Label -->
        <div v-if="label" class="mb-2">
            <label class="block text-sm font-medium text-gray-700">
                {{ label }}
            </label>
        </div>

        <!-- Contact Preferences Component -->
        <ContactPreferences
            :contactPreferences="contactPreferencesData"
            :containerClass="containerClass"
            :showEditButton="showEditButton"
            :editable="editable"
        />
    </div>
</template>

<style scoped>
.contact-preferences-wrapper {
    /* Add any wrapper-specific styles here */
}
</style>