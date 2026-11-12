import Foundation

extension LocationsArea {
    init(remote: MobileWarehouseArea) {
        self.init(
            remoteID: remote.id,
            code: remote.code,
            name: remote.name,
            pickingOrder: remote.pickingPosition.map { Int($0.rounded()) },
            locationCount: remote.numberLocations,
            emptyCount: remote.numberEmptyLocations,
            valueLabel: remote.stockValue > 0 ? "stock value \(LocationsFormat.money(remote.stockValue))" : ""
        )
    }
}

extension LocationsWarehouseSummary {
    init(remote: MobileLocationsOverview) {
        let used = remote.numberLocations - remote.numberLocationsEmpty
        self.init(
            areaCount: remote.numberAreas,
            locationCount: remote.numberLocations,
            emptyCount: remote.numberLocationsEmpty,
            utilisationPercent: remote.numberLocations > 0 ? Int((Double(used) / Double(remote.numberLocations) * 100).rounded()) : 0,
            toRemoveNow: remote.emptyLocationsRemoval?.toRemove ?? 0,
            removedLastWeek: remote.emptyLocationsRemoval?.removedLast7Days ?? 0
        )
    }
}

extension LocationsAutoRemoval {
    init(remote: MobileEmptyLocationsRemoval) {
        self.init(
            isOn: remote.enabled,
            changedBy: remote.lastChange?.by,
            changedOn: remote.lastChange?.at.flatMap(LocationsFormat.isoDate),
            canEdit: remote.canEdit
        )
    }
}

extension LocationsEmptySlot {
    init(remote: MobileEmptyLocationSlot) {
        self.init(
            id: remote.id,
            skoReference: remote.code,
            name: remote.name ?? "",
            locationCode: remote.locationCode,
            removedOn: remote.date.flatMap(LocationsFormat.isoDate)
        )
    }
}

extension LocationsLocation {
    init(remote: MobileLocation) {
        var scopes = Set<LocationsScope>()
        if remote.allowStocks { scopes.insert(.stocks) }
        if remote.allowFulfilment { scopes.insert(.fulfilment) }
        if remote.allowDropshipping { scopes.insert(.dropshipping) }

        self.init(
            code: remote.code,
            areaCode: remote.area?.code ?? "–",
            status: LocationsStatus(rawValue: remote.status) ?? .operational,
            scopes: scopes,
            isGoodsOutGathering: remote.isGoodsOut,
            neverRemoveEmptySkos: remote.keepEmptyOrgStocks,
            maxWeightKg: remote.maxWeight,
            maxVolumeM3: remote.maxVolume,
            slots: (remote.orgStocks ?? []).map(LocationsSlot.init(remote:)),
            pallets: (remote.pallets ?? []).map(LocationsPallet.init(remote:)),
            auditedOn: remote.auditedAt.flatMap(LocationsFormat.isoDate),
            remote: LocationsRemoteSummary(
                id: remote.id,
                slotCount: remote.numberOrgStockSlots,
                emptySlotCount: remote.numberEmptyStockSlots,
                palletCount: remote.numberPallets,
                stockValue: remote.stockValue
            )
        )
    }
}

extension LocationsSlot {
    init(remote: MobileLocation.OrgStock) {
        var roles = Set<LocationsPickingRole>()
        if remote.defaultWholesalePickingLocation { roles.insert(.wholesale) }
        if remote.defaultDropshippingPickingLocation { roles.insert(.dropshipping) }
        let quantity = Int(remote.quantity.value.rounded(.towardZero))
        let value = remote.value?.value ?? 0

        self.init(
            skoReference: remote.code,
            name: remote.name ?? "",
            quantity: quantity,
            type: LocationsSlotType(rawValue: remote.type) ?? .storing,
            roles: roles,
            minStock: remote.minStock?.value ?? 0,
            maxStock: remote.maxStock?.value ?? 0,
            replenishment: remote.replenishmentStock?.value ?? 0,
            note: remote.notes ?? "",
            unitWeightKg: 0,
            unitVolumeM3: 0,
            unitValue: quantity > 0 ? value / Double(quantity) : 0,
            remoteOrgStockID: remote.id,
            remoteLinkID: remote.locationOrgStockId
        )
    }
}

extension LocationsFormat {
    static func money(_ value: Double) -> String {
        value.formatted(.number.precision(.fractionLength(0)).locale(locale))
    }

    static func isoDate(_ text: String) -> Date? {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        if let date = formatter.date(from: text) {
            return date
        }
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter.date(from: text)
    }
}

extension LocationsPallet {
    init(remote: MobileLocation.Pallet) {
        self.init(
            reference: remote.reference,
            customer: remote.customerName ?? remote.customerReference ?? "",
            state: remote.stateLabel ?? ""
        )
    }
}

extension LocationsMovement {
    init(remote: MobileLocationMovement) {
        self.init(
            id: remote.id,
            date: remote.date.flatMap(LocationsFormat.isoDate),
            typeLabel: remote.typeLabel ?? remote.type,
            reasonLabel: remote.reasonLabel,
            note: remote.note,
            skoReference: remote.orgStockCode ?? remote.orgStockName ?? "",
            quantity: remote.quantity?.value ?? 0,
            runningQuantity: remote.runningQuantity?.value ?? nil,
            userName: remote.userName
        )
    }
}
