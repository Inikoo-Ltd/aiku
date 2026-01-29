
type Offer = {
    id: number
    type: string
    [key: string]: any
}


export function getBestOffer(
  offersData?: {
    best_percentage_off?: { offer_id?: number | string }
    offers?: Offer[] | Record<string, Offer>
  }
): Offer | null {
  const offerId = offersData?.best_percentage_off?.offer_id
  if (offerId == null) return null

  const offers = offersData?.offers
  if (!offers) return null

  const list: Offer[] = Array.isArray(offers)
    ? offers
    : Object.values(offers)

  return list.find(offer => String(offer.id) === String(offerId)) ?? null
}
