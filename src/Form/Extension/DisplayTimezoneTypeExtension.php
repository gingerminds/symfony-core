<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Extension;

use Gingerminds\CoreBundle\Service\DisplayTimezone;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Date-times are typed and shown in the display timezone, stored in UTC. `DateType` (a day, no time) is left as is.
 */
final class DisplayTimezoneTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly DisplayTimezone $displayTimezone,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [DateTimeType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'model_timezone' => 'UTC',
            'view_timezone' => $this->displayTimezone->getName(),
        ]);
    }
}
