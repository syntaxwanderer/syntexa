# Tests Examples Documentation

This directory contains all documentation for test examples.

## Structure

```
docs/
├── README.md                    # This file
└── Orm/                         # ORM testing documentation
    ├── README.md                # ORM testing overview
    └── Blockchain/              # Blockchain integration testing guides
        ├── README.md            # Blockchain testing overview
        └── MULTI_NODE_TESTING.md # Multi-node blockchain testing guide
```

## Quick Links

### ORM Testing
- [ORM Testing Overview](Orm/README.md) - General ORM testing documentation
- [Blockchain Testing Overview](Orm/Blockchain/README.md) - Blockchain testing strategy
- [Multi-Node Blockchain Testing](Orm/Blockchain/MULTI_NODE_TESTING.md) - Guide for testing blockchain with multiple nodes

## Purpose

This directory is separated from test code files (`.php`) to keep documentation organized and easy to find. All `.md` files related to test examples should be placed here, maintaining the same directory structure as the test code.

## Organization Rules

- **All `.md` files** should be in `tests/Examples/docs/` directory
- **Directory structure** in `docs/` mirrors the test code structure
- **No `.md` files** should be mixed with `.php` test files
- **README.md files** provide navigation and overview for each section
