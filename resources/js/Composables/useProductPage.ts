import { isArray } from "lodash-es"
import type { Image as ImageTS } from "@/types/Image"

export const resolveProductImages = (product: any) => {
  const images = product?.images
  if (!images || !isArray(images)) return []

  return images
    .filter(i => i?.type === "image" && i?.images)
    .flatMap(i =>
      (Array.isArray(i.images) ? i.images : [i.images]).map(
        (img: ImageTS) => ({
          source: img,
          thumbnail: img,
        })
      )
    )
}

export const resolveProductVideo = (product: any) => {
  const images = product?.images
  if (!Array.isArray(images)) return null

  return images.find(item => item?.type === "video") ?? null
}

