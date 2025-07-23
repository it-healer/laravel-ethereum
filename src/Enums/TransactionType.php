<?php

namespace ItHealer\LaravelEthereum\Enums;

enum TransactionType: string
{
    case OUTGOING = 'out';
    case INCOMING = 'in';
}