import Foundation

enum GoodsInOfflinePayloads {
    static let stockDeliveryID = "stock_delivery_id"
    static let stockDeliveryItemID = "stock_delivery_item_id"
    static let returnID = "return_delivery_note_id"
    static let returnItemID = "return_delivery_note_item_id"
    static let setAsPickingLocation = "set_as_picking_location"

    static func placement(_ write: GoodsInDeliveryWrite, deliveryID: Int, shown: MobileStockDelivery?) -> OfflinePayload? {
        switch write {
        case let .place(itemID, quantity, target, setAsPickingLocation, clientRequestID):
            guard let amount = GoodsOutOfflinePlanner.positive(quantity) else { return nil }
            return place(deliveryID: deliveryID, itemID: itemID, quantity: amount, target: target, setAsPickingLocation: setAsPickingLocation, clientRequestID: clientRequestID)
        case let .placeAll(itemID, target, setAsPickingLocation, clientRequestID):
            guard let amount = GoodsOutOfflinePlanner.positive(shown?.item(itemID)?.skoToPlace) else { return nil }
            return place(deliveryID: deliveryID, itemID: itemID, quantity: amount, target: target, setAsPickingLocation: setAsPickingLocation, clientRequestID: clientRequestID)
        default:
            return nil
        }
    }

    static func place(deliveryID: Int, itemID: Int, quantity: Double, target: GoodsInPlaceTarget, setAsPickingLocation: Bool, clientRequestID: String) -> OfflinePayload {
        var payload: OfflinePayload = [
            stockDeliveryID: .int(deliveryID),
            stockDeliveryItemID: .int(itemID),
            OfflinePayloadKey.quantity: .number(quantity),
            Self.setAsPickingLocation: .bool(setAsPickingLocation),
            OfflinePayloadKey.clientRequestID: .string(clientRequestID),
        ]
        switch target {
        case .link(let linkID, _): payload[OfflinePayloadKey.locationOrgStockID] = .int(linkID)
        case .location(let locationID, _): payload[OfflinePayloadKey.locationID] = .int(locationID)
        }
        return payload
    }

    static func returnPut(_ write: GoodsInReturnWrite, returnID: Int, shown: MobileReturn?) -> (operation: OfflineOperation, payload: OfflinePayload)? {
        guard let put = ReturnPut(write) else { return nil }
        guard let amount = GoodsOutOfflinePlanner.positive(put.quantity ?? shown?.item(put.itemID)?.remaining) else { return nil }
        var payload: OfflinePayload = [
            Self.returnID: .int(returnID),
            returnItemID: .int(put.itemID),
            OfflinePayloadKey.quantity: .number(amount),
            OfflinePayloadKey.clientRequestID: .string(put.clientRequestID),
        ]
        if let linkID = put.locationOrgStockID {
            payload[OfflinePayloadKey.locationOrgStockID] = .int(linkID)
        }
        return (put.operation, payload)
    }

    private struct ReturnPut {
        let operation: OfflineOperation
        let itemID: Int
        let quantity: Double?
        let locationOrgStockID: Int?
        let clientRequestID: String

        init?(_ write: GoodsInReturnWrite) {
            switch write {
            case let .returned(itemID, quantity, locationOrgStockID, clientRequestID):
                self.init(operation: .returnPutBack, itemID: itemID, quantity: quantity, locationOrgStockID: locationOrgStockID, clientRequestID: clientRequestID)
            case let .damaged(itemID, quantity, clientRequestID):
                self.init(operation: .returnDamaged, itemID: itemID, quantity: quantity, locationOrgStockID: nil, clientRequestID: clientRequestID)
            case let .notReturned(itemID, quantity, clientRequestID):
                self.init(operation: .returnNotReturned, itemID: itemID, quantity: quantity, locationOrgStockID: nil, clientRequestID: clientRequestID)
            default:
                return nil
            }
        }

        init(operation: OfflineOperation, itemID: Int, quantity: Double?, locationOrgStockID: Int?, clientRequestID: String) {
            self.operation = operation
            self.itemID = itemID
            self.quantity = quantity
            self.locationOrgStockID = locationOrgStockID
            self.clientRequestID = clientRequestID
        }
    }
}

enum GoodsInOfflineOverlay {
    static func apply(_ entries: [QueueEntry], to delivery: MobileStockDelivery) -> MobileStockDelivery {
        let placements = entries.filter { $0.isUnsent && $0.operation == .placeStockDeliveryItem }
        guard !placements.isEmpty else { return delivery }
        var overlaid = delivery
        overlaid.items = delivery.items.map { item in
            placements
                .filter { $0.payloadInt(GoodsInOfflinePayloads.stockDeliveryItemID) == item.id }
                .reduce(item) { placed($0, by: $1) }
        }
        return overlaid
    }

