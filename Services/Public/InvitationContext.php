<?php

namespace App\Modules\Declarations\Services\Public;

use App\Modules\Declarations\Entities\DeclarationInvitation;
use App\Modules\Declarations\Entities\DeclarationPacket;
use App\Modules\Declarations\Entities\Person;

class InvitationContext
{
    public function __construct(
        public readonly string $token,
        public readonly DeclarationInvitation $invitation,
        public readonly DeclarationPacket $packet,
        public readonly ?Person $person,
    ) {
    }
}
