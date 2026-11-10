import SwiftUI

struct SheetScaffold<Content: View, Footer: View>: View {
    let title: String
    var subtitle: String?
    var spacing: CGFloat = 14
    @ViewBuilder var content: Content
    @ViewBuilder var footer: Footer

    init(
        title: String,
        subtitle: String? = nil,
        spacing: CGFloat = 14,
        @ViewBuilder content: () -> Content,
        @ViewBuilder footer: () -> Footer
    ) {
        self.title = title
        self.subtitle = subtitle
        self.spacing = spacing
        self.content = content()
        self.footer = footer()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: spacing) {
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.aiku(18, .bold))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityAddTraits(.isHeader)
                if let subtitle {
                    Text(subtitle)
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text3)
                }
            }
            content
            if Footer.self != EmptyView.self {
                VStack(spacing: 8) {
                    footer
                }
                .padding(.top, 4)
            }
        }
        .padding(.horizontal, 20)
        .padding(.top, 28)
        .padding(.bottom, 16)
        .frame(maxWidth: .infinity, alignment: .leading)
    }
}

extension SheetScaffold where Footer == EmptyView {
    init(title: String, subtitle: String? = nil, spacing: CGFloat = 14, @ViewBuilder content: () -> Content) {
        self.init(title: title, subtitle: subtitle, spacing: spacing, content: content, footer: { EmptyView() })
    }
}

struct SheetSectionLabel: View {
    let text: String

    init(_ text: String) {
        self.text = text
    }

    var body: some View {
        Text(text.uppercased())
            .font(.aiku(12, .bold))
            .tracking(0.6)
            .foregroundStyle(AikuColor.text3)
    }
}

private struct FittedSheet<SheetContent: View>: View {
    @ViewBuilder var content: SheetContent
    @State private var height: CGFloat = 360

    var body: some View {
        ScrollView {
            content
                .onGeometryChange(for: CGFloat.self) { proxy in
                    proxy.size.height
                } action: { newValue in
                    height = newValue
                }
        }
        .scrollBounceBehavior(.basedOnSize)
        .scrollDismissesKeyboard(.interactively)
        .presentationDetents([.height(min(max(height, 160), 720))])
        .presentationDragIndicator(.visible)
        .presentationBackground(AikuColor.surface)
        .presentationCornerRadius(16)
    }
}

extension View {
    func aikuSheet<SheetContent: View>(
        isPresented: Binding<Bool>,
        @ViewBuilder content: @escaping () -> SheetContent
    ) -> some View {
        sheet(isPresented: isPresented) {
            FittedSheet { content() }
        }
    }

    func aikuSheet<Item: Identifiable, SheetContent: View>(
        item: Binding<Item?>,
        @ViewBuilder content: @escaping (Item) -> SheetContent
    ) -> some View {
        sheet(item: item) { value in
            FittedSheet { content(value) }
        }
    }

    func aikuConfirm(
        _ title: String,
        isPresented: Binding<Bool>,
        message: String,
        confirmTitle: String,
        cancelTitle: String = "Cancel",
        isDestructive: Bool = false,
        onConfirm: @escaping () -> Void
    ) -> some View {
        alert(title, isPresented: isPresented) {
            Button(cancelTitle, role: .cancel) {}
            Button(confirmTitle, role: isDestructive ? .destructive : nil, action: onConfirm)
        } message: {
            Text(message)
        }
    }
}
