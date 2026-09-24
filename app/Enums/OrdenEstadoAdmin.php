<?php

namespace App\Enums;

enum OrdenEstadoAdmin: string
{
    case PENDIENTE_PAGO = 'pendiente_pago';
    case PAGADA = 'pagada';
}
