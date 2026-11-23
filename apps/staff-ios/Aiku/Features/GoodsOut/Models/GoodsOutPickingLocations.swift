import Foundation

enum GoodsOutPickingChannel: String, CaseIterable, Hashable, Sendable {
    case wholesale
    case dropshipping

    var title: String {
        switch self {
        case .wholesale: "Wholesale"
        case .dropshipping: "Dropshipping"
        }
    }
}

struct GoodsOutPickingDefaults: Hashable, Sendable {
    var wholesale: Int?
    var dropshipping: Int?

    subscript(channel: GoodsOutPickingChannel) -> Int? {
        get {
            switch channel {
            case .wholesale: wholesale
            case .dropshipping: dropshipping
            }
        }
        set {
            switch channel {
            case .wholesale: wholesale = newValue
            case .dropshipping: dropshipping = newValue
            }
        }
    }

    var isEmpty: Bool { wholesale == nil && dropshipping == nil }
}

struct GoodsOutPickingLocationOption: Identifiable, Hashable, Sendable {
    let linkID: Int
    let code: String
    let stockText: String
    var isDefaultWholesale = false
    var isDefaultDropshipping = false

    var id: Int { linkID }

    func isDefault(for channel: GoodsOutPickingChannel) -> Bool {
        switch channel {
        case .wholesale: isDefaultWholesale
        case .dropshipping: isDefaultDropshipping
        }
    }
}

struct GoodsOutPickingLocationUpdate: Hashable, Sendable {
    let linkID: Int
    let body: MobileLocationOrgStockPickingDefaults
}

enum GoodsOutPickingLocationsOutcome: Equatable, Sendable {
    case unchanged
    case saved
    case failed(message: String)
}

struct GoodsOutPickingLocations: Hashable, Sendable {
    let orgStockID: Int
    let code: String
    let name: String?
    let options: [GoodsOutPickingLocationOption]
    let current: GoodsOutPickingDefaults
    let canEdit: Bool

    @MainActor
    init(detail: MobileOrgStockDetail, warehouseCanEditStock: Bool) {
        orgStockID = detail.id
        code = detail.code
        name = GoodsOutMapper.nonEmpty(detail.name)
        options = detail.locations.map { link in
            GoodsOutPickingLocationOption(
                linkID: link.locationOrgStockId,
                code: link.location.code,
                stockText: link.quantity.value > 0 ? L10n.format("%@ in stock", InventoryLive.quantityText(link.quantity.value, label: link.quantityLabel)) : L10n.text("Empty"),
                isDefaultWholesale: link.isDefaultWholesale,
                isDefaultDropshipping: link.isDefaultDropshipping
            )
        }
        let links = detail.locations
        current = GoodsOutPickingDefaults(
            wholesale: links.first { $0.isDefaultWholesale }?.locationOrgStockId
                ?? links.first { $0.location.id == detail.pickingLocation?.id }?.locationOrgStockId,
            dropshipping: links.first { $0.isDefaultDropshipping }?.locationOrgStockId
                ?? links.first { $0.location.id == detail.pickingDropshippingLocation?.id }?.locationOrgStockId
        )
        canEdit = detail.can?.editStocks ?? warehouseCanEditStock
    }

    func selection(for channel: GoodsOutPickingChannel, chosen: GoodsOutPickingDefaults) -> Int? {
        chosen[channel] ?? current[channel]
    }

    func visibleOptions(for channel: GoodsOutPickingChannel, chosen: GoodsOutPickingDefaults) -> [GoodsOutPickingLocationOption] {
        guard !canEdit else { return options }
        let selected = selection(for: channel, chosen: chosen)
        return options.filter { $0.linkID == selected }
    }

    func updates(to chosen: GoodsOutPickingDefaults) -> [GoodsOutPickingLocationUpdate] {
        let wholesale = newDefault(chosen.wholesale, for: .wholesale)
        let dropshipping = newDefault(chosen.dropshipping, for: .dropshipping)
        var seen = Set<Int>()
        return [wholesale, dropshipping].compactMap { $0 }.filter { seen.insert($0).inserted }.map { linkID in
            GoodsOutPickingLocationUpdate(
                linkID: linkID,
                body: MobileLocationOrgStockPickingDefaults(
                    setAsPriorityWholesale: linkID == wholesale ? true : nil,
                    setAsPriorityDropshipping: linkID == dropshipping ? true : nil
                )
            )
        }
    }

    private func newDefault(_ linkID: Int?, for channel: GoodsOutPickingChannel) -> Int? {
        guard let linkID, options.contains(where: { $0.linkID == linkID && !$0.isDefault(for: channel) }) else { return nil }
        return linkID
    }
}

extension GoodsOutStore {
    func pickingLocations(for line: GoodsOutLine) async -> Result<GoodsOutPickingLocations, GoodsOutProblem> {
        guard isRemote, let warehouseID else { return .failure(GoodsOutProblem(message: "Sign in and choose a warehouse to change picking locations.")) }
        guard let orgStockID = line.orgStockID else { return .failure(GoodsOutProblem(message: "This line has no SKO to change.")) }
        guard let fetch = service.orgStock else { return .failure(GoodsOutProblem(message: "Picking locations can’t be loaded here.")) }
        do {
            let detail = try await fetch(warehouseID, orgStockID)
            return .success(GoodsOutPickingLocations(detail: detail, warehouseCanEditStock: rights.canEditStock))
        } catch where error.isCancellation {
            return .failure(GoodsOutProblem(message: ""))
        } catch {
            return .failure(GoodsOutProblem(message: GoodsOutMapper.message(for: error)))
        }
    }

    func savePickingLocations(_ locations: GoodsOutPickingLocations, chosen: GoodsOutPickingDefaults, on reference: String) async -> GoodsOutPickingLocationsOutcome {
        guard isRemote else { return .failed(message: "Sign in and choose a warehouse to change picking locations.") }
        guard locations.canEdit else { return .failed(message: "You can’t edit stock in this warehouse.") }
        let updates = locations.updates(to: chosen)
        guard !updates.isEmpty else { return .unchanged }
        guard let send = service.setPickingDefaults else { return .failed(message: "Picking locations can’t be changed here.") }
        var hasSaved = false
        for update in updates {
            do {
                try await send(update.linkID, update.body)
                hasSaved = true
            } catch {
                if hasSaved {
                    await loadNote(reference: reference)
                }
                return .failed(message: error.isCancellation ? "" : GoodsOutMapper.message(for: error))
            }
        }
        await loadNote(reference: reference)
        return .saved
    }
}
