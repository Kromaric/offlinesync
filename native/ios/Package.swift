// swift-tools-version:5.5
import PackageDescription

let package = Package(
    name: "OfflineSync",
    platforms: [
        .iOS(.v14),
        .macOS(.v11)
    ],
    products: [
        .library(
            name: "OfflineSync",
            targets: ["OfflineSync"]
        ),
    ],
    dependencies: [
        // Pas de dépendances externes - utilise seulement les frameworks iOS
    ],
    targets: [
        .target(
            name: "OfflineSync",
            dependencies: [],
            path: "Sources/OfflineSync"
        ),
        .testTarget(
            name: "OfflineSyncTests",
            dependencies: ["OfflineSync"],
            path: "Tests/OfflineSyncTests"
        ),
    ]
)
