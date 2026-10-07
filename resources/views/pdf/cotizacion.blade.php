<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cotizacion {{ $cotizacion->num_cotizacion ?? "" }}</title>
    <style>
        @page {
            margin: 22px 26px 22px 26px;
        }

        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        .w-100 { width: 100%; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .clear { clear: both; }

        /* Encabezado */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo-img {
            max-width: 170px;
            max-height: 75px;
        }

        .header-title-box {
            text-align: right;
        }

        .header-main-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.5px;
            margin: 0 0 2px 0;
        }

        .header-cotizacion-num {
            font-size: 14px;
            font-weight: 700;
            color: #dc2626;
            margin: 0 0 2px 0;
        }

        .header-tipo-badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        /* Bloque Cliente */
        .section-box {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 7px 10px;
            margin-bottom: 10px;
            background-color: #ffffff;
        }

        .section-box-title {
            font-size: 9px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .table-data {
            width: 100%;
            border-collapse: collapse;
        }

        .table-data td {
            padding: 2px 4px;
            font-size: 9.5px;
            vertical-align: top;
        }

        .table-data .lbl {
            font-weight: 700;
            color: #334155;
            width: 15%;
        }

        .table-data .val {
            color: #0f172a;
            width: 35%;
        }

        /* Grilla Fechas / Tipo / Padre */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .meta-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-align: center;
            vertical-align: middle;
        }

        .meta-table td {
            padding: 6px;
            border: 1px solid #cbd5e1;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
            font-weight: 600;
            color: #0f172a;
        }

        /* Tablas de Detalles */
        .items-section-title {
            font-size: 10px;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 10px 0 4px 0;
            padding-left: 2px;
        }

        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .grid-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 5px 3px;
            border: 1px solid #1e3a8a;
            text-align: center;
            vertical-align: middle;
        }

        .grid-table td {
            border: 1px solid #e2e8f0;
            padding: 4px 3px;
            font-size: 8.5px;
            vertical-align: middle;
            text-align: center;
        }

        .grid-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Seccion Totales y Empresa */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .footer-table td {
            vertical-align: top;
        }

        .company-box {
            width: 58%;
            padding-right: 15px;
        }

        .company-box-card {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 8px 10px;
            background-color: #f8fafc;
        }

        .company-name {
            font-size: 11px;
            font-weight: 800;
            color: #1e3a8a;
            margin-bottom: 3px;
        }

        .company-line {
            font-size: 9px;
            color: #334155;
            margin-bottom: 2px;
        }

        .company-bank-title {
            font-size: 8.5px;
            font-weight: 700;
            color: #475569;
            margin-top: 6px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .totals-box {
            width: 42%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background-color: #ffffff;
        }

        .totals-table td {
            padding: 4px 8px;
            font-size: 9.5px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .totals-table .lbl {
            font-weight: 600;
            color: #475569;
        }

        .totals-table .val {
            font-weight: 700;
            color: #0f172a;
            text-align: right;
        }

        .totals-table tr.grand-total td {
            border-top: 2px solid #dc2626;
            background-color: #fef2f2;
            color: #dc2626;
            font-size: 12px;
            font-weight: 800;
            padding: 7px 8px;
        }
    </style>
</head>

<body>

    <!-- 1. ENCABEZADO -->
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo">
                @else
                    <h2 style="margin: 0; color: #1e3a8a;">{{ $empresa["nombre"] ?? "SOLO AIRE SPA" }}</h2>
                @endif
            </td>
            <td style="width: 50%;" class="header-title-box">
                <div class="header-main-title">COTIZACI&Oacute;N</div>
                <div class="header-cotizacion-num">#{{ $cotizacion->num_cotizacion ?? ("COT-" . str_pad($cotizacion->id_cotizacion, 5, "0", STR_PAD_LEFT)) }}</div>
                <div>
                    @php
                        $tipoVal = is_object($cotizacion->tipo) ? ($cotizacion->tipo->value ?? "inicial") : ($cotizacion->tipo ?? "inicial");
                    @endphp
                    <span class="header-tipo-badge uppercase">
                        TIPO: {{ strtoupper($tipoVal) }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 2. DATA DEL CLIENTE -->
    <div class="section-box">
        <div class="section-box-title">DATOS DEL CLIENTE</div>
        <table class="table-data">
            <tr>
                <td class="lbl">Nombre / Raz&oacute;n Social:</td>
                <td class="val font-bold">{{ $cliente->nombre ?? "N/A" }}</td>
                <td class="lbl">RUT:</td>
                <td class="val font-bold">{{ $cliente->rut ?? "N/A" }}</td>
            </tr>
            <tr>
                <td class="lbl">Direcci&oacute;n:</td>
                <td class="val">{{ $cliente->direccion ?? "N/A" }}</td>
                <td class="lbl">Tel&eacute;fono:</td>
                <td class="val">{{ $cliente->num_tlf ?? "N/A" }}</td>
            </tr>
            <tr>
                <td class="lbl">Correo Electr&oacute;nico:</td>
                <td class="val">{{ $cliente->correo ?? "N/A" }}</td>
                <td class="lbl">Atendido Por:</td>
                <td class="val">{{ $cotizacion->admin->nombre ?? "Administrador" }}</td>
            </tr>
        </table>
    </div>

    <!-- 3. FECHAS / TIPO ORDEN / NUM COTIZACION PADRE -->
    <table class="meta-table">
        <thead>
            <tr>
                <th style="width: 25%;">FECHA EMISI&Oacute;N</th>
                <th style="width: 25%;">FECHA VENCIMIENTO</th>
                <th style="width: 25%;">TIPO DE &Oacute;RDEN</th>
                <th style="width: 25%;"># COTIZACI&Oacute;N PADRE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ !empty($cotizacion->fecha_creacion) ? \Carbon\Carbon::parse($cotizacion->fecha_creacion)->format("d/m/Y") : date("d/m/Y") }}</td>
                <td>{{ !empty($cotizacion->fecha_vencimiento) ? \Carbon\Carbon::parse($cotizacion->fecha_vencimiento)->format("d/m/Y") : "-" }}</td>
                <td class="uppercase">{{ $tipoOrden->nombre ?? ($cotizacion->orden->tipoOrden->nombre ?? "N/A") }}</td>
                <td class="font-bold">
                    {{ !empty($cotizacion->num_cotizacion_padre) ? $cotizacion->num_cotizacion_padre : "-" }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- 4. REGISTROS DE LA TABLA COTIZACIONES_INVENTARIO -->
    @if(isset($cotizacion->detallesInventario) && count($cotizacion->detallesInventario) > 0)
        <div class="items-section-title">DETALLE DE INSUMOS Y REPUESTOS (INVENTARIO)</div>
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 7%;">ID INSUMO SAL.</th>
                    <th style="width: 7%;">ID REP. SAL.</th>
                    <th style="width: 7%;">ID REP. ENT.</th>
                    <th style="width: 17%;">DESCRIPCI&Oacute;N</th>
                    <th style="width: 5%;">CANT.</th>
                    <th style="width: 8%;">PRECIO UNIT.</th>
                    <th style="width: 7%;">TASACI&Oacute;N</th>
                    <th style="width: 9%;">TOTAL SIN IVA</th>
                    <th style="width: 5%;">% IVA</th>
                    <th style="width: 7%;">MONTO IVA</th>
                    <th style="width: 9%;">TOTAL CON IVA</th>
                    <th style="width: 12%;">SERVICE TAG</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detallesInventario as $detInv)
                    @php
                        $nombreItem = $detInv->inventarioInsumoSaliente->nombre 
                            ?? $detInv->inventarioRepuestoSaliente->nombre 
                            ?? $detInv->inventarioRepuestoEntrante->nombre 
                            ?? "-";
                    @endphp
                    <tr>
                        <td>{{ $detInv->id_inventario_insumo_saliente ?? "-" }}</td>
                        <td>{{ $detInv->id_inventario_repuesto_saliente ?? "-" }}</td>
                        <td>{{ $detInv->id_inventario_repuesto_entrante ?? "-" }}</td>
                        <td>{{ $nombreItem }}</td>
                        <td class="font-bold">{{ $detInv->cantidad }}</td>
                        <td>${{ number_format($detInv->precio_unitario, 2, ",", ".") }}</td>
                        <td>
                            {{ !empty($detInv->monto_tasacion) ? "$" . number_format($detInv->monto_tasacion, 2, ",", ".") : "-" }}
                        </td>
                        <td class="font-bold">${{ number_format($detInv->monto_total_linea_sin_iva, 2, ",", ".") }}</td>
                        <td>{{ number_format($detInv->porcentaje_iva, 0) }}%</td>
                        <td>${{ number_format($detInv->monto_iva, 2, ",", ".") }}</td>
                        <td class="font-bold">${{ number_format($detInv->monto_total_linea_con_iva, 2, ",", ".") }}</td>
                        <td style="font-size: 8px;">{{ $detInv->service_tag_repuesto_a_reparar ?? "-" }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 5. REGISTROS DE LA TABLA COTIZACIONES_SERVICIOS_TALLER -->
    @if(isset($cotizacion->detallesServiciosTaller) && count($cotizacion->detallesServiciosTaller) > 0)
        <div class="items-section-title">DETALLE DE SERVICIOS DE TALLER</div>
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 10%;">ID SERVICIO</th>
                    <th style="width: 25%;">DESCRIPCI&Oacute;N SERVICIO</th>
                    <th style="width: 6%;">CANT.</th>
                    <th style="width: 10%;">PRECIO UNIT.</th>
                    <th style="width: 11%;">TOTAL SIN IVA</th>
                    <th style="width: 6%;">% IVA</th>
                    <th style="width: 9%;">MONTO IVA</th>
                    <th style="width: 11%;">TOTAL CON IVA</th>
                    <th style="width: 12%;">SERVICE TAG</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detallesServiciosTaller as $detServ)
                    @php
                        $nombreServ = $detServ->servicioTaller->nombre ?? "-";
                    @endphp
                    <tr>
                        <td>{{ $detServ->id_servicio_taller ?? "-" }}</td>
                        <td>{{ $nombreServ }}</td>
                        <td class="font-bold">{{ $detServ->cantidad }}</td>
                        <td>${{ number_format($detServ->precio_unitario, 2, ",", ".") }}</td>
                        <td class="font-bold">${{ number_format($detServ->monto_total_linea_sin_iva, 2, ",", ".") }}</td>
                        <td>{{ number_format($detServ->porcentaje_iva, 0) }}%</td>
                        <td>${{ number_format($detServ->monto_iva, 2, ",", ".") }}</td>
                        <td class="font-bold">${{ number_format($detServ->monto_total_linea_con_iva, 2, ",", ".") }}</td>
                        <td style="font-size: 8px;">{{ $detServ->service_tag_repuesto_a_reparar ?? "-" }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 6. TOTALES ECONOMICOS Y DATA DE LA EMPRESA -->
    <table class="footer-table">
        <tr>
            <!-- DATA DE LA EMPRESA -->
            <td class="company-box">
                <div class="company-box-card">
                    <div class="company-name">{{ $empresa["nombre"] ?? "SOLO AIRE SPA" }}</div>
                    <div class="company-line font-bold">RUT: {{ $empresa["rut"] ?? "77.618.216-8" }}</div>
                    <div class="company-line">{{ $empresa["direccion"] ?? "Maruri 857 Independencia, Santiago de Chile" }}</div>
                    <div class="company-line">Tel&eacute;fono: {{ $empresa["telefono"] ?? "+56 985394629" }}</div>
                    <div class="company-line">Correo: {{ $empresa["correo"] ?? "Soloairespa@gmail.com" }}</div>
                    
                    <div class="company-bank-title">DATOS BANCARIOS PARA TRANSFERENCIA:</div>
                    <div class="company-line font-bold">{{ $empresa["banco"] ?? "BANCO SANTANDER" }}</div>
                    <div class="company-line">{{ $empresa["tipo_cuenta"] ?? "CUENTA CORRIENTE" }}: {{ $empresa["numero_cuenta"] ?? "0-000-8871366-4" }}</div>
                </div>
            </td>

            <!-- TOTALES MONTOS ECONOMICOS DE LA COTIZACION -->
            <td class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td class="lbl">SUBTOTAL GRAVADO:</td>
                        <td class="val">${{ number_format($cotizacion->monto_total_gravado ?? 0, 2, ",", ".") }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">SUBTOTAL EXENTO:</td>
                        <td class="val">${{ number_format($cotizacion->monto_total_exento ?? 0, 2, ",", ".") }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">I.V.A TOTAL:</td>
                        <td class="val">${{ number_format($cotizacion->monto_total_iva ?? 0, 2, ",", ".") }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td>TOTAL A PAGAR:</td>
                        <td class="text-right">${{ number_format($cotizacion->monto_total ?? 0, 2, ",", ".") }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>

</html>