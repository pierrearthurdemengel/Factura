<?php

namespace App\Tests\Unit\Service\Autopilot;

use App\Service\Autopilot\AutopilotRule;
use PHPUnit\Framework\TestCase;

class AutopilotRuleTest extends TestCase
{
    public function testGetDefaultRulesReturnsNonEmptyList(): void
    {
        $rules = AutopilotRule::getDefaultRules();

        $this->assertNotEmpty($rules);
        $this->assertGreaterThanOrEqual(5, count($rules));
    }

    public function testEachRuleHasRequiredFields(): void
    {
        $requiredFields = ['id', 'name', 'description', 'trigger', 'action', 'params', 'enabled', 'category'];

        foreach (AutopilotRule::getDefaultRules() as $rule) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $rule, "La regle {$rule['id']} manque le champ {$field}");
            }
        }
    }

    public function testRuleIdsAreUnique(): void
    {
        $ids = array_map(
            static fn (array $r): string => $r['id'],
            AutopilotRule::getDefaultRules(),
        );

        $this->assertSame($ids, array_unique($ids));
    }

    public function testAllTriggersAreValid(): void
    {
        $validTriggers = array_keys(AutopilotRule::getAvailableTriggers());

        foreach (AutopilotRule::getDefaultRules() as $rule) {
            $this->assertContains(
                $rule['trigger'],
                $validTriggers,
                "Declencheur invalide : {$rule['trigger']} dans la regle {$rule['id']}",
            );
        }
    }

    public function testAllActionsAreValid(): void
    {
        $validActions = array_keys(AutopilotRule::getAvailableActions());

        foreach (AutopilotRule::getDefaultRules() as $rule) {
            $this->assertContains(
                $rule['action'],
                $validActions,
                "Action invalide : {$rule['action']} dans la regle {$rule['id']}",
            );
        }
    }

    public function testAllCategoriesAreKnown(): void
    {
        $knownCategories = [
            AutopilotRule::CATEGORY_PAYMENT,
            AutopilotRule::CATEGORY_TAX,
            AutopilotRule::CATEGORY_CLIENT,
            AutopilotRule::CATEGORY_REPORTING,
        ];

        foreach (AutopilotRule::getDefaultRules() as $rule) {
            $this->assertContains(
                $rule['category'],
                $knownCategories,
                "Categorie inconnue : {$rule['category']} dans la regle {$rule['id']}",
            );
        }
    }

    public function testGetAvailableTriggersReturnsAll(): void
    {
        $triggers = AutopilotRule::getAvailableTriggers();

        $this->assertArrayHasKey(AutopilotRule::TRIGGER_INVOICE_OVERDUE, $triggers);
        $this->assertArrayHasKey(AutopilotRule::TRIGGER_INVOICE_DUE_SOON, $triggers);
        $this->assertArrayHasKey(AutopilotRule::TRIGGER_PAYMENT_RECEIVED, $triggers);
        $this->assertArrayHasKey(AutopilotRule::TRIGGER_REVENUE_THRESHOLD, $triggers);
        $this->assertArrayHasKey(AutopilotRule::TRIGGER_VAT_DECLARATION_DUE, $triggers);
        $this->assertArrayHasKey(AutopilotRule::TRIGGER_NEW_CLIENT, $triggers);
    }

    public function testGetAvailableActionsReturnsAll(): void
    {
        $actions = AutopilotRule::getAvailableActions();

        $this->assertArrayHasKey(AutopilotRule::ACTION_SEND_REMINDER, $actions);
        $this->assertArrayHasKey(AutopilotRule::ACTION_SEND_NOTIFICATION, $actions);
        $this->assertArrayHasKey(AutopilotRule::ACTION_GENERATE_REPORT, $actions);
        $this->assertArrayHasKey(AutopilotRule::ACTION_MARK_OVERDUE, $actions);
        $this->assertArrayHasKey(AutopilotRule::ACTION_SUGGEST_PAYMENT_PLAN, $actions);
    }

    public function testDefaultRulesHavePaymentReminders(): void
    {
        $rules = AutopilotRule::getDefaultRules();
        $reminderRules = array_filter(
            $rules,
            static fn (array $r): bool => AutopilotRule::ACTION_SEND_REMINDER === $r['action'],
        );

        $this->assertGreaterThanOrEqual(2, count($reminderRules), 'Au moins 2 regles de relance attendues');
    }

    public function testDefaultRulesHaveTaxAlerts(): void
    {
        $rules = AutopilotRule::getDefaultRules();
        $taxRules = array_filter(
            $rules,
            static fn (array $r): bool => AutopilotRule::CATEGORY_TAX === $r['category'],
        );

        $this->assertGreaterThanOrEqual(2, count($taxRules), 'Au moins 2 regles fiscales attendues');
    }

    public function testSomeRulesDisabledByDefault(): void
    {
        $rules = AutopilotRule::getDefaultRules();
        $disabled = array_filter(
            $rules,
            static fn (array $r): bool => !$r['enabled'],
        );

        $this->assertNotEmpty($disabled, 'Certaines regles doivent etre desactivees par defaut');
    }
}
