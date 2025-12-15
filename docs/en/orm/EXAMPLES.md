## ORM Executable Examples

These tests live in `tests/Examples/Orm/` and act as **living documentation** for the ORM.

- **Basic CRUD**: `BasicCrudTest.php`
- **Domain projection**: `DomainProjectionTest.php`
- **Query builder with joins**: `QueryBuilderJoinsTest.php`
- **Relationships (OneToOne, OneToMany, ManyToMany)**: `RelationshipsTest.php`
- **Lazy loading & relationship projection**: `RelationshipLoadingTest.php`
- **Domain + storage extension via traits**: `DomainExtensionTest.php`
- **Repository-centric usage and domain-style methods**: `UserRepositoryExamplesTest.php`

To run all ORM examples:

```bash
./bin/phpunit tests/Examples/Orm/
```

To run with SQLite (fast, no Docker):

```bash
TEST_WITH_SQLITE=1 ./bin/phpunit tests/Examples/Orm/
```

See the PHP files in that folder for concrete usage patterns.

