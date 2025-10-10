import { useColorTheme } from '@/Composables/useStockList'

import { OrganisationsData, Group, OrganisationState, StackedComponent} from '@/types/LayoutRules'
import { Colors } from "@/types/Color"
import { Navigation, grpNavigation, orgNavigation } from "@/types/Navigation"
import { Image } from "@/types/Image"
import { Notification } from '@/types/Notification'

export const layoutStructure = {
    agents: {
        // currentOrganisations: '',
        data: {} as OrganisationsData[]
    },
    agentsState: {} as {[key: string]: OrganisationState},
    app: {
        name: "",  // For styling navigation depend on which App
        color: null as unknown | Colors,  // Styling layout color
        theme: useColorTheme[0] as string[],  // For styling app color
        url: null as string | null, // For url on logo top left
        environment: null as string | null, // 'local' | 'staging' 
    },
    currentModule: "",
    currentRoute: "grp.dashboard.show", // Define value to avoid route null at first load
    currentParams: {} as {[key: string]: string},
    currentQuery: {} as {[key: string]: string},
    digital_agency: null as {data: OrganisationsData[]} | null,
    group: null as Group | null,
    help_portal_url: null as string | null,
    leftSidebar: {
        show: true,
    },
    navigation: {
        grp: {} as grpNavigation,
        org: {} as { [key: string]: orgNavigation } | { [key: string]: Navigation } | Navigation
    },
    organisations: {
        data: {} as OrganisationsData[]
    },
    organisationsState: {} as {[key: string]: OrganisationState},
    rightSidebar: {
        activeUsers: {
            users: [],
            count: 0,
            show: false
        },
        language: {
            show: false
        }
    },
    root_active: null as string | null,
    stackedComponents: [] as StackedComponent[],
    user: {} as {
        id: number,
        email: string,
        username: string,
    },
    notifications: [] as Notification[],
    avatar_thumbnail: null as Image | null,

    isShopPage: false as boolean,
    isFulfilmentPage: false as boolean,
    // isShopPage: false as boolean,
}