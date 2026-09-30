<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Resource;

/**
 * Where the admin goes after saving a resource: its list or its edit form.
 */
enum RedirectTarget: string
{
    case Index = 'index';
    case Edit = 'edit';
}
