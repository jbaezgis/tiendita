<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido {{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #1e40af;
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header h2 {
            color: #1e40af;
            margin: 0px 0 0 0;
            font-size: 16px;
            font-weight: bold;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .order-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .info-section {
            flex: 1;
            margin: 0 10px;
        }
        
        .info-section h3 {
            color: #2563eb;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-delivered { background-color: #dbeafe; color: #1e40af; }
        
        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .priority-low { background-color: #d1fae5; color: #065f46; }
        .priority-medium { background-color: #fef3c7; color: #92400e; }
        .priority-high { background-color: #fee2e2; color: #991b1b; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            color: #475569;
        }
        
        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            font-size: 11px;
        }
        
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .total-row {
            background-color: #f1f5f9 !important;
            font-weight: bold;
        }
        
        .total-row td {
            border-top: 2px solid #2563eb;
        }
        
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 4px;
        }
        
        .notes-section h3 {
            color: #92400e;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .rejection-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            border-radius: 4px;
        }
        
        .rejection-section h3 {
            color: #991b1b;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 10px;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .page-number {
            text-align: right;
            font-size: 10px;
            color: #666;
            font-weight: bold;
            background-color: #f8fafc;
            padding: 5px 10px;
            border-radius: 3px;
            border: 1px solid #e2e8f0;
            margin-top: 20px;
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px;
        }
        .logo img {
            max-width: 120px;
            height: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        @if($logoBase64)
            <div class="logo">
                <img src="{{ $logoBase64 }}" alt="Logo">
            </div>
        @endif
        <h1>Tiendita AJFA</h1>
        <h2>Tienda de productos de Grupo AJFA</h2>
    </div>

    <div class="order-info">
        <div class="info-section">
            <h3>Información del Empleado</h3>
            <div class="info-row">
                <span class="info-label">Nombre:</span>
                <span class="info-value">{{ $order->employee->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Departamento:</span>
                <span class="info-value">{{ $order->employee->department }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Cédula:</span>
                <span class="info-value">{{ $order->employee->cedula }}</span>
            </div>
        </div>

        <div class="info-section">
            <h3>Información del Pedido</h3>
            <div class="info-row">
                <span class="info-label">Número:</span>
                <span class="info-value">{{ $order->order_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha:</span>
                <span class="info-value">{{ $order->order_date->format('d/m/Y') }}</span>
            </div>
            {{-- <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value">
                    <span class="status-badge status-{{ $order->status }}">
                        {{ $order::getStatusOptions()[$order->status] }}
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Prioridad:</span>
                <span class="info-value">
                    <span class="priority-badge priority-{{ $order->priority }}">
                        {{ $order::getPriorityOptions()[$order->priority] }}
                    </span>
                </span>
            </div> --}}
        </div>

        {{-- <div class="info-section">
            <h3>Información de Categoría</h3>
            @if($order->category)
                <div class="info-row">
                    <span class="info-label">Categoría:</span>
                    <span class="info-value">{{ $order->category->code }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Límite de compra:</span>
                    <span class="info-value">${{ number_format($order->category->purchase_limit, 2) }}</span>
                </div>
                @if(!$order->isWithinPurchaseLimit())
                    <div class="info-row">
                        <span class="info-label" style="color: #dc2626;">⚠️ Excede límite</span>
                        <span class="info-value" style="color: #dc2626;">Sí</span>
                    </div>
                @endif
            @else
                <div class="info-row">
                    <span class="info-label">Categoría:</span>
                    <span class="info-value">No asignada</span>
                </div>
            @endif
        </div> --}}
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Código</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
                {{-- <th>Estado</th> --}}
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->description }}</td>
                    <td>{{ $item->product->code }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->price, 2) }}</td>
                    <td>${{ number_format($item->subtotal, 2) }}</td>
                    {{-- <td>
                        <span class="status-badge status-{{ $item->status }}">
                            {{ ucfirst($item->status) }}
                        </span>
                    </td> --}}
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;"><strong>Total:</strong></td>
                <td><strong>${{ number_format($order->total, 2) }}</strong></td>
                {{-- <td></td> --}}
            </tr>
        </tfoot>
    </table>

    @if($order->notes)
        <div class="notes-section">
            <h3>Notas del Pedido</h3>
            <p>{{ $order->notes }}</p>
        </div>
    @endif

    @if($order->status === 'rejected' && $order->rejection_reason)
        <div class="rejection-section">
            <h3>Razón del Rechazo</h3>
            <p>{{ $order->rejection_reason }}</p>
        </div>
    @endif

    @if($order->approver || $order->rejector)
        <div class="page-break"></div>
        <div class="header">
            <h1>Información de Procesamiento</h1>
        </div>
        
        <div class="order-info">
            @if($order->approver)
                <div class="info-section">
                    <h3>Aprobación</h3>
                    <div class="info-row">
                        <span class="info-label">Aprobado por:</span>
                        <span class="info-value">{{ $order->approver->name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha de aprobación:</span>
                        <span class="info-value">{{ $order->approved_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            @endif
            
            @if($order->rejector)
                <div class="info-section">
                    <h3>Rechazo</h3>
                    <div class="info-row">
                        <span class="info-label">Rechazado por:</span>
                        <span class="info-value">{{ $order->rejector->name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Fecha de rechazo:</span>
                        <span class="info-value">{{ $order->rejected_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @if($order->rejection_reason)
                        <div class="info-row">
                            <span class="info-label">Razón del rechazo:</span>
                            <span class="info-value">{{ $order->rejection_reason }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
        
        <div class="page-number">Página 2 de {{ $totalPages }}</div>
    @endif

        <div class="footer">
            <p>Documento generado automáticamente por el Sistema de Gestión Escolar</p>
            <p>Fecha de generación: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
        
        {{-- <div class="page-number">Página 1 de {{ $totalPages }}</div> --}}
</body>
</html> 