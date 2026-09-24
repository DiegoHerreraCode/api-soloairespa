<?php

namespace App\Enums;

enum CotizacionEstado: string
{
    case PENDIENTE = 'pendiente';
    case ACEPTADA = 'aceptada';
    case RECHAZADA = 'rechazada';
    case ANULADA = 'anulada';
}
