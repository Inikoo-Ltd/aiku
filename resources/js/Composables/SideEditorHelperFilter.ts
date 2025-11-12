
import type { Component } from 'vue'

import NotFoundComponents from '@/Components/CMS/Webpage/NotFoundComponent.vue'
import MinMaxPrice from '@/Components/Workshop/Properties/MinMaxPrice.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'

// Field list of SideEditor
export const getFilterComponent = (componentName: string) => {
    const components: Component = {
        'min_max_price' : MinMaxPrice, 
        'selectquery' : PureMultiselectInfiniteScroll,
    }
    return components[componentName] ?? NotFoundComponents
}
