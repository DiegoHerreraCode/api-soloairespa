<?php

namespace App\Enums;

enum CompraEstado: string
{
    case POR_PAGAR = 'por_pagar';
    case PAGADA_PARCIAL = 'pagada_parcial';
    case PAGADA = 'pagada';
}
