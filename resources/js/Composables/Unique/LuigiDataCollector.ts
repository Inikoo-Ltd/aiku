/**
 * Author: Vika Aqordi
 * Created on 14-10-2025-09h-03m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { ProductHit } from "@/types/Luigi/LuigiTypes"

interface Response {
    hits: ProductHit[];
    recommendation_id: string;
    recommendation_type: string;
    recommender_client_identifier: string;
}

// Collector: recommendation list
export const RecommendationCollector = (response: Response) => {
    const listItems = response.hits.map((hit: any, index: number) => ({
        item_id: hit.url,
        item_name: hit.attributes.title,
        index: index + 1,
        price: hit.attributes.price,
        type: hit.type,
    }))
    const idItems = response.hits.map((hit: any) => hit.url)

    const body = {
        event: "view_item_list",
        ecommerce: {
            item_list_name: "Recommendation",
            items: listItems,
            filters: {
                "RecommenderClientId": response.recommender_client_identifier,
                "ItemIds": idItems,
                "Type": response.recommendation_type,
                "RecommendationId": response.recommendation_id,
            }
        }
    }

    window.dataLayer?.push(body)
}

// Collector: on click recommendation
// export const RecommendationCollector = (response: Response) => {
//     const listItems = response.hits.map((hit: any, index: number) => ({
//         item_id: hit.url,
//         item_name: hit.attributes.title,
//         index: index + 1,
//         price: hit.attributes.price,
//         type: hit.type,
//     }))
//     const idItems = response.hits.map((hit: any) => hit.url)

//     const body = {
//         event: "view_item_list",
//         ecommerce: {
//             item_list_name: "Recommendation",
//             items: listItems,
//             filters: {
//                 "RecommenderClientId": response.recommender_client_identifier,
//                 "ItemIds": idItems,
//                 "Type": response.recommendation_type,
//                 "RecommendationId": response.recommendation_id,
//             }
//         }
//     }

//     window.dataLayer?.push(body)
// }