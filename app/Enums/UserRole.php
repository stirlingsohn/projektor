<?php

namespace App\Enums;

enum UserRole: string
{
    /** The workspace owner, who can see and manage everything. */
    case Owner = 'owner';

    /** An invited client, who only sees what they were invited to. */
    case Client = 'client';
}
