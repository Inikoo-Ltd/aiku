import SwiftUI

struct GoodsInBacklogView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    private let store = GoodsInStore.shared

    var body: some View {
        AppScreen(title: "Incoming hub", kicker: "Goods in · \(session.warehouseLabel)") {
            ModuleTabs(.goodsIn, current: .goodsInBacklog)
        } content: {
            GoodsInBacklogCard(title: "Stock deliveries", counts: store.backlogCounts(for: [.supplier, .production])) {
                store.applyFilter(GoodsInDeliveryFilter())
                router.open(GoodsInRoute.stockDeliveries)
            }
            GoodsInBacklogCard(title: "Agents", counts: store.backlogCounts(for: [.agent])) {
                store.applyFilter(GoodsInDeliveryFilter(sources: [.agent]))
                router.open(GoodsInRoute.stockDeliveries)
            }
            GoodsInBacklogCard(title: "Partners", counts: store.backlogCounts(for: [.partner])) {
                store.applyFilter(GoodsInDeliveryFilter(sources: [.partner]))
                router.open(GoodsInRoute.stockDeliveries)
            }
            if session.hasFulfilmentAccess {
                GoodsInBacklogCard(title: "Fulfilment deliveries", counts: GoodsInBacklogCounts(arriving: 2, received: 0, checked: 0, bookingIn: 1, bookedInLastWeek: 4)) {
                    router.open(FulfilmentRoute.deliveries)
                }
            }
            GoodsInBacklogCard(title: "Returns", counts: store.returnsBacklog) {
                router.open(GoodsInRoute.returns)
            }
            HelperText("Same as the web Incoming hub: one row per source, counts by state. Arriving means dispatched by the supplier.")
                .padding(.horizontal, 4)
        }
    }
}

struct GoodsInBacklogCard: View {
    let title: String
    let counts: GoodsInBacklogCounts
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 10) {
                HStack {
                    Text(title)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Spacer(minLength: 8)
                    AppIcon.chevronRight.image
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(AikuColor.faint)
                        .accessibilityHidden(true)
                }
                HStack(spacing: 6) {
                    cell(counts.arriving, "Arriving", tone: .neutral)
                    cell(counts.received, "Received", tone: .info)
                    cell(counts.checked, "Checked", tone: .info)
                    cell(counts.bookingIn, "Booking in", tone: .info)
                    cell(counts.bookedInLastWeek, "Booked in 7d", tone: .success)
                }
            }
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .cardSurface()
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("goodsIn.backlog.\(title)")
    }

    private func cell(_ value: Int, _ label: String, tone: Tone) -> some View {
        VStack(spacing: 2) {
            Text(value.grouped)
                .font(.aiku(17, .bold))
                .foregroundStyle(tone.foreground)
            Text(label)
                .font(.aiku(10, .semibold))
                .foregroundStyle(AikuColor.text3)
                .multilineTextAlignment(.center)
                .lineLimit(2)
                .minimumScaleFactor(0.8)
        }
        .padding(.vertical, 8)
        .padding(.horizontal, 2)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .top)
        .background(tone.background, in: RoundedRectangle(cornerRadius: 8))
        .accessibilityElement(children: .combine)
    }
}
