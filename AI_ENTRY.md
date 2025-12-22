# Syntexa Framework - AI Assistant Entry Point

> **Important:** All original documentation is written in English. Translations to other languages may be available in language-specific directories (e.g., `docs/uk/`, `docs/it/`).

## Project Structure

Understanding the project structure is crucial for working with Syntexa Framework. Here's where everything is located:

### Core Directories

```
syntexa/
├── bin/                    # Executable scripts
│   └── syntexa            # Main CLI tool
├── docs/                   # Documentation (see structure below)
├── packages/              # Framework packages
│   ├── syntexa/          # Core framework packages
│   │   ├── core/         # Core framework functionality
│   │   ├── orm/          # ORM package
│   │   ├── user-api/     # User API module
│   │   └── user-frontend/# User frontend module
│   └── acme/             # Example/third-party modules
├── src/                   # Application source code
│   ├── infrastructure/   # Infrastructure layer
│   │   ├── database/     # Generated entity wrappers
│   │   └── migrations/   # Database migrations (PHP classes)
│   └── modules/          # Application modules
├── var/                   # Variable/temporary files
│   ├── cache/            # Cache files
│   ├── data/             # Data files
│   ├── log/              # Log files
│   └── docs/             # Working/intermediate documentation (for AI)
└── vendor/               # Composer dependencies
```

### Documentation Structure

```
docs/
├── en/                    # English (original) documentation
│   ├── architecture/      # Architecture documentation
│   ├── attributes/        # Attribute documentation
│   ├── guides/            # User guides
│   └── api/              # API documentation
├── uk/                    # Ukrainian translations (if available)
├── it/                    # Italian translations (if available)
└── ...                    # Other language translations
```

### Working Directory for AI

The `var/docs/` directory is a **working directory** where AI assistants can:
- Create intermediate documentation files
- Store temporary analysis results
- Generate draft documentation
- Keep work-in-progress files

**Important:** Files in `var/docs/` are temporary and may be cleaned up. Do not rely on them for permanent documentation.

## Key Concepts

### 1. Module System

Syntexa uses a modular architecture:
- **Core packages** (`packages/syntexa/*`) - Framework core functionality
- **Application modules** (`src/modules/*`) - Application-specific code
- **Third-party modules** (`packages/acme/*`) - External modules

### 2. Request/Response/Handler Pattern

The framework uses a clean separation:
- **Request** - Input DTO with `#[AsRequest]` attribute
- **Response** - Output DTO with `#[AsResponse]` attribute  
- **Handler** - Business logic with `#[AsRequestHandler]` attribute

### 3. ORM Entities

Entities follow a specific pattern:
- Base entities in modules (e.g., `packages/syntexa/user-frontend/src/Domain/Entity/`)
- Generated infrastructure entities in `src/infrastructure/database/`
- Extension via traits with `#[AsEntityPart]` attribute

### 4. Attribute System

All framework attributes support documentation via `#[Documentation]` attribute:
- Documentation files are in `docs/en/attributes/`
- Each attribute can reference its documentation
- AI can automatically discover and read documentation

## Getting Started

1. **Read the architecture**: Start with `docs/en/architecture/ARCHITECTURE.md`
2. **Understand conventions**: See `docs/en/guides/CONVENTIONS.md`
3. **Learn attributes**: Browse `docs/en/attributes/`
4. **Check examples**: Look at `docs/en/guides/EXAMPLES.md`

## Important Files

- `docs/en/architecture/ARCHITECTURE.md` - Framework architecture
- `docs/en/guides/CONVENTIONS.md` - Coding conventions and patterns
- `docs/en/attributes/README.md` - Attribute documentation index
- `docs/AI_GUIDELINES.md` - Guidelines for AI assistants
- `var/docs/` - Working directory for AI (temporary files)

## Language Support

- **English (en)** - Original documentation, always up-to-date
- **Other languages** - Translations in `docs/{lang}/` directories

When working with documentation:
1. Always refer to English version (`docs/en/`) as the source of truth
2. Translations may lag behind the original
3. If translation is missing, use English version

## For AI Assistants

### Mandatory Rules

When working with this codebase, you **MUST** follow these rules:

0. **⚠️ LANGUAGE POLICY** - **CRITICAL AND MANDATORY**: 
   - **ALL documentation files MUST be written in English** - this includes:
     - All README.md files (including in `tests/` directories)
     - All documentation in `docs/en/` (obviously)
     - All documentation in `var/docs/` (working directory)
     - All code comments in documentation files
     - All test documentation and examples
   - **Ukrainian (`docs/uk/`) and Italian (`docs/it/`)** are **translations only** - they are examples of translations, NOT the primary language
   - **NEVER write documentation in Ukrainian or Italian** unless explicitly creating a translation file in `docs/uk/` or `docs/it/`
   - **If you write documentation in Ukrainian by mistake, you MUST immediately translate it to English**
   - **Rule of thumb**: If the file is not in `docs/uk/` or `docs/it/`, it MUST be in English

1. **Always start here** - Read this file first
2. **Check structure** - Understand where things are located
3. **Use working directory** - Use `var/docs/` for intermediate MD documents (in English!)
4. **Read documentation** - Check `docs/en/` for detailed information
5. **Follow conventions** - See `docs/en/guides/CONVENTIONS.md`
6. **⚠️ UPDATE DOCUMENTATION** - **MANDATORY**: After making changes to framework core code:
   - **New features** → Update relevant documentation in `docs/en/`
   - **Behavior changes** → Update affected documentation files
   - **API changes** → Update API documentation in `docs/en/api/`
   - **Attribute changes** → Update attribute documentation in `docs/en/attributes/`
   - **Architecture changes** → Update `docs/en/architecture/ARCHITECTURE.md`
   
   **Rule:** Documentation must be updated **automatically and immediately** after code changes. Outdated documentation is worse than no documentation.

## Quick Reference

| What | Where |
|------|-------|
| Core framework | `packages/syntexa/core/` |
| ORM | `packages/syntexa/orm/` |
| Application code | `src/modules/` |
| Documentation (EN) | `docs/en/` |
| Working files (AI) | `var/docs/` |
| CLI tool | `bin/syntexa` |

---

**Last updated:** 2024
**Framework version:** See `composer.json`

