<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Form;

use Gingerminds\CoreBundle\Form\Extension\DisplayTimezoneTypeExtension;
use Gingerminds\CoreBundle\Service\DisplayTimezone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

final class DisplayTimezoneTypeExtensionTest extends TestCase
{
    public function testSubmittedValueIsConvertedToUtc(): void
    {
        $form = $this->factory()->create(DateTimeType::class, null, ['widget' => 'single_text', 'input' => 'datetime_immutable']);
        $form->submit('2026-07-01T10:30');

        $data = $form->getData();
        self::assertInstanceOf(\DateTimeImmutable::class, $data);
        self::assertSame('2026-07-01 08:30 UTC', $data->format('Y-m-d H:i T'));
    }

    public function testStoredValueIsShownInTheDisplayTimezone(): void
    {
        $date = new \DateTimeImmutable('2026-07-01 08:30', new \DateTimeZone('UTC'));
        $form = $this->factory()->create(DateTimeType::class, $date, ['widget' => 'single_text', 'input' => 'datetime_immutable']);

        self::assertSame('2026-07-01T10:30', $form->createView()->vars['value']);
    }

    public function testExplicitOptionsWin(): void
    {
        $form = $this->factory()->create(DateTimeType::class, null, ['view_timezone' => 'UTC']);

        self::assertSame('UTC', $form->getConfig()->getOption('view_timezone'));
        self::assertSame('UTC', $form->getConfig()->getOption('model_timezone'));
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new DisplayTimezoneTypeExtension(new DisplayTimezone()))
            ->getFormFactory();
    }
}
