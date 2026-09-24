<?php

namespace App\Enums;

enum RepuestoEstado: string
{
    case NUEVO = 'nuevo';
    case REPARADO = 'reparado';
    case PENDIENTE_REPARACION = 'pendiente_reparacion';
}
