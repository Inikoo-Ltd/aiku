import md5 from 'md5'
import { get, set, cloneDeep } from "lodash-es"


export const useBannerHash = (bannerObject: any) => {
    const dataFiltered = cloneDeep(bannerObject) // Clone to prevent disturbing actual data

    dataFiltered.components.map((component: any) => {
        // To remove all keys but 'id' inside each Slide 
        if(component.image)
        Object.keys(component.image).forEach(key => {
            // key is mobile, tablet, desktop
            let idImage = get(component.image[key], 'id', null)
            component.image[key] = {};
            set(component.image[key], 'id', idImage)
        });

        // To remove key 'layout'
        delete component.layout
    })

    delete dataFiltered.published_hash
    delete dataFiltered.hash


    // dataFiltered
    // console.log("========================= useBannerHash ========================")
    // console.log('real data:', bannerObject)
    // console.log('hash', md5(JSON.stringify(bannerObject)))
    // console.log('data Filtered:', dataFiltered)
    // console.log('hash', md5(JSON.stringify(dataFiltered)))
    // console.log("==================================================================")

    return md5(JSON.stringify(dataFiltered))  // change object to string and then hash the string
}