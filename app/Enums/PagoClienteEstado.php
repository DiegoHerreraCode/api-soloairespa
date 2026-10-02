<?php

namespace App\Enums;

enum PagoClienteEstado: string
{
    case COMPLETADO = 'completado';
    case ANULADO = 'anulado';
}
