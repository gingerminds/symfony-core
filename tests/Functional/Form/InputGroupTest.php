<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Twig\Environment;

final class InputGroupTest extends KernelTestCase
{
    public function testPrefixAndSuffixWrapTheInputInAnInputGroup(): void
    {
        $crawler = $this->render($this->form());

        $group = $crawler->filter('#form_slug')->ancestors()->first();
        self::assertSame('input-group', $group->attr('class'));
        self::assertSame(['/products/', '/'], $group->filter('.input-group-text')->each(static fn (Crawler $text): string => $text->text()));
        self::assertSame('form-control', $crawler->filter('#form_slug')->attr('class'));

        $prefixOnly = $crawler->filter('#form_code')->ancestors()->first();
        self::assertSame(['#'], $prefixOnly->filter('.input-group-text')->each(static fn (Crawler $text): string => $text->text()));

        // Without option, or on a non single line input: no group.
        self::assertNull($crawler->filter('#form_name')->closest('.input-group'));
        self::assertNull($crawler->filter('#form_description')->closest('.input-group'));
    }

    public function testAnInvalidInputGroupKeepsItsError(): void
    {
        $form = $this->form();
        $form->submit(['slug' => 'Bad slug']);
        $form->get('slug')->addError(new FormError('Invalid slug.'));

        $crawler = $this->render($form);

        self::assertSame('input-group has-validation', $crawler->filter('#form_slug')->ancestors()->first()->attr('class'));
        self::assertStringContainsString('is-invalid', (string) $crawler->filter('#form_slug')->attr('class'));
        self::assertSame('Invalid slug.', $crawler->filter('.invalid-feedback')->text());
    }

    /**
     * @return FormInterface<mixed>
     */
    private function form(): FormInterface
    {
        return self::getContainer()->get(FormFactoryInterface::class)->createBuilder(FormType::class, null, ['csrf_protection' => false])
            ->add('slug', TextType::class, ['input_prefix' => '/products/', 'input_suffix' => '/'])
            ->add('code', TextType::class, ['input_prefix' => '#'])
            ->add('name', TextType::class)
            ->add('description', TextareaType::class, ['input_prefix' => 'ignored'])
            ->getForm();
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function render(FormInterface $form): Crawler
    {
        $template = self::getContainer()->get(Environment::class)
            ->createTemplate("{% form_theme form '@GingermindsCore/form/theme.html.twig' %}{{ form_widget(form) }}");

        return new Crawler($template->render(['form' => $form->createView()]));
    }
}
