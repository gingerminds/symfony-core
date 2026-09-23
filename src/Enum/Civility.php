<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Civility: string implements TranslatableInterface
{
    case Mr = 'mr';
    case Mrs = 'mrs';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('civility.' . $this->value, [], 'GingermindsCore', $locale);
    }
}
