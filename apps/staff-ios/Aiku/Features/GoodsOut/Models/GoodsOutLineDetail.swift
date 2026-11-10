import Foundation

extension GoodsOutLine {
    func detailText(showsLocationStock: Bool = false) -> String {
        var parts = [pickLocation, name]
        if pickedFrom != nil {
            parts.append("from another location")
        }
        if showsLocationStock, !isHandled, let stock = locationStock {
            parts.append("\(stock) in location")
        }
        return parts.joined(separator: " · ")
    }
}
