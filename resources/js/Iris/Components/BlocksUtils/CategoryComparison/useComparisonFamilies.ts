import axios from "axios"
import { computed, ref, watch, type Ref } from "vue"
import type {
    CategoryComparisonValue,
    ComparisonFamily,
    ScreenType,
} from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

export const DEFAULT_NUMBER_OF_FAMILIES: Record<ScreenType, number> = {
    mobile: 2,
    tablet: 3,
    desktop: 4,
}

export const useComparisonFamilies = (
    fetchRouteName: string,
    fetchParameters: Ref<Record<string, string> | undefined>,
    fieldValue: Ref<CategoryComparisonValue | undefined>,
    screenType: Ref<ScreenType>
) => {
    const loading = ref(false)
    const fetchedFamilies = ref<ComparisonFamily[]>([])

    const loadFamilies = async () => {
        if (!fetchParameters.value) {
            fetchedFamilies.value = []
            return
        }

        loading.value = true

        try {
            const response = await axios.get(route(fetchRouteName, fetchParameters.value))

            fetchedFamilies.value = response.data?.data ?? []
        } catch (error) {
            console.error("Failed loading families for comparison:", error)
            fetchedFamilies.value = []
        } finally {
            loading.value = false
        }
    }

    const currentFamily = computed(() => fetchedFamilies.value.find(family => family.is_current))

    const familyOptions = computed<ComparisonFamily[]>(
        () => fetchedFamilies.value.filter(family => !family.is_current)
    )

    const numberOfFamilies = computed(() => {
        const configured = fieldValue.value?.number_of_families
        const forScreen = typeof configured === "object" && configured !== null
            ? configured[screenType.value] ?? configured.desktop
            : configured

        return Number(forScreen) > 0
            ? Number(forScreen)
            : DEFAULT_NUMBER_OF_FAMILIES[screenType.value]
    })

    const numberOfComparedFamilies = computed(() =>
        Math.max(numberOfFamilies.value - (currentFamily.value ? 1 : 0), 0)
    )

    const selectedSlugs = ref<string[]>([])

    const familyOf = (slug: string) => familyOptions.value.find(family => family.slug === slug)

    const families = computed<ComparisonFamily[]>(() => {
        const compared = selectedSlugs.value
            .map(familyOf)
            .filter((family): family is ComparisonFamily => Boolean(family))

        return currentFamily.value ? [currentFamily.value, ...compared] : compared
    })

    const toggleFamily = (slug: string) => {
        if (selectedSlugs.value.includes(slug)) {
            selectedSlugs.value = selectedSlugs.value.filter(selected => selected !== slug)
            return
        }

        if (selectedSlugs.value.length >= numberOfComparedFamilies.value) {
            return
        }

        selectedSlugs.value = [...selectedSlugs.value, slug]
    }

    watch([familyOptions, numberOfComparedFamilies], () => {
        const optionSlugs = familyOptions.value.map(family => family.slug as string)
        const kept = selectedSlugs.value.filter(slug => optionSlugs.includes(slug))
        const filler = optionSlugs.filter(slug => !kept.includes(slug))

        selectedSlugs.value = [...kept, ...filler].slice(0, numberOfComparedFamilies.value)
    }, { immediate: true })

    watch(fetchParameters, loadFamilies, { immediate: true })

    return {
        families,
        familyOptions,
        selectedSlugs,
        numberOfComparedFamilies,
        toggleFamily,
        loading,
        loadFamilies,
    }
}
