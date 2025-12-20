## ORM Executable Examples

These tests live in `tests/Examples/Orm/` and act as **living documentation** for the ORM.

- **Basic CRUD**: `BasicCrudTest.php` - Direct save/update/delete operations (no persist/flush)
- **Domain projection**: `DomainProjectionTest.php`
- **Query builder with joins**: `QueryBuilderJoinsTest.php`
- **Relationships (OneToOne, OneToMany, ManyToMany)**: `RelationshipsTest.php`
- **Lazy loading & relationship projection**: `RelationshipLoadingTest.php`
- **Domain + storage extension via traits**: `DomainExtensionTest.php`
- **Repository-centric usage and domain-style methods**: `UserRepositoryExamplesTest.php`

### Key Pattern: Direct Operations

All examples use **direct operations** - no Unit of Work pattern:

```php
// ✅ Correct: Immediate write
$repo->save($user);

// ❌ Old way (removed): 
// $em->persist($user);
// $em->flush();
```

To run all ORM examples:

```bash
./bin/phpunit tests/Examples/Orm/
```

To run with SQLite (fast, no Docker):

```bash
TEST_WITH_SQLITE=1 ./bin/phpunit tests/Examples/Orm/
```

See the PHP files in that folder for concrete usage patterns.

