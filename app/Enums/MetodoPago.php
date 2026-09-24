<?php

namespace App\Enums;

enum MetodoPago: string
{
    case TRANSFERENCIA = 'transferencia';
    case EFECTIVO = 'efectivo';
    case CHEQUE = 'cheque';
}
