<?php

namespace App\Enums;

enum InventarioCondicion: string
{
    case NUEVO = 'nuevo';
    case USADO = 'usado';
    case NA = 'NA';
}
