# Syntexa Framework Examples

> **Status:** Draft - To be completed  
> **Location:** `docs/en/guides/EXAMPLES.md`

This document provides practical examples of using Syntexa Framework.

## Table of Contents

- [Creating a New Module](#creating-a-new-module)
- [Adding a New Handler](#adding-a-new-handler)
- [Extending Entity via Trait](#extending-entity-via-trait)
- [Using ORM](#using-orm)

## Creating a New Module

### Step 1: Create Module Structure

```
packages/acme/module-example/
├── composer.json
└── src/
    ├── Application/
    │   ├── Handler/
    │   │   └── Request/
    │   ├── Input/
    │   │   └── Http/
    │   ├── Output/
    │   └── View/
    │       └── templates/
    └── Domain/
        ├── Entity/
        ├── Repository/
        └── Service/
```

### Step 2: Configure composer.json

```json
{
    "name": "acme/module-example",
    "type": "syntexa-module",
    "autoload": {
        "psr-4": {
            "Acme\\Example\\": "src/"
        }
    }
}
```

## Adding a New Handler

### Complete Example

```php
// 1. Create Request
#[Documentation]
#[AsRequest(path: '/api/products', methods: ['GET'])]
class ProductListRequest implements RequestInterface {}

// 2. Create Response
#[Documentation]
#[AsResponse(template: 'products/list.html.twig')]
class ProductListResponse implements ResponseInterface {}

// 3. Create Handler
#[Documentation]
#[AsRequestHandler(for: ProductListRequest::class)]
class ProductListHandler implements HttpHandlerInterface
{
    public function __construct(
        #[Inject] private ProductRepository $repository
    ) {}

    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $products = $this->repository->findAll();
        $response->setContext(['products' => $products]);
        return $response;
    }
}
```

## Extending Entity via Trait

### Base Entity

```php
#[AsEntity(table: 'users')]
class User extends BaseEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id', type: 'integer')]
    private ?int $id = null;
    
    #[Column(name: 'email', type: 'string')]
    private string $email;
}
```

### Extension Trait

```php
#[AsEntityPart(base: User::class)]
trait UserMarketingTrait
{
    #[Column(name: 'marketing_tag', type: 'string', nullable: true)]
    public ?string $marketingTag = null;
}
```

### Generate Wrapper

```bash
bin/syntexa entity:generate User
```

## Using ORM

### Repository Pattern

```php
use Syntexa\Orm\Entity\EntityManager;
use DI\Attribute\Inject;

class UserRepository
{
    public function __construct(
        #[Inject] private EntityManager $em
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->em->findOneBy(User::class, ['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
```

### Query Builder

```php
$users = $em->createQueryBuilder()
    ->select('u.*')
    ->from(User::class, 'u')
    ->where('u.email = :email', ['email' => 'test@example.com'])
    ->orderBy('u.createdAt', 'DESC')
    ->setMaxResults(10)
    ->getResult();
```

---

**Note:** More examples will be added as the framework evolves.

