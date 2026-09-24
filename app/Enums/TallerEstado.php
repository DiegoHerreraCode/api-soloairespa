<?php

namespace App\Enums;

enum TallerEstado: string
{
    case PENDIENTE_ASIGNACION = 'pendiente_asignacion';
    case EN_ESPERA = 'en_espera';
    case EN_PROCESO = 'en_proceso';
    case FINALIZADO = 'finalizado';
}