    static func apply(_ entries: [QueueEntry], to goodsReturn: MobileReturn) -> MobileReturn {
        let puts = entries.filter { entry in entry.isUnsent && entry.operation.map { OfflineOperation.returnPuts.contains($0) } == true }
        guard !puts.isEmpty else { return goodsReturn }
        var overlaid = goodsReturn
        overlaid.items = goodsReturn.items.map { item in
            puts
                .filter { $0.payloadInt(GoodsInOfflinePayloads.returnItemID) == item.id }
                .reduce(item) { handled($0, by: $1) }
        }
        return overlaid
    }

    private static func placed(_ item: MobileStockDeliveryItem, by entry: QueueEntry) -> MobileStockDeliveryItem {
        let quantity = entry.quantity
        guard quantity > 0 else { return item }
        var updated = item
        let packedIn = item.sko?.packedIn
        updated.skoPlaced = GoodsOutOfflinePlanner.rounded(item.skoPlaced + quantity)
        updated.skoPlacedLabel = StockFraction.label(updated.skoPlaced, packedIn: packedIn)
        updated.skoToPlace = GoodsOutOfflinePlanner.rounded(max(0, item.skoToPlace - quantity))
        updated.skoToPlaceLabel = StockFraction.label(updated.skoToPlace, packedIn: packedIn)
        updated.locations = added(quantity, at: entry.payloadInt(OfflinePayloadKey.locationOrgStockID), to: item.locations, packedIn: packedIn)
        return updated
    }

    private static func handled(_ item: MobileReturnItem, by entry: QueueEntry) -> MobileReturnItem {
        let quantity = entry.quantity
        guard quantity > 0 else { return item }
        var updated = item
        let packedIn = item.sko?.packedIn
        switch entry.operation {
        case .returnPutBack:
            updated.returned = GoodsOutOfflinePlanner.rounded(item.returned + quantity)
            updated.returnedLabel = StockFraction.label(updated.returned, packedIn: packedIn)
            updated.locations = added(quantity, at: entry.payloadInt(OfflinePayloadKey.locationOrgStockID), to: item.locations, packedIn: packedIn)
        case .returnDamaged:
            updated.damaged = GoodsOutOfflinePlanner.rounded(item.damaged + quantity)
            updated.damagedLabel = StockFraction.label(updated.damaged, packedIn: packedIn)
        default:
            updated.notReturned = GoodsOutOfflinePlanner.rounded(item.notReturned + quantity)
            updated.notReturnedLabel = StockFraction.label(updated.notReturned, packedIn: packedIn)
        }
        let left = GoodsOutOfflinePlanner.rounded(max(0, item.remaining - quantity))
        updated.remaining = left
        updated.remainingLabel = StockFraction.label(left, packedIn: packedIn)
        updated.isHandled = item.isHandled || left <= 0
        return updated
    }

    private static func added(_ quantity: Double, at linkID: Int?, to locations: [MobileGoodsInItemLocation], packedIn: Double?) -> [MobileGoodsInItemLocation] {
        guard let linkID else { return locations }
        return locations.map { location in
            guard location.locationOrgStockId == linkID else { return location }
            var changed = location
            changed.quantity = GoodsOutOfflinePlanner.rounded(location.quantity + quantity)
            changed.quantityLabel = StockFraction.label(changed.quantity, packedIn: packedIn)
            return changed
        }
    }
}

enum GoodsInOfflineText {
    @MainActor
    static func summary(_ entry: QueueEntry, delivery: MobileStockDelivery?) -> String {
        let quantity = OfflineTexts.quantity(entry.quantity)
        let item = entry.payloadInt(GoodsInOfflinePayloads.stockDeliveryItemID).flatMap { delivery?.item($0) }
        let sko = item?.code ?? ""
        let linkID = entry.payloadInt(OfflinePayloadKey.locationOrgStockID)
        let location = linkID.flatMap { id in item?.locations.first { $0.locationOrgStockId == id }?.location.code }
        if let location {
            return L10n.format("Place · %@ × %@ in %@", quantity, sko, location)
        }
        return L10n.format("Place · %@ × %@ in a new location", quantity, sko)
    }

    @MainActor
    static func summary(_ entry: QueueEntry, goodsReturn: MobileReturn?) -> String {
        let quantity = OfflineTexts.quantity(entry.quantity)
        let item = entry.payloadInt(GoodsInOfflinePayloads.returnItemID).flatMap { goodsReturn?.item($0) }
        let sko = item?.code ?? ""
        switch entry.operation {
        case .returnPutBack:
            let linkID = entry.payloadInt(OfflinePayloadKey.locationOrgStockID)
            let location = linkID.flatMap { id in item?.locations.first { $0.locationOrgStockId == id }?.location.code } ?? ""
            return L10n.format("Back to stock · %@ × %@ at %@", quantity, sko, location)
        case .returnDamaged:
            return L10n.format("Damaged · %@ × %@", quantity, sko)
        default:
            return L10n.format("Not returned · %@ × %@", quantity, sko)
        }
    }
}
