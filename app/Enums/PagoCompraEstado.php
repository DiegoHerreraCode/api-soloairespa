<?php

namespace App\Enums;

enum PagoCompraEstado: string
{
    case PENDIENTE = 'pendiente';
    case REALIZADO = 'realizado';
}
