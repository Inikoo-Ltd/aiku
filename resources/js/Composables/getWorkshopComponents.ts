import { defineAsyncComponent, type AsyncComponentLoader, type Component } from 'vue'

import NotFoundComponents from '@/Components/CMS/Webpage/NotFoundComponent.vue'

const asyncComponentCache = new Map<string, Component>()

/**
 * Wraps a dynamic import in an async component, memoised by key so every render
 * resolves to the very same component identity and never remounts the block.
 */
const lazy = (key: string, loader: AsyncComponentLoader): Component => {
    const cached = asyncComponentCache.get(key)
    if (cached) {
        return cached
    }

    const component = defineAsyncComponent(loader)
    asyncComponentCache.set(key, component)

    return component
}

/**
 * @type {Record<string, AsyncComponentLoader>}
 * Keys suffixed with `@b2b` are the b2b variant of the unsuffixed key.
 */
const workshopLoaders: Record<string, AsyncComponentLoader> = {
    //topbar
    'top-bar-1': () => import('@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Workshop.vue'),
    'top-bar-2': () => import('@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Workshop.vue'),
    'top-bar-1-fulfilment': () => import('@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentWorkshop.vue'),
    'top-bar-2-fulfilment': () => import('@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentWorkshop.vue'),

    //header
    'header-1': () => import('@/Components/CMS/Website/Headers/Header1/Header1Workshop.vue'),
    'header-2': () => import('@/Components/CMS/Website/Headers/Header2/Header2Workshop.vue'),

    //menu
    'menu-1': () => import('@/Components/CMS/Website/Menus/Menu1Workshop.vue'),

    //footer
    'footer-1': () => import('@/Components/CMS/Website/Footers/footerTheme1/Footer1Workshop.vue'),

    //description catalouge
    'collection-description-1': () => import('@/Components/CMS/Webpage/CollectionDescription/CollectionDescriptionWorkshop.vue'),
    'department-description-1': () => import('@/Components/CMS/Webpage/DepartmentDescription/DepartmentDescriptionWorkshop.vue'),
    'department-description-2': () => import('@/Components/CMS/Webpage/DepartmentDescription2/DepartmentDescription2Workshop.vue'),
    'sub-department-description-1': () => import('@/Components/CMS/Webpage/SubDepartmentDescription/SubDepartmentDescriptionWorkshop.vue'),

    //sub-department
    'sub-departments-1': () => import('@/Components/CMS/Webpage/SubDepartment1/SubDepartmentWorkshop.vue'),
    'sub-departments-2': () => import('@/Components/CMS/Webpage/SubDepartment2/SubDepartmentWorkshop.vue'),
    'sub-departments-3': () => import('@/Components/CMS/Webpage/SubDepartment3/SubDepartment3Workshop.vue'),

    //family
    'families-1': () => import('@/Components/CMS/Webpage/Families1/Families1Workshop.vue'),
    'families-2': () => import('@/Components/CMS/Webpage/Families2/Families2Workshop.vue'),
    'families-3': () => import('@/Components/CMS/Webpage/Families3/Families3Workshop.vue'),

    //family
    'families-1-overview': () => import('@/Components/CMS/Webpage/Families1Overview/Families1OverviewWorkshop.vue'),

    // family-description
    'family-1': () => import('@/Components/CMS/Webpage/Family-1/family1Workshop.vue'),
    'family-2': () => import('@/Components/CMS/Webpage/Family2/family2Workshop.vue'),
    'family-3': () => import('@/Components/CMS/Webpage/Family3/family3Workshop.vue'),

    //family-extra-description
    'family-2-extra-description': () => import('@/Components/CMS/Webpage/Family2ExtraDescription/Family2ExtraDescriptionWorkshop.vue'),
    'family-3-extra-description': () => import('@/Components/CMS/Webpage/Family2ExtraDescription/Family2ExtraDescriptionWorkshop.vue'),

    //product
    'product-1': () => import('@/Components/CMS/Webpage/Product/Dropshipping/RenderDropshippingProductWorkshop.vue'),
    'product-1@b2b': () => import('@/Components/CMS/Webpage/Product/Ecommerce/RenderEcommerceProductWorkshop.vue'),
    'product-2': () => import('@/Components/CMS/Webpage/Product/Ecommerce/RenderEcommerceProductWorkshop.vue'),

    //product list
    'products-1': () => import('@/Components/CMS/Webpage/Products/Dropshipping/ListProductsWorkshop.vue'),
    'products-1@b2b': () => import('@/Components/CMS/Webpage/Products/Ecommerce/ListProductsEcomWorkshop.vue'),
    'products-2': () => import('@/Components/CMS/Webpage/Products/Ecommerce/ListProductsEcomWorkshop.vue'),

    //see-also
    'see-also-1': () => import('@/Components/CMS/Webpage/SeeAlso1/SeeAlso1Workshop.vue'),

    // Luigi
    'luigi-trends-1': () => import('@/Components/CMS/Webpage/LuigiTrends1/LuigiTrends1Workshop.vue'),
    'luigi-last-seen-1': () => import('@/Components/CMS/Webpage/LuigiLastSeen1/LuigiLastSeen1Workshop.vue'),
    'luigi-item-alternatives-1': () => import('@/Components/CMS/Webpage/LuigiItemAlternatives1/LuigiItemAlternatives1Workshop.vue'),
    'recommendation-customer-recently-bought-1': () => import('@/Components/CMS/Webpage/RecomendationRecentlyBought1/RecommendationCRB1Workshop.vue'),

    'cta-image-background': () => import('@/Components/CMS/Webpage/CtaImageBackround/CtaImageBackroundWorkshop.vue'),
    'banner': () => import('@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerWorkshop.vue'),
    'bento-grid-1': () => import('@/Components/CMS/Webpage/BentoGrid/BentoGridWorksop.vue'),
    'bricks': () => import('@/Components/CMS/Webpage/Gallery/GalleryWorkshop.vue'),
    'cta1': () => import('@/Components/CMS/Webpage/Cta1/Cta1Workshop.vue'),
    'cta2': () => import('@/Components/CMS/Webpage/Cta2/Cta2Workshop.vue'),
    'cta3': () => import('@/Components/CMS/Webpage/Cta3/Cta3Workshop.vue'),
    'iframe': () => import('@/Components/CMS/Webpage/Iframe/IframeWorkshop.vue'),
    'images': () => import('@/Components/CMS/Webpage/Image/ImageWorkshop.vue'),
    'overview_aurora': () => import('@/Components/CMS/Webpage/Overview/OverviewWorkshop.vue'),
    'script': () => import('@/Components/CMS/Webpage/Script/ScriptWorkShop.vue'),
    'text': () => import('@/Components/CMS/Webpage/Text/TextContentWorkshop.vue'),
    'cta_aurora_1': () => import('@/Components/CMS/Webpage/CtaAurora1/CtaAurora1Workshop.vue'),
    'overview_2': () => import('@/Components/CMS/Webpage/Overview2/Overview2Workshop.vue'),
    'text-column': () => import('@/Components/CMS/Webpage/TextColumn/TextColumnWorkshop.vue'),
    'pricing': () => import('@/Components/CMS/Webpage/Pricing/PricingWorkshop.vue'),
    'button': () => import('@/Components/CMS/Webpage/Button/ButtonWorkshop.vue'),
    'column-layout-2': () => import('@/Components/CMS/Webpage/Column/ColumnWorkshop.vue'),
    'column-layout-3': () => import('@/Components/CMS/Webpage/Column3/Column3Workshop.vue'),
    'column-layout-4': () => import('@/Components/CMS/Webpage/Column4/Column4Workshop.vue'),
    'disclosure': () => import('@/Components/CMS/Webpage/Disclosure/DisclosureWorkshop.vue'),
    'timeline': () => import('@/Components/CMS/Webpage/Timeline/TimelineWorkshop.vue'),
    'step-1': () => import('@/Components/CMS/Webpage/Step1/Step1Workshop.vue'),
    'carousel-1': () => import('@/Components/CMS/Webpage/Carousel-1/Carousel1Workshop.vue'),
    'cta-video-1': () => import('@/Components/CMS/Webpage/CtaVideo1/CtaVideo1Workshop.vue'),
    'video-1': () => import('@/Components/CMS/Webpage/Video/Video1Workshop.vue'),
    'user-subscribe': () => import('@/Components/CMS/Webpage/UserSubscribe/UserSubscribeWorkshop.vue'),
    'cta4': () => import('@/Components/CMS/Webpage/Cta4/Cta4Workshop.vue'),
    'blog': () => import('@/Components/CMS/Webpage/Blog/BlogWorkshop.vue'),
    'carousel-cta': () => import('@/Components/CMS/Webpage/CarouselCta/CarouselCtaWorkshop.vue'),
    'carousel-image-background': () => import('@/Components/CMS/Webpage/CarouselImageBackground/CarouselImageBackgroundWorkshop.vue'),
    'step-2': () => import('@/Components/CMS/Webpage/Step2/Step2Workshop.vue'),
    'slider-1': () => import('@/Components/CMS/Webpage/Slider-1/Slider1Workshop.vue'),
    'recommendation-from-master': () => import('@/Components/CMS/Webpage/RecommendationFromMaster/RecommendationFromMasterWorkshop.vue'),
    'relatedProductCategory': () => import('@/Components/CMS/Webpage/RelatedProductCategory/RealatedProductCategoryWorkshop.vue'),
    'recommendation-product-category-from-master': () => import('@/Components/CMS/Webpage/RecommendationProductCategoryFromMaster/RecommendationProductCategoryFromMasterWorkshop.vue'),
    'tabs': () => import('@/Components/CMS/Webpage/Tabs/TabsWorkshop.vue'),
    'faq-department': () => import('@/Components/CMS/Webpage/FaqDepartment/FaqDepartmentWorkshop.vue'),
    'top-families': () => import('@/Components/CMS/Webpage/TopFamilies/TopFamiliesWorkshop.vue'),
}

