import { ref, computed, reactive, watch } from 'vue'
import { debounce } from 'lodash-es'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

export function useFilterRecipients(props: any, notify: any) {
    /* ---------------- STATE ---------------- */
    const activeFilters = ref<Record<string, any>>(
        props.filters ? { ...props.filters } : {}
    )

    const preloadedEntities = reactive<Record<string, any[]>>({})

    /* ---------------- COMPUTED ---------------- */
    const activeFilterCount = computed(() =>
        Object.keys(activeFilters.value ?? {}).length
    )

    const isAllCustomers = computed(() =>
        Object.keys(activeFilters.value).length === 0
    )

    const readyFilters = computed(() =>
        Object.fromEntries(
            Object.entries(activeFilters.value).filter(
                ([_, f]: any) => f?.config?.type
            )
        )
    )

     const FILTER_CONFLICTS: Record<string, string[]> = {
        registered_never_ordered: ['orders_in_basket','by_order_value','orders_collection','by_family','by_subdepartment','by_family_never_ordered','by_showroom_orders','by_department'],
        orders_in_basket: ['registered_never_ordered'],
        by_order_value: ['registered_never_ordered'],
        orders_collection: ['registered_never_ordered'],
        by_family: ['registered_never_ordered'],
        by_subdepartment: ['registered_never_ordered'],
        by_family_never_ordered: ['registered_never_ordered'],
        by_showroom_orders: ['registered_never_ordered'],
        by_department: ['registered_never_ordered']
    }

    function hasConflict(newKey: string) {
        const activeKeys = Object.keys(activeFilters.value)
        const conflicts = FILTER_CONFLICTS[newKey] || []
        return activeKeys.find(k => conflicts.includes(k)) || null
    }

    /* ---------------- FILTER ADD ---------------- */
   const addFilter = (key: string, config: any) => {
        const conflictWith = hasConflict(key)
        if (conflictWith) {
            notify({
                title: "Filter conflict",
                text: `"${config.label}" cannot be combined with "${activeFilters.value[conflictWith].config.label}"`,
                type: "error"
            })
            return
        }

        let value: any = true

        if (config.type === 'boolean') value = { date_range: null, amount_range: { min: null, max: null }, date_range_preset: null }
        if (config.type === 'select') value = config.options?.[0]?.value ?? null
        if (config.type === 'multiselect') value = config.label === 'By Family Never Ordered' ? { ids: null } : config.behavior_options ? { ids: [], behaviors: ['purchased'], combine_logic: 'or' } : { ids: [] }
        if (config.type === 'daterange') value = { date_range: null }
        if (config.type === 'entity_behaviour') value = { ids: [], behaviors: [], combine_logic: true }
        if (config.type === 'location') value = { mode:'direct', country_ids:[], postal_codes:[], location:'', radius:null, radius_custom:null, lat:null, lng:null, resolved:false }

        activeFilters.value[key] = { value, config }
    }

    const removeFilter = (key: string) => {
        delete activeFilters.value[key]
    }

    const clearAllFilters = () => {
        Object.keys(activeFilters.value).forEach(k => delete activeFilters.value[k])
        fetchCustomers()
    }

    /* ---------------- PAYLOAD ---------------- */
    const filtersPayload = computed(() => {
        const payload: any = {}

        Object.entries(activeFilters.value).forEach(([key, filter]: any) => {
            payload[key] = { value: filter.value }
        })

        return payload
    })

    /* ---------------- FETCH CUSTOMERS ---------------- */
    const fetchCustomers = debounce(() => {
        router.get(
            route(route().current(), route().params),
            { filters: filtersPayload.value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['customers', 'filters', 'estimatedRecipients']
            }
        )
    }, 400)

    /* ---------------- LOCATION GEOCODE ---------------- */
    const getLatLngToLocation = async (filter: any, forceMode?: 'forward' | 'reverse') => {
        const v = filter.value
        let params: any = {}

        const mode =
            forceMode ||
            (v.lastSource === 'map' ? 'reverse' : 'forward')

        if (mode === 'reverse') {
            if (!v.lat || !v.lng) return
            params.latitude = v.lat
            params.longitude = v.lng
        } else {
            if (!v.location) return
            params.location = v.location
        }

        v.loadingMap = true

        try {
            const res = await axios.get(route('grp.json.get_geocode'), { params })
            const data = res.data

            if (data.latitude && data.longitude) {
                v.lat = Number(data.latitude)
                v.lng = Number(data.longitude)
                v.zoom = v.zoom || 12
            }

            if (data.city || data.formatted_address) {
                v.location = data.city || data.formatted_address
            }
            
            v.resolved = true
        } finally {
            v.loadingMap = false
        }
    }

    const debouncedReverseGeocode = debounce(getLatLngToLocation, 500)

    const onMapClick = (e: any, filter: any) => {
        const v = filter.value

        v.lat = Number(e.latlng.lat)
        v.lng = Number(e.latlng.lng)
        v.lastSource = 'map'

        debouncedReverseGeocode(filter)
    }

    const onMarkerDrag = (e: any, filter: any) => {
        const pos = e.target.getLatLng()
        const v = filter.value

        v.lat = Number(pos.lat)
        v.lng = Number(pos.lng)
        v.lastSource = 'map'

        debouncedReverseGeocode(filter)
    }

    const shouldShowMap = (val: any) => {
        if (val.mode === 'radius') return true
        if (val.mode === 'direct' && (val.country_ids?.length || val.postal_codes?.length)) return true
        return false
    }
    const radiusInMeters = (val: any) => {
        const km = val.radius === 'custom'
            ? Number(val.radius_custom) || 0
            : Number(val.radius) || 0

        return km * 1000
    }

    const getPostalCodeModel = (filter: any) => {
        return computed({
            get: () => filter.value.postal_codes?.join(', ') || '',
            set: (val: string) => {
                filter.value.postal_codes = val
                    .split(',')
                    .map(v => v.trim())
                    .filter(Boolean)
            }
        })
    }
    return {
        activeFilters,
        activeFilterCount,
        isAllCustomers,
        readyFilters,
        radiusInMeters,
        shouldShowMap,
        getPostalCodeModel,
        addFilter,
        removeFilter,
        clearAllFilters,
        filtersPayload,
        onMapClick,
        onMarkerDrag,
        fetchCustomers,
        preloadedEntities,
        getLatLngToLocation,
    }
}
