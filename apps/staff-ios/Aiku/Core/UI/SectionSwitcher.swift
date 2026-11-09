import SwiftUI

struct SectionSwitcherItem<ID: Hashable>: Identifiable {
    let id: ID
    var title: String?
    var systemImage: String?
    var accessibilityLabel: String?
}

struct SectionSwitcher<ID: Hashable>: View {
    let items: [SectionSwitcherItem<ID>]
    @Binding var selection: ID

    var body: some View {
        HStack(spacing: 4) {
            ForEach(items) { item in
                let isSelected = item.id == selection
                Button {
                    selection = item.id
                } label: {
                    HStack(spacing: 6) {
                        if let systemImage = item.systemImage {
                            Image(systemName: systemImage).font(.system(size: 15))
                        }
                        if let title = item.title {
                            Text(title).font(.subheadline.weight(.semibold)).lineLimit(1)
                        }
                    }
                    .foregroundStyle(isSelected ? AikuColor.yellow : AikuColor.background)
                    .frame(maxWidth: item.title == nil ? 44 : .infinity, minHeight: 40)
                    .padding(.horizontal, item.title == nil ? 4 : 0)
                    .background {
                        if isSelected {
                            RoundedRectangle(cornerRadius: 6)
                                .fill(AikuColor.indigoDeep)
                                .overlay(RoundedRectangle(cornerRadius: 6).stroke(AikuColor.indigoRing))
                        }
                    }
                }
                .accessibilityLabel(item.accessibilityLabel ?? item.title ?? "")
                .accessibilityAddTraits(isSelected ? .isSelected : [])
            }
        }
        .padding(4)
        .background(Color.white.opacity(0.12), in: RoundedRectangle(cornerRadius: 8))
    }
}

struct DetailTabs<ID: Hashable>: View {
    let items: [SectionSwitcherItem<ID>]
    @Binding var selection: ID

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 18) {
                ForEach(items) { item in
                    let isSelected = item.id == selection
                    Button {
                        selection = item.id
                    } label: {
                        VStack(spacing: 8) {
                            Group {
                                if let title = item.title {
                                    Text(title).font(.subheadline.weight(.semibold))
                                } else if let systemImage = item.systemImage {
                                    Image(systemName: systemImage).font(.system(size: 16))
                                }
                            }
                            .foregroundStyle(isSelected ? AikuColor.yellow : AikuColor.indigoMuted)
                            Rectangle()
                                .fill(isSelected ? AikuColor.yellow : .clear)
                                .frame(height: 3)
                        }
                        .fixedSize(horizontal: true, vertical: false)
                        .frame(minHeight: 36)
                    }
                    .accessibilityLabel(item.accessibilityLabel ?? item.title ?? "")
                    .accessibilityAddTraits(isSelected ? .isSelected : [])
                }
            }
        }
    }
}
