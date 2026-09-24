<?php

namespace App\Enums;

enum OrdenEstadoOperativo: string
{
    case EN_ESPERA = 'en_espera';
    case EN_PROCESO = 'en_proceso';
    case FINALIZADA = 'finalizada';
    case ANULADA = 'anulada';
}
