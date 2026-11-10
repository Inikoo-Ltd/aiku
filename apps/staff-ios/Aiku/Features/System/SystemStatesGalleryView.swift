import SwiftUI

struct SystemStatesGalleryView: View {
    @Environment(AppRouter.self) private var router

    var body: some View {
        AppScreen(title: "Design states", kicker: "Debug build") {
            ForEach(SystemStateCatalog.sections) { section in
                SectionHeading(section.title, detail: "\(section.entries.count)")
                CardList(section.entries) { entry in
                    ListRow(entry.title, subtitle: entry.subtitle, icon: entry.icon, iconTone: entry.tone, showsChevron: true) {
                        open(entry.target)
                    }
                    .accessibilityIdentifier("system.gallery.\(entry.code)")
                }
            }
            HelperText("Every state screen of the design, with sample data. Real flows show the same screens.")
        }
    }

    private func open(_ target: SystemGalleryTarget) {
        switch target {
        case .system(let route): router.open(route)
        case .clocking(let route): router.open(route)
        }
    }
}
