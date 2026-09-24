<?php

namespace App\Enums;

enum ReparacionEstado: string
{
    case PENDIENTE = 'pendiente';
    case EN_PROCESO = 'en_proceso';
    case FINALIZADA = 'finalizada';
}
