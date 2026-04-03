<?php

namespace App\Tests\Unit\Validator;

use App\Validator\Constraints\ValidIban;
use App\Validator\Constraints\ValidIbanValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidIbanValidatorTest extends TestCase
{
    private ValidIbanValidator $validator;
    private ExecutionContextInterface $context;
    private ConstraintViolationBuilderInterface $violationBuilder;

    protected function setUp(): void
    {
        $this->validator = new ValidIbanValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);

        $this->validator->initialize($this->context);
    }

    /**
     * Verifie qu'un IBAN francais valide passe.
     */
    public function testValidFrenchIban(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        // IBAN de test FR76 3000 6000 0112 3456 7890 189
        $this->validator->validate('FR7630006000011234567890189', new ValidIban());
    }

    /**
     * Verifie qu'un IBAN avec espaces passe aussi.
     */
    public function testValidIbanWithSpaces(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('FR76 3000 6000 0112 3456 7890 189', new ValidIban());
    }

    /**
     * Verifie qu'un IBAN allemand valide passe.
     */
    public function testValidGermanIban(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate('DE89370400440532013000', new ValidIban());
    }

    /**
     * Verifie qu'un IBAN avec une cle invalide est rejete.
     */
    public function testInvalidIbanChecksum(): void
    {
        $this->violationBuilder->method('setParameter')->willReturnSelf();
        $this->violationBuilder->expects($this->once())->method('addViolation');

        $this->context->method('buildViolation')->willReturn($this->violationBuilder);

        $this->validator->validate('FR7630006000011234567890188', new ValidIban());
    }

    /**
     * Verifie qu'un IBAN trop court est rejete.
     */
    public function testIbanTooShort(): void
    {
        $this->violationBuilder->method('setParameter')->willReturnSelf();
        $this->violationBuilder->expects($this->once())->method('addViolation');

        $this->context->method('buildViolation')->willReturn($this->violationBuilder);

        $this->validator->validate('FR76300', new ValidIban());
    }

    /**
     * Verifie que null passe (champ optionnel).
     */
    public function testNullValuePasses(): void
    {
        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate(null, new ValidIban());
    }
}