const translationLoaders: Record<string, AsyncComponentLoader> = {
    'footer-1': () => import('@/Components/CMS/Website/Footers/footerTheme1/EditFooter1Translation.vue'),
}

const productRenderB2bLoaders: Record<string, AsyncComponentLoader> = {
    'product-1': () => import('@/Components/CMS/Webpage/Product1/Ecommerce/Product1WorkshopEcom.vue'),
    'product-2': () => import('@/Components/CMS/Webpage/Product2/Product2WorkshopEcom.vue'),
}

const productRenderDropshippingLoaders: Record<string, AsyncComponentLoader> = {
    'product-1': () => import('@/Components/CMS/Webpage/Product1/Dropshipping/Product1Workshop.vue'),
}

export const getComponent = (componentName: string, options?: {
    shop_type?: string // 'b2b' | 'dropshipping'
}): Component => {
    const b2bKey = `${componentName}@b2b`
    const key = options?.shop_type === 'b2b' && workshopLoaders[b2bKey] ? b2bKey : componentName
    const loader = workshopLoaders[key]

    return loader ? lazy(key, loader) : NotFoundComponents
}

export const getTranslationComponent = (componentName: string): Component => {
    const loader = translationLoaders[componentName]

    return loader ? lazy(`translation:${componentName}`, loader) : NotFoundComponents
}

export const getProductRenderB2bComponent = (componentName: string): Component | null => {
    const loader = productRenderB2bLoaders[componentName]

    return loader ? lazy(`product-b2b:${componentName}`, loader) : null
}

export const getProductRenderDropshippingComponentWorkshop = (componentName: string): Component | null => {
    const loader = productRenderDropshippingLoaders[componentName]

    return loader ? lazy(`product-dropshipping:${componentName}`, loader) : null
}
