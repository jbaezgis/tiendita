<table>
    <thead>
    <tr>
        <th>Número de Pedido</th>
        <th>Empleado</th>
        <th>Categoría</th>
        <th>Estado</th>
        <th>Prioridad</th>
        <th>Total</th>
        <th>Fecha de Creación</th>
        <th>Aprobado Por</th>
        <th>Fecha de Aprobación</th>
        <th>Rechazado Por</th>
        <th>Fecha de Rechazo</th>
        <th>Razón del Rechazo</th>
    </tr>
    </thead>
    <tbody>
    @foreach($orders as $order)
        <tr>
            <td>{{ $order->order_number }}</td>
            <td>{{ $order->employee->name ?? 'N/A' }}</td>
            <td>{{ $order->category->name ?? 'N/A' }}</td>
            <td>{{ $order->getStatusText() }}</td>
            <td>{{ $order->getPriorityText() }}</td>
            <td>{{ number_format($order->total, 2) }}</td>
            <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $order->approver->name ?? 'N/A' }}</td>
            <td>{{ $order->approved_at ? $order->approved_at->format('d/m/Y H:i') : 'N/A' }}</td>
            <td>{{ $order->rejector->name ?? 'N/A' }}</td>
            <td>{{ $order->rejected_at ? $order->rejected_at->format('d/m/Y H:i') : 'N/A' }}</td>
            <td>{{ $order->rejection_reason ?? 'N/A' }}</td>
        </tr>
    @endforeach
    </tbody>
</table> 