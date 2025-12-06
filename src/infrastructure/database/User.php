<?php

declare(strict_types=1);
namespace Syntexa\Infrastructure\Database;

use \Syntexa\UserFrontend\Domain\Entity\User as SyntexaUserBase;
use \Acme\Marketing\Domain\Entity\UserMarketingProfileTrait as AcmeUserMarketingProfileTraitTrait;


/**
 * Infrastructure aggregate for Syntexa\UserFrontend\Domain\Entity\User
 * Base module: syntexa/user-frontend
 * Table: users
 *
 * Extensions:
 *  - acme/module-user-marketing: Acme\Marketing\Domain\Entity\UserMarketingProfileTrait
 *
 * @syntexa-columns {"email":{"entity":"Syntexa\UserFrontend\Domain\Entity\User","column":"email","type":"string","nullable":false,"unique":true,"length":null,"default":null,"timestamp":null},"passwordHash":{"entity":"Syntexa\UserFrontend\Domain\Entity\User","column":"password_hash","type":"string","nullable":false,"unique":false,"length":null,"default":null,"timestamp":null},"name":{"entity":"Syntexa\UserFrontend\Domain\Entity\User","column":"name","type":"string","nullable":true,"unique":false,"length":null,"default":null,"timestamp":null},"id":{"entity":"Syntexa\UserFrontend\Domain\Entity\User","column":"id","type":"int","nullable":true,"unique":false,"length":null,"default":null,"timestamp":null},"createdAt":{"entity":"Syntexa\UserFrontend\Domain\Entity\User","column":"created_at","type":"datetime_immutable","nullable":true,"unique":false,"length":null,"default":null,"timestamp":"created"},"updatedAt":{"entity":"Syntexa\UserFrontend\Domain\Entity\User","column":"updated_at","type":"datetime_immutable","nullable":true,"unique":false,"length":null,"default":null,"timestamp":"updated"}}
 */
class User extends SyntexaUserBase
{

    use AcmeUserMarketingProfileTraitTrait;
}
