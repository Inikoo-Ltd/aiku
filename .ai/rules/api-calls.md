---
paths:
  - 'app/Actions/Dispatching/Shipment/ApiCalls/**'
---

# Api Calls

## Packeta points and Z-BOXes are read from the notes, booked as addressId
HELP-4946 (25 Nov 2026). FindPacketaPickupPoint reads the Packeta point a dropship order asks for. It looks for a number ("[31693]", "Výdajné miesto: 32760", "Z-BOX/ID 34593") or for the point's name and street, which must be in the notes; a home address can be a shop that is also a point. CallApiPacketaShipping books the point id as addressId, with no carrierPickupPoint. A wrong box is worse than none: the resolver only acts when the text names Packeta. A number next to a Packeta word needs the point's street, or its town in the notes. A number after a bare "ID" needs the street. A number running into another digit group is a postcode. A staff line that is just "Z-BOX 31693" wins if the point is in the delivery country. Ambiguous or unknown points are refused only when the notes ask for a Z-BOX or výdejní/výdajné místo/miesto. Bare "Zásilkovna" is also Packeta's home delivery brand, so it never forces a refusal. The point list (v5 box + branch feeds, ~16k points, minutes per download, ~440 MB peak) lives only in the cache key packeta-pickup-points. packeta:refresh_pickup_points refills it daily, and hourly when missing; never download it while booking. Do not refuse on the feed's "temporarily full" status: about half of Z-BOXes show it and it flips by the hour. Packeta checks capacity live at createPacket (addressId fault).
