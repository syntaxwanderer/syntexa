# Syntexa Overlay Architecture

The Syntexa Framework implements a unique "Layered Overlay" system that allows for seamless customization of base module logic without modifying the core packages.

## 🎯 The Core Concept: "Source over Packages"

In a traditional framework, `/src` is simply where your code lives. In Syntexa, `/src` acts as an **Override Layer** that sits on top of the base modules located in `packages/syntexa/`.

### How it works:
The `AttributeDiscovery` system scans the codebase and assigns a **Priority Score** to every discovered class (Requests, Handlers, Entities) based on its location:

| Location | Priority | Role |
| :--- | :--- | :--- |
| `/packages/syntexa/*` | **200** | Base/Standard Logic |
| `/packages/acme/*` | **250** | Third-party Modules |
| `/src/*` | **400** | **Project-Specific Overrides (Overlay)** |

## 🚀 Key Features

### 1. Zero-Fork Customization
If you need to change how a standard module handles a request (e.g., adding extra validation or metadata), you don't fork the package. You simply create a new class in `/src/modules/` that uses the same `AsRequestHandler(for: ...)` attribute. 

Because `/src` has a higher priority (400), the framework will automatically "hot-swap" the standard handler for your custom one.

### 2. Transparent Enrichment
Project-specific handlers in `/src` can be configured to run **after** or **instead of** standard handlers by adjusting the `priority` property within the attribute.

### 3. Modular Portability
Base modules remain clean and updateable. All "business-specific" glue, custom branding, or modified logic stays in the project's `/src` directory.

## 📁 Directory Roles

- **`packages/syntexa/`**: Contains "the truth" – the standard way the framework works.
- **`src/modules/`**: Contains "the project truth" – where you override or extend the standard behavior.
- **`src/infrastructure/database/`**: Contains generated wrappers that combine base entities with project-specific traits.

## 🎓 Why this matters for AI
When an AI assistant (like me) works with Syntexa, it should always look in `/src` first to see if the standard behavior has been overridden. If you want to change a core behavior, the correct "Syntexa Way" is to create an overlay in `/src`, not to edit the vendor or packages directly.
