# AsDomainPart Attribute

## Description

The `#[AsDomainPart]` attribute marks a trait as extending a domain model.  
Domain parts allow modules to extend domain models with additional properties and methods without modifying the base domain class.

This is similar to `#[AsEntityPart]` for storage entities, but works with clean domain models (no ORM attributes).

## Usage

```php
use Syntexa\Orm\Attributes\AsDomainPart;
use Syntexa\UserFrontend\Domain\Entity\User;

#[AsDomainPart(base: User::class)]
trait UserMarketingProfileDomainTrait
{
    private ?\DateTimeImmutable $birthday = null;
    private bool $marketingOptIn = false;
    private ?string $favoriteCategory = null;

    public function getBirthday(): ?\DateTimeImmutable
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeImmutable $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function hasMarketingOptIn(): bool
    {
        return $this->marketingOptIn;
    }

    public function setMarketingOptIn(bool $marketingOptIn): void
    {
        $this->marketingOptIn = $marketingOptIn;
    }

    public function getFavoriteCategory(): ?string
    {
        return $this->favoriteCategory;
    }

    public function setFavoriteCategory(?string $favoriteCategory): void
    {
        $this->favoriteCategory = $favoriteCategory;
    }
}
```

## Parameters

### Required

- `base` (string) - Fully-qualified class name of the base domain class that this trait extends.

## Requirements

1. Trait MUST be marked with `#[AsDomainPart]` attribute.
2. `base` parameter MUST point to an existing domain class.
3. Domain class MUST be configured in storage entity's `#[AsEntity]` attribute with `domainClass` parameter.
4. Trait should contain only domain logic (no ORM attributes like `#[Column]`).

## Generate Domain Wrapper

After creating domain parts, generate the wrappers:

```bash
# Generate both storage and domain wrappers automatically
bin/syntexa entity:generate User
# or
bin/syntexa entity:generate --all
```

This automatically creates:
- `src/infrastructure/Database/User.php` - Storage entity wrapper
- `src/modules/{Module}/Domain/User.php` - Domain model wrapper (extends base + uses domain parts)

**Note:** If you only need domain wrappers, you can use:
```bash
bin/syntexa domain:generate User
# or
bin/syntexa domain:generate --all
```

## Examples

### Basic Domain Part

```php
namespace Acme\Marketing\Domain\Entity;

use Syntexa\Orm\Attributes\AsDomainPart;
use Syntexa\UserFrontend\Domain\Entity\User;

#[AsDomainPart(base: User::class)]
trait UserMarketingProfileDomainTrait
{
    private ?string $marketingTag = null;

    public function getMarketingTag(): ?string
    {
        return $this->marketingTag;
    }

    public function setMarketingTag(?string $marketingTag): void
    {
        $this->marketingTag = $marketingTag;
    }
}
```

### Domain Part with Business Logic

```php
#[AsDomainPart(base: User::class)]
trait UserMarketingProfileDomainTrait
{
    private bool $marketingOptIn = false;
    private ?\DateTimeImmutable $lastStoreVisitAt = null;

    public function hasMarketingOptIn(): bool
    {
        return $this->marketingOptIn;
    }

    public function setMarketingOptIn(bool $marketingOptIn): void
    {
        $this->marketingOptIn = $marketingOptIn;
    }

    public function getLastStoreVisitAt(): ?\DateTimeImmutable
    {
        return $this->lastStoreVisitAt;
    }

    public function setLastStoreVisitAt(?\DateTimeImmutable $lastStoreVisitAt): void
    {
        $this->lastStoreVisitAt = $lastStoreVisitAt;
    }

    /**
     * Check if user should receive marketing emails
     */
    public function shouldReceiveMarketingEmails(): bool
    {
        return $this->marketingOptIn 
            && $this->lastStoreVisitAt !== null 
            && $this->lastStoreVisitAt > new \DateTimeImmutable('-30 days');
    }
}
```

## Workflow

1. **Create base domain class** in module (e.g., `Syntexa\UserFrontend\Domain\Entity\User`)
2. **Configure storage entity** with `domainClass` parameter:
   ```php
   #[AsEntity(table: 'users', domainClass: User::class)]
   class User { ... }
   ```
3. **Create domain part trait** in another module with `#[AsDomainPart]`
4. **Generate domain wrapper** using `bin/syntexa domain:generate User`
5. **Use wrapper domain class** in application code (e.g., `Syntexa\Modules\UserFrontend\Domain\User`)

## Differences from AsEntityPart

- **AsEntityPart**: Extends storage entities (infrastructure layer) - can have ORM attributes
- **AsDomainPart**: Extends domain models (domain layer) - no ORM attributes, only business logic

## Related attributes

- `#[AsEntity]` - Storage entity that maps to domain class
- `#[AsEntityPart]` - Trait for extending storage entities

## See also

- [AsEntity](AsEntity.md) - Storage entity configuration
- [AsEntityPart](AsEntityPart.md) - Storage entity extension
- [ORM Documentation](../orm/README.md) - Complete ORM guide

