# Syntexa Framework Documentation

> **Important:** All original documentation is written in **English**.  
> Translations to other languages may be available in language-specific directories.

## Structure

```
docs/
├── en/                    # English (original) documentation
│   ├── architecture/      # Architecture documentation
│   ├── attributes/        # PHP attribute documentation
│   ├── guides/           # User guides and tutorials
│   └── api/              # API reference documentation
├── uk/                    # Ukrainian translations (if available)
├── it/                    # Italian translations (if available)
└── ...                    # Other language translations
```

## Language Support

- **English (en)** - Original documentation, always up-to-date
- **Other languages** - Translations in `docs/{lang}/` directories

### Important Notes

1. **English is the source of truth** - Always refer to `docs/en/` for the most current information
2. **Translations may lag** - Translations may not always be up-to-date with the original
3. **Missing translations** - If a translation is missing, use the English version

## Quick Links

- [English Documentation](en/README.md) - Main documentation index
- [Architecture](en/architecture/ARCHITECTURE.md) - Framework architecture
- [Conventions](en/guides/CONVENTIONS.md) - Coding conventions
- [Examples](en/guides/EXAMPLES.md) - Usage examples
- [Attributes](en/attributes/README.md) - Attribute documentation

## For AI Assistants

When working with documentation:

1. **Start with** `AI_ENTRY.md` in the project root
2. **Use English version** (`docs/en/`) as the primary source
3. **Check structure** - Understand the directory organization
4. **Use working directory** - Use `var/docs/` for temporary/intermediate files

## Contributing

When adding new documentation:

1. Write in English first (`docs/en/`)
2. Place in appropriate directory
3. Follow existing documentation style
4. Update language-specific README files if adding new sections

