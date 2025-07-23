<?php

namespace ItHealer\LaravelEthereum\Enums;

enum EthereumModel: string
{
    case Node = 'node';
    case Explorer = 'explorer';
    case Token = 'token';
    case Wallet = 'wallet';
    case Address = 'address';
    case Transaction = 'transaction';
    case Deposit = 'deposit';
}
