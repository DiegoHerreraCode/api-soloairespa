<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Alerta de Stock Bajo</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            width: 90%;
            max-width: 650px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .header {
            background: linear-gradient(135deg, #d9534f 0%, #c9302c 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .content {
            padding: 10px;
        }

        .item-list {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }

        .item-card {
            padding: 14px;
            border-bottom: 1px dashed #ddd;
            background-color: #fafafa;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .item-header {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }

        .item-name {
            font-weight: bold;
            color: #c9302c;
            font-size: 1.05em;
        }

        .item-type-badge {
            display: inline-block;
            background-color: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 600;
            margin-left: 8px;
            text-transform: uppercase;
        }

        .item-details {
            font-size: 0.9em;
            color: #555;
            margin-top: 4px;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            font-size: 0.8em;
            text-align: center;
            color: #888;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Alerta: Stock Bajo en Inventario</h1>
        </div>
        <div class="content">
            <p>Estimado/a <strong>{{ $admin_nombre }}</strong>,</p>
            <p>Se ha detectado que los siguientes ítems de inventario (compresores, válvulas, equipos o insumos) han superado su nivel de stock mínimo tras la última operación realizada:</p>

            <div class="item-list">
                @foreach($items as $item)
                    <div class="item-card">
                        <div class="item-header">
                            <span class="item-name">{{ $item['nombre'] }}</span>
                            @if(!empty($item['tipo']))
                                <span class="item-type-badge">{{ $item['tipo'] }}</span>
                            @endif
                        </div>
                        <div class="item-details">
                            @if(!empty($item['sku']))
                                <span>SKU: <strong>{{ $item['sku'] }}</strong> | </span>
                            @endif
                            <span>Stock Propio Actual: <span class="badge-danger">{{ $item['cantidad_propia'] }}</span></span> |
                            <span>Stock Mínimo Requerido: <strong>{{ $item['stock_minimo'] }}</strong></span>
                        </div>
                    </div>
                @endforeach
            </div>

            <p style="margin-top: 25px;">Se recomienda gestionar oportunamente la reposición o compras de estos ítems para garantizar la continuidad operativa de taller y ventas.</p>
        </div>
        <div class="footer">
            <p>Este es un mensaje automático generado por el sistema SoloAire SpA.</p>
        </div>
    </div>
</body>

</html>
