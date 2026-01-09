import { isArray } from "lodash-es"
import type { Image as ImageTS } from "@/types/Image"


export const resolveProductImages = (product: any) => {
  const images = product?.images

  if (!images || !isArray(images)) return []

  return images.flatMap((item: any) => {
    // FORMAT A: product images
    if (item?.type === "image" && item?.images) {
      const imgs = isArray(item.images) ? item.images : [item.images]

      return imgs.map((img: ImageTS) => ({
        source: img,
        thumbnail: img,
      }))
    }

    // FORMAT B: media library
    if (item?.source && item?.thumbnail) {
      return [
        {
          source: item.source,
          thumbnail: item.thumbnail,
        },
      ]
    }

    return []
  })
}
export const resolveProductVideo = (product: any) => {
  const images = product?.images
  if (!Array.isArray(images)) return null

  return images.find(item => item?.type === "video") ?? null
}

