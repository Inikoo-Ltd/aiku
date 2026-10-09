import { computed } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { routeType } from '@/types/route'

export interface GalleryCategory {
    key: string
    label: string
    route: routeType
    icon?: string
}

export const useShopGalleryCategories = () => computed<GalleryCategory[]>(() => {
    const shop = route().params?.shop
    if (!shop) {
        return []
    }

    return [
        { key: 'logos', label: ctrans('Logos'), icon: 'fal fa-copyright', route: { name: 'grp.json.shop.gallery.logos', parameters: { shop } } },
        { key: 'catalogue', label: ctrans('Catalogue images'), icon: 'fal fa-books', route: { name: 'grp.json.shop.gallery.catalogue', parameters: { shop } } },
    ]
})
