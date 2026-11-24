import Foundation

extension GoodsOutLine {
    @MainActor
    func detailText(showsLocationStock: Bool = false) -> String {
        var parts = [pickLocation, name]
        if pickedFrom != nil {
            parts.append(L10n.text("from another location"))
        }
        if showsLocationStock, !isHandled, let stockLabel = stockLocations.first?.stockLabel, !stockLabel.isEmpty {
            parts.append(L10n.format("%@ in location", stockLabel))
        } else if showsLocationStock, !isHandled, let stock = locationStock {
            parts.append(L10n.format("%lld in location", stock))
        }
        return parts.joined(separator: " · ")
    }
}
