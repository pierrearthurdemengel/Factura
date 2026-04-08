<?php

namespace App\Service\Autopilot;

/**
 * Represente une regle d'automatisation configurable par l'utilisateur.
 *
 * Chaque regle est un triplet : condition + action + parametres.
 * Le moteur d'autopilot evalue les regles actives periodiquement
 * et execute les actions correspondantes quand les conditions sont remplies.
 *
 * @phpstan-type RuleConfig array{id: string, name: string, description: string, trigger: string, action: string, params: array<string, string>, enabled: bool, category: string}
 */
class AutopilotRule
{
    // Declencheurs disponibles
    public const TRIGGER_INVOICE_OVERDUE = 'invoice_overdue';
    public const TRIGGER_INVOICE_DUE_SOON = 'invoice_due_soon';
    public const TRIGGER_PAYMENT_RECEIVED = 'payment_received';
    public const TRIGGER_REVENUE_THRESHOLD = 'revenue_threshold';
    public const TRIGGER_VAT_DECLARATION_DUE = 'vat_declaration_due';
    public const TRIGGER_NEW_CLIENT = 'new_client';

    // Actions disponibles
    public const ACTION_SEND_REMINDER = 'send_reminder';
    public const ACTION_SEND_NOTIFICATION = 'send_notification';
    public const ACTION_GENERATE_REPORT = 'generate_report';
    public const ACTION_MARK_OVERDUE = 'mark_overdue';
    public const ACTION_SUGGEST_PAYMENT_PLAN = 'suggest_payment_plan';

    // Categories
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_TAX = 'tax';
    public const CATEGORY_CLIENT = 'client';
    public const CATEGORY_REPORTING = 'reporting';

    /**
     * Retourne les regles par defaut proposees a chaque utilisateur.
     *
     * @return list<RuleConfig>
     */
    public static function getDefaultRules(): array
    {
        return [
            [
                'id' => 'auto_reminder_7d',
                'name' => 'Relance automatique J+7',
                'description' => 'Envoie un email de relance 7 jours apres echeance si la facture est impayee',
                'trigger' => self::TRIGGER_INVOICE_OVERDUE,
                'action' => self::ACTION_SEND_REMINDER,
                'params' => ['days_after' => '7', 'template' => 'polite'],
                'enabled' => true,
                'category' => self::CATEGORY_PAYMENT,
            ],
            [
                'id' => 'auto_reminder_30d',
                'name' => 'Relance formelle J+30',
                'description' => 'Envoie une mise en demeure 30 jours apres echeance',
                'trigger' => self::TRIGGER_INVOICE_OVERDUE,
                'action' => self::ACTION_SEND_REMINDER,
                'params' => ['days_after' => '30', 'template' => 'formal'],
                'enabled' => true,
                'category' => self::CATEGORY_PAYMENT,
            ],
            [
                'id' => 'due_soon_notification',
                'name' => 'Alerte echeance proche',
                'description' => 'Notifie 3 jours avant l\'echeance d\'une facture',
                'trigger' => self::TRIGGER_INVOICE_DUE_SOON,
                'action' => self::ACTION_SEND_NOTIFICATION,
                'params' => ['days_before' => '3'],
                'enabled' => true,
                'category' => self::CATEGORY_PAYMENT,
            ],
            [
                'id' => 'payment_thank_you',
                'name' => 'Remerciement de paiement',
                'description' => 'Envoie un email de remerciement a la reception du paiement',
                'trigger' => self::TRIGGER_PAYMENT_RECEIVED,
                'action' => self::ACTION_SEND_NOTIFICATION,
                'params' => ['template' => 'thank_you'],
                'enabled' => false,
                'category' => self::CATEGORY_CLIENT,
            ],
            [
                'id' => 'revenue_alert_vat',
                'name' => 'Alerte seuil TVA',
                'description' => 'Alerte quand le CA approche le seuil de franchise de TVA (36 800 EUR)',
                'trigger' => self::TRIGGER_REVENUE_THRESHOLD,
                'action' => self::ACTION_SEND_NOTIFICATION,
                'params' => ['threshold' => '36800', 'alert_at_percent' => '90'],
                'enabled' => true,
                'category' => self::CATEGORY_TAX,
            ],
            [
                'id' => 'revenue_alert_micro',
                'name' => 'Alerte plafond micro-entreprise',
                'description' => 'Alerte quand le CA approche le plafond micro (77 700 EUR BNC)',
                'trigger' => self::TRIGGER_REVENUE_THRESHOLD,
                'action' => self::ACTION_SEND_NOTIFICATION,
                'params' => ['threshold' => '77700', 'alert_at_percent' => '85'],
                'enabled' => true,
                'category' => self::CATEGORY_TAX,
            ],
            [
                'id' => 'vat_reminder',
                'name' => 'Rappel declaration TVA',
                'description' => 'Rappel 5 jours avant la date limite de declaration de TVA',
                'trigger' => self::TRIGGER_VAT_DECLARATION_DUE,
                'action' => self::ACTION_SEND_NOTIFICATION,
                'params' => ['days_before' => '5'],
                'enabled' => true,
                'category' => self::CATEGORY_TAX,
            ],
            [
                'id' => 'monthly_report',
                'name' => 'Rapport mensuel automatique',
                'description' => 'Genere et envoie un rapport de synthese le 1er de chaque mois',
                'trigger' => self::TRIGGER_REVENUE_THRESHOLD,
                'action' => self::ACTION_GENERATE_REPORT,
                'params' => ['frequency' => 'monthly', 'day' => '1'],
                'enabled' => false,
                'category' => self::CATEGORY_REPORTING,
            ],
        ];
    }

    /**
     * Retourne les declencheurs disponibles avec leur description.
     *
     * @return array<string, string>
     */
    public static function getAvailableTriggers(): array
    {
        return [
            self::TRIGGER_INVOICE_OVERDUE => 'Facture en retard de paiement',
            self::TRIGGER_INVOICE_DUE_SOON => 'Echeance de facture proche',
            self::TRIGGER_PAYMENT_RECEIVED => 'Paiement recu',
            self::TRIGGER_REVENUE_THRESHOLD => 'Seuil de chiffre d\'affaires atteint',
            self::TRIGGER_VAT_DECLARATION_DUE => 'Declaration TVA a venir',
            self::TRIGGER_NEW_CLIENT => 'Nouveau client cree',
        ];
    }

    /**
     * Retourne les actions disponibles avec leur description.
     *
     * @return array<string, string>
     */
    public static function getAvailableActions(): array
    {
        return [
            self::ACTION_SEND_REMINDER => 'Envoyer un email de relance',
            self::ACTION_SEND_NOTIFICATION => 'Envoyer une notification',
            self::ACTION_GENERATE_REPORT => 'Generer un rapport',
            self::ACTION_MARK_OVERDUE => 'Marquer la facture en retard',
            self::ACTION_SUGGEST_PAYMENT_PLAN => 'Proposer un echeancier de paiement',
        ];
    }
}
