
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

  const offer = list.find(o => String(o.id) === String(offerId))
  if (!offer) return null

  const now = new Date()

  const startAt = offer.start_at ? new Date(offer.start_at) : null
  const endAt = offer.end_at ? new Date(offer.end_at) : null

  if (startAt && now < startAt) return null

  if (offer.duration !== 'permanent') {
    if (!endAt || now > endAt) return null
  }

  if (offer.duration === 'permanent' && endAt && now > endAt) {
    return null
  }

  return offer
}