import SwiftUI

struct PlaceholderView: View {
    let title: String

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: title, subtitle: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code)")
            ScrollView {
                EmptyStateView(
                    systemImage: "hammer",
                    title: "Not built yet",
                    message: "\(title) is part of the warehouse menu but has no mobile design yet."
                )
                .padding(16)
            }
        }
        .aikuScreen()
        .mainTabBar(highlightsSelection: false)
    }
}

#Preview {
    PlaceholderView(title: "Shippers")
        .environment(AppRouter())
}
