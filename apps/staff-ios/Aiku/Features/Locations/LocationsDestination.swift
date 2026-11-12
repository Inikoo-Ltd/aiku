import SwiftUI

struct LocationsDestination: View {
    let route: LocationsRoute

    var body: some View {
        switch route {
        case .warehouse:
            LocationsWarehouseView()
        case .areas:
            LocationsAreasView()
        case let .area(code):
            LocationsAreaDetailView(code: code)
        case .locations:
            LocationsListView()
        case let .location(code):
            LocationsDetailView(code: code)
        case let .skoInLocation(sko, location):
            LocationsStockEditView(sko: sko, locationCode: location)
        case let .emptySlots(kind):
            LocationsEmptySlotsView(kind: kind)
        }
    }
}
