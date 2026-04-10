<?php

namespace App\Banking\DTO;

/**
 * Type de transaction bancaire (debit ou credit).
 */
enum TransactionType: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
