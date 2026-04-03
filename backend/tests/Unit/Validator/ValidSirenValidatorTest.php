<?php

namespace App\Tests\Unit\Validator;

use App\Validator\Constraints\ValidSiren;
use App\Validator\Constraints\ValidSirenValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidSirenValidatorTest extends TestCase
{
    private ValidSirenValidator $validator;
    private ExecutionContextInterface $context;
    private ConstraintViolationBuilderInterface $violationBuilder;

    protected function setUp(): void
    {
        $this->validator = new ValidSirenValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);

        $this->validator->initialize($this->context);
    }

    /**
     * Verifie qu'un SIREN valide passe la validation.
     * 732 829 320 est le SIREN de La Poste.
     */
    public function testValidSiren(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('732829320', new ValidSiren());
    }

    /**
     * Verifie qu'un SIREN avec une cle de controle invalide est rejete.
     */
    public function testInvalidSirenChecksum(): void
    {
        $this->violationBuilder->method('setParameter')->willReturnSelf();
        $this->violationBuilder->expects($this->once())->method('addViolation');

        $this->context->method('buildViolation')->willReturn($this->violationBuilder);

        $this->validator->validate('732829321', new ValidSiren());
    }

    /**
     * Verifie qu'un SIREN trop court est rejete.
     */
    public function testSirenTooShort(): void
    {
        $this->violationBuilder->method('setParameter')->willReturnSelf();
        $this->violationBuilder->expects($this->once())->method('addViolation');

        $this->context->method('buildViolation')->willReturn($this->violationBuilder);

        $this->validator->validate('12345', new ValidSiren());
    }

    /**
     * Verifie qu'un SIREN contenant des lettres est rejete.
     */
    public function testSirenWithLetters(): void
    {
        $this->violationBuilder->method('setParameter')->willReturnSelf();
        $this->violationBuilder->expects($this->once())->method('addViolation');

        $this->context->method('buildViolation')->willReturn($this->violationBuilder);

        $this->validator->validate('73282ABCD', new ValidSiren());
    }

    /**
     * Verifie que null passe sans violation (champ optionnel).
     */
    public function testNullValuePasses(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate(null, new ValidSiren());
    }

    /**
     * Verifie qu'une chaine vide passe sans violation.
     */
    public function testEmptyStringPasses(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('', new ValidSiren());
    }

    /**
     * Verifie un autre SIREN valide (SIREN de Total).
     */
    public function testAnotherValidSiren(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        // SIREN 542 051 180 (TotalEnergies)
        $this->validator->validate('542051180', new ValidSiren());
    }
}
