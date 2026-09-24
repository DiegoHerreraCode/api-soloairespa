<?php

namespace App\Enums;

enum InventarioTipo: string
{
    case INSUMO = 'insumo';
    case COMPRESOR = 'compresor';
    case VALVULA = 'valvula';
    case EQUIPO = 'equipo';
}
